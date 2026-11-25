<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelProductMappingFactory extends Factory
{
    protected $model = ChannelProductMapping::class;

    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'channel_listing_id' => ChannelListing::factory(),
            'product_variant_id' => ProductVariant::factory(),
        ];
    }
}
