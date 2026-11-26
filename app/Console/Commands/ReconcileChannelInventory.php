<?php

namespace App\Console\Commands;

use App\Actions\Channels\RunChannelReconciliation;
use App\Jobs\InitializesActiveTenant;
use App\Models\ChannelConnection;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ReconcileChannelInventory extends Command
{
    protected $signature = 'inventory:reconcile-channels';

    protected $description = 'Compare local channel quantities with remote channel inventory';

    use InitializesActiveTenant;

    public function __construct(private readonly RunChannelReconciliation $runChannelReconciliation)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $discrepancies = 0;

        foreach (Tenant::query()->active()->pluck('id') as $tenantId) {
            if (! $this->initializeActiveTenant((string) $tenantId)) {
                continue;
            }

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
