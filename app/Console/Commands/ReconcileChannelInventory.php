<?php

namespace App\Console\Commands;

use App\Actions\Channels\RunChannelReconciliation;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ReconcileChannelInventory extends Command
{
    protected $signature = 'inventory:reconcile-channels';

    protected $description = 'Compare local channel quantities with remote channel inventory';

    public function __construct(private readonly RunChannelReconciliation $runChannelReconciliation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $discrepancies = 0;

        foreach (Tenant::query()->active()->pluck('id') as $tenantId) {
            tenancy()->initialize(Tenant::query()->findOrFail($tenantId));

            try {
                $connections = ChannelConnection::query()
                    ->active()
                    ->with('credentials')
                    ->get();

                foreach ($connections as $connection) {
                    $discrepancies += ($this->runChannelReconciliation)($connection)->discrepancies_count;
                }
            } finally {
                tenancy()->end();
            }
        }

        $this->info('Logged '.$discrepancies.' channel inventory discrepancy(ies).');

        return self::SUCCESS;
    }
}
