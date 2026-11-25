<?php

namespace App\Console\Commands;

use App\Jobs\DispatchInventoryOutbox;
use App\Models\Tenant;
use Illuminate\Console\Command;

class DispatchInventoryOutboxCommand extends Command
{
    protected $signature = 'inventory:dispatch-outbox';

    protected $description = 'Dispatch due channel inventory outbox events';

    public function handle(): int
    {
        $tenantIds = Tenant::query()->active()->pluck('id');

        foreach ($tenantIds as $tenantId) {
            DispatchInventoryOutbox::dispatch((string) $tenantId);
        }

        $this->info('Dispatched inventory outbox jobs for '.$tenantIds->count().' tenant(s).');

        return self::SUCCESS;
    }
}
