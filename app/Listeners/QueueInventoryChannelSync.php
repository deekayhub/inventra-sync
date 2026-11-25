<?php

namespace App\Listeners;

use App\Actions\Channels\QueueInventoryChannelSync as QueueInventoryChannelSyncAction;
use App\Events\InventoryChanged;

class QueueInventoryChannelSync
{
    public function __construct(private readonly QueueInventoryChannelSyncAction $queueInventoryChannelSync) {}

    public function handle(InventoryChanged $event): void
    {
        ($this->queueInventoryChannelSync)($event->stock, $event->movement, $event->changeType);
    }
}
