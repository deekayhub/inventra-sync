<?php

namespace App\Jobs;

use App\Actions\Channels\ProcessInventoryOutboxEvent;
use App\Models\InventoryOutboxEvent;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DispatchInventoryOutbox implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InitializesActiveTenant, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return 'inventory-outbox:'.$this->tenantId;
    }

    public function handle(ProcessInventoryOutboxEvent $processEvent): void
    {
        if (! $this->initializeActiveTenant($this->tenantId)) {
            return;
        }

        try {
            $eventIds = DB::transaction(function (): array {
                $events = InventoryOutboxEvent::query()
                    ->due()
                    ->orderBy('id')
                    ->limit(50)
                    ->lockForUpdate()
                    ->get(['id']);

                if ($events->isEmpty()) {
                    return [];
                }

                $eventIds = $events->map(fn (InventoryOutboxEvent $event): int => $event->id)->all();

                InventoryOutboxEvent::query()
                    ->whereKey($eventIds)
                    ->update([
                        'status' => InventoryOutboxEvent::STATUS_PROCESSING,
                        'next_try_at' => null,
                    ]);

                return $eventIds;
            });

            InventoryOutboxEvent::query()
                ->whereKey($eventIds)
                ->get()
                ->each(fn (InventoryOutboxEvent $event) => $processEvent($event));
        } finally {
            tenancy()->end();
        }
    }
}
