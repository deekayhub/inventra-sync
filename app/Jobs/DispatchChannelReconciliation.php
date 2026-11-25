<?php

namespace App\Jobs;

use App\Actions\Channels\RunChannelReconciliation;
use App\Models\ChannelConnection;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchChannelReconciliation implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly ?int $channelConnectionId = null,
    ) {}

    public function handle(RunChannelReconciliation $runChannelReconciliation): void
    {
        tenancy()->initialize($this->tenantId);

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
