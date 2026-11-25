<?php

namespace App\Jobs;

use App\Actions\Orders\ImportChannelOrder;
use App\Adapters\ChannelAdapterResolver;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessChannelWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

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
        tenancy()->initialize(Tenant::findOrFail($this->tenantId));

        $event = null;

        try {
            $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

            if ($event->status === WebhookEvent::STATUS_PROCESSED) {
                return;
            }

            $event->status = WebhookEvent::STATUS_PROCESSING;
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
                throw new \RuntimeException('Webhook payload did not contain a usable order.');
            }

            $importChannelOrder($event->connection, $orderData, $event->raw_payload);

            $event->status = WebhookEvent::STATUS_PROCESSED;
            $event->processed_at = now();
            $event->save();
        } catch (\Throwable $exception) {
            if ($event instanceof WebhookEvent) {
                $event->status = WebhookEvent::STATUS_FAILED;
                $event->save();
            }

            throw $exception;
        } finally {
            tenancy()->end();
        }
    }
}
