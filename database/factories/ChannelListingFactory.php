<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelListingFactory extends Factory
{
    protected $model = ChannelListing::class;

    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'external_id' => 'GID-'.fake()->unique()->numerify('######'),
            'title' => fake()->sentence(3),
            'status' => ChannelListing::STATUS_ACTIVE,
            'last_imported_at' => now(),
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => ChannelListing::STATUS_ARCHIVED]);
    }
}
