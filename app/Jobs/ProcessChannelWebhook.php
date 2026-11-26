<?php

namespace App\Jobs;

use App\Actions\Orders\ImportChannelOrder;
use App\Adapters\ChannelAdapterResolver;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProcessChannelWebhook implements ShouldBeUnique, ShouldQueue
{
    use InitializesActiveTenant, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $timeout = 120;

    public function __construct(
        public readonly int $webhookEventId,
        public readonly string $tenantId,
    ) {}

    public function uniqueId(): string
    {
        return 'webhook-event:'.$this->webhookEventId;
    }

    public function handle(ChannelAdapterResolver $resolver, ImportChannelOrder $importChannelOrder): void
    {
        if (! $this->initializeActiveTenant($this->tenantId)) {
            return;
        }

        $event = null;

        try {
            $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

            if ($event->status === WebhookEvent::STATUS_PROCESSED) {
                return;
            }

            $event->status = WebhookEvent::STATUS_PROCESSING;
            $event->attempts = $this->attempts();
            $event->save();

            $adapter = $resolver->resolve($event->provider);
            $orderData = is_array($event->payload) ? $event->payload : [];

            if ($event->external_order_id !== null && (empty($orderData['items']) || ! isset($orderData['external_order_id']))) {
                $orderData = $adapter->fetchOrder(
                    $event->connection->decryptedCredentials(),
                    $event->external_order_id,
                );
            }

            if ($orderData === []) {
                throw new RuntimeException('Webhook payload did not contain a usable order.');
            }

            $importChannelOrder($event->connection, $orderData, $event->raw_payload);

            $event->status = WebhookEvent::STATUS_PROCESSED;
            $event->raw_payload = null;
            $event->last_error = null;
            $event->next_try_at = null;
            $event->processed_at = now();
            $event->save();
        } catch (Throwable $exception) {
            if ($event instanceof WebhookEvent) {
                $attempts = max(1, $this->attempts());
                $finalAttempt = $attempts >= $this->tries;
                $delay = $this->backoff[min($attempts - 1, count($this->backoff) - 1)] ?? 60;

                $event->status = $finalAttempt
                    ? WebhookEvent::STATUS_FAILED
                    : WebhookEvent::STATUS_RETRYING;
                $event->attempts = $attempts;
                $event->next_try_at = $finalAttempt ? null : now()->addSeconds($delay);
                $event->last_error = Str::limit($exception->getMessage(), 1000);
                $event->save();
            }

            throw $exception;
        } finally {
            tenancy()->end();
        }
    }
}
