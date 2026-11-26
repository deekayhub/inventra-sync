<?php

namespace App\Http\Controllers\Webhooks;

use App\Adapters\ChannelAdapterResolver;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessChannelWebhook;
use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ChannelWebhookController extends Controller
{
    public function __invoke(string $provider, Request $request, ChannelAdapterResolver $resolver): Response
    {
        $payload = $request->getContent();
        $signatureHeader = $provider === ChannelConnection::PROVIDER_SHOPIFY
            ? 'X-Shopify-Hmac-Sha256'
            : 'X-WC-Webhook-Signature';
        $signature = (string) $request->header($signatureHeader, '');

        if ($signature === '') {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $adapter = $resolver->resolve($provider);
        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $connectionsQuery = ChannelConnection::query()
            ->active()
            ->where('provider', $provider);

        if ($provider === ChannelConnection::PROVIDER_SHOPIFY && $shopDomain !== '') {
            $connectionsQuery->where('account_identifier', $shopDomain);
        }

        $connections = $connectionsQuery->get();

        $connection = null;

        foreach ($connections as $candidate) {
            $secret = $candidate->decryptedCredentials()['webhook_secret'] ?? null;

            if (is_string($secret) && $secret !== '' && $adapter->verifyWebhookSignature($payload, $signature, $secret)) {
                $connection = $candidate;

                break;
            }
        }

        if (! $connection instanceof ChannelConnection) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        try {
            $parsed = $adapter->parseWebhookEvent($payload, $request->headers->all());
        } catch (InvalidArgumentException) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (! $this->isOrderTopic($provider, $parsed['topic'])) {
            return response()->noContent(Response::HTTP_ACCEPTED);
        }

        $payloadHash = hash('sha256', $payload);
        $tenantId = (string) tenant()->getTenantKey();

        try {
            $event = WebhookEvent::firstOrCreate([
                'tenant_id' => $tenantId,
                'channel_connection_id' => $connection->id,
                'payload_hash' => $payloadHash,
            ], [
                'provider' => $provider,
                'external_event_id' => $parsed['external_event_id'],
                'topic' => $parsed['topic'],
                'external_order_id' => $parsed['external_order_id'],
                'raw_payload' => $payload,
                'payload' => $parsed['payload'],
                'status' => WebhookEvent::STATUS_PENDING,
                'attempts' => 0,
                'next_try_at' => null,
                'last_error' => null,
                'signature_verified_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->noContent(Response::HTTP_ACCEPTED);
        }

        $shouldDispatch = $event->wasRecentlyCreated;

        if (! $shouldDispatch && in_array($event->status, [
            WebhookEvent::STATUS_FAILED,
            WebhookEvent::STATUS_RETRYING,
        ], true)) {
            $event->status = WebhookEvent::STATUS_PENDING;
            $event->attempts = 0;
            $event->next_try_at = null;
            $event->last_error = null;
            $event->save();
            $shouldDispatch = true;
        }

        if ($shouldDispatch) {
            ProcessChannelWebhook::dispatch($event->id, $tenantId)->afterCommit();
        }

        return response()->noContent(Response::HTTP_ACCEPTED);
    }

    private function isOrderTopic(string $provider, string $topic): bool
    {
        if ($provider === ChannelConnection::PROVIDER_SHOPIFY) {
            return str_starts_with($topic, 'orders/');
        }

        return str_contains($topic, 'order');
    }
}
