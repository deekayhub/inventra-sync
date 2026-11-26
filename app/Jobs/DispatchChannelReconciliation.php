<?php

namespace App\Jobs;

use App\Actions\Channels\RunChannelReconciliation;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchChannelReconciliation implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use InitializesActiveTenant, Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?int $channelConnectionId = null,
    ) {}

    public function uniqueId(): string
    {
        return 'channel-reconciliation:'.$this->tenantId.':'.($this->channelConnectionId ?? 'all');
    }

    public function handle(RunChannelReconciliation $runChannelReconciliation): void
    {
        if (! $this->initializeActiveTenant($this->tenantId)) {
            return;
        }

        try {
            ChannelConnection::query()
                ->active()
                ->with('credentials')
                ->when($this->channelConnectionId !== null, fn ($query) => $query->where('id', $this->channelConnectionId))
                ->get()
                ->each(fn (ChannelConnection $connection) => $runChannelReconciliation($connection));
        } finally {
            tenancy()->end();
        }
    }
}
