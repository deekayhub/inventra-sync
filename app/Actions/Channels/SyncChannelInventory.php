<?php

namespace App\Actions\Channels;

use App\Adapters\ChannelAdapterResolver;
use App\Models\ChannelConnection;
use App\Models\InventoryStock;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SyncChannelInventory
{
    public function __construct(private ChannelAdapterResolver $resolver) {}

    public function __invoke(ChannelConnection $connection): int
    {
        if (! $connection->isActive()) {
            return 0;
        }

        $connection->load('credentials');

        $mappings = $connection->mappings()
            ->with(['listing', 'inventorySetting'])
            ->whereHas('inventorySetting', fn ($query) => $query->where('paused', false))
            ->get();

        if ($mappings->isEmpty()) {
            return 0;
        }

        $variantIds = $mappings->pluck('product_variant_id')->unique()->values()->all();
        $stocksByVariant = InventoryStock::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
            ->where('inventory_stocks.tenant_id', $connection->tenant_id)
            ->where('warehouses.is_active', true)
            ->whereIn('inventory_stocks.product_variant_id', $variantIds)
            ->get([
                'inventory_stocks.product_variant_id',
                'inventory_stocks.on_hand',
                'inventory_stocks.reserved',
                'inventory_stocks.buffer_stock',
            ])
            ->groupBy('product_variant_id');

        $adapter = $this->resolver->resolve($connection->provider);
        $credentials = $connection->decryptedCredentials();
        $synced = 0;

        try {
            foreach ($mappings as $mapping) {
                $available = $stocksByVariant
                    ->get($mapping->product_variant_id, collect())
                    ->sum(fn (InventoryStock $stock): int => $stock->channelAvailableStock);

                $quantity = max(0, $available - (int) $mapping->inventorySetting->buffer_stock);
                $adapter->pushInventory($credentials, $mapping->listing->external_id, $quantity);
                $synced++;
            }
        } catch (ConnectionException|RequestException|InvalidArgumentException) {
            throw ValidationException::withMessages([
                'connection' => __('Channel inventory could not be synchronized.'),
            ]);
        }

        DB::transaction(fn () => $connection->forceFill(['last_synced_at' => now()])->save());

        activity('channels')
            ->performedOn($connection)
            ->causedBy(auth()->user())
            ->event('synced')
            ->log("synced {$synced} listing(s)");

        return $synced;
    }
}
