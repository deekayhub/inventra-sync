<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelConnectionFactory extends Factory
{
    protected $model = ChannelConnection::class;

    public function definition(): array
    {
        return [
            'provider' => ChannelConnection::PROVIDER_SHOPIFY,
            'name' => fake()->unique()->company(),
            'account_identifier' => fake()->unique()->domainWord().'-store.myshopify.com',
            'status' => ChannelConnection::STATUS_ACTIVE,
            'activated_at' => now(),
            'last_synced_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ChannelConnection::STATUS_PENDING,
            'activated_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => ChannelConnection::STATUS_INACTIVE,
        ]);
    }
}
