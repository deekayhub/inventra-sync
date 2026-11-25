<?php

namespace App\Actions\Channels;

use App\Models\InventoryStock;

class CalculateChannelAvailableQuantity
{
    public function __invoke(string $tenantId, int $productVariantId): int
    {
        return InventoryStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
            ->where('inventory_stocks.tenant_id', $tenantId)
            ->where('warehouses.is_active', true)
            ->where('inventory_stocks.product_variant_id', $productVariantId)
            ->get([
                'inventory_stocks.on_hand',
                'inventory_stocks.reserved',
                'inventory_stocks.buffer_stock',
            ])
            ->sum(fn (InventoryStock $stock): int => $stock->channelAvailableStock);
    }
}
