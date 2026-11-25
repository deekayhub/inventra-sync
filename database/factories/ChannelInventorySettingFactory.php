<?php

namespace Database\Factories;

use App\Models\ChannelInventorySetting;
use App\Models\ChannelProductMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelInventorySettingFactory extends Factory
{
    protected $model = ChannelInventorySetting::class;

    public function definition(): array
    {
        return [
            'channel_product_mapping_id' => ChannelProductMapping::factory(),
            'buffer_stock' => 0,
            'paused' => false,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn (): array => ['paused' => true]);
    }
}
