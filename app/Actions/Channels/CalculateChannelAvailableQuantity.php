<?php

namespace App\Actions\Channels;

use App\Models\InventoryStock;

class CalculateChannelAvailableQuantity
{
    public function __invoke(string $tenantId, int $productVariantId): int
    {
        return $this->forVariants($tenantId, [$productVariantId])[$productVariantId] ?? 0;
    }

    /** @param list<int> $productVariantIds */
    public function forVariants(string $tenantId, array $productVariantIds): array
    {
        if ($productVariantIds === []) {
            return [];
        }

        $quantities = InventoryStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
            ->where('inventory_stocks.tenant_id', $tenantId)
            ->where('warehouses.is_active', true)
            ->whereIn('inventory_stocks.product_variant_id', $productVariantIds)
            ->selectRaw(
                'inventory_stocks.product_variant_id, SUM(CASE WHEN inventory_stocks.on_hand - inventory_stocks.reserved - inventory_stocks.buffer_stock > 0 THEN inventory_stocks.on_hand - inventory_stocks.reserved - inventory_stocks.buffer_stock ELSE 0 END) as available_quantity',
            )
            ->groupBy('inventory_stocks.product_variant_id')
            ->pluck('available_quantity', 'inventory_stocks.product_variant_id')
            ->map(fn (mixed $quantity): int => (int) $quantity)
            ->all();

        $result = array_fill_keys($productVariantIds, 0);

        foreach ($quantities as $productVariantId => $quantity) {
            $result[(int) $productVariantId] = $quantity;
        }

        return $result;
    }
}
