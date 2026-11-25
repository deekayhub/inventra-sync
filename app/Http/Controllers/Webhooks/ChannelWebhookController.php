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
        $connections = ChannelConnection::query()
            ->active()
            ->where('provider', $provider)
            ->with('credentials')
            ->get();

        if ($provider === ChannelConnection::PROVIDER_SHOPIFY && $shopDomain !== '') {
            $connections = $connections->where('account_identifier', $shopDomain)->values();
        }

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

        try {
            $event = WebhookEvent::firstOrCreate([
                'tenant_id' => (string) tenant()->getTenantKey(),
                'channel_connection_id' => $connection->id,
                'external_event_id' => $parsed['external_event_id'],
            ], [
                'provider' => $provider,
                'topic' => $parsed['topic'],
                'external_order_id' => $parsed['external_order_id'],
                'raw_payload' => $payload,
                'payload' => $parsed['payload'],
                'status' => WebhookEvent::STATUS_PENDING,
                'signature_verified_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return response()->noContent(Response::HTTP_ACCEPTED);
        }

        if ($event->wasRecentlyCreated) {
            ProcessChannelWebhook::dispatch($event->id, (string) tenant()->getTenantKey())->afterCommit();
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
