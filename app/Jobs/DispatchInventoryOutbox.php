<?php

namespace App\Jobs;

use App\Actions\Channels\ProcessInventoryOutboxEvent;
use App\Models\InventoryOutboxEvent;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchInventoryOutbox implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return 'inventory-outbox:'.$this->tenantId;
    }

    public function handle(ProcessInventoryOutboxEvent $processEvent): void
    {
        $tenant = Tenant::query()->findOrFail($this->tenantId);
        tenancy()->initialize($tenant);

        try {
            InventoryOutboxEvent::query()
                ->due()
                ->orderBy('id')
                ->limit(50)
                ->get()
                ->each(fn (InventoryOutboxEvent $event) => $processEvent($event));
        } finally {
            tenancy()->end();
        }
    }
}
