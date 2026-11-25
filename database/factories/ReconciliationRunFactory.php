<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReconciliationRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn () => tenant()?->getTenantKey(),
            'channel_connection_id' => fn () => ChannelConnection::query()->value('id'),
            'status' => ReconciliationRun::STATUS_COMPLETED,
            'items_count' => 1,
            'discrepancies_count' => 1,
            'corrections_count' => 0,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }
}
