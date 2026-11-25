<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ProductVariant;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReconciliationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey(),
            'reconciliation_run_id' => fn () => ReconciliationRun::query()->value('id'),
            'channel_connection_id' => fn () => ChannelConnection::query()->value('id'),
            'channel_listing_id' => fn () => ChannelListing::query()->value('id'),
            'product_variant_id' => fn () => ProductVariant::query()->value('id'),
            'status' => ReconciliationItem::STATUS_DISCREPANCY,
            'expected_quantity' => 10,
            'remote_quantity' => 7,
            'difference' => -3,
            'correction_quantity' => -3,
        ];
    }
}
