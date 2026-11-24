<?php

namespace App\Events;

use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InventoryChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly InventoryStock $stock,
        public readonly InventoryMovement $movement,
        public readonly string $changeType,
    ) {}
}
