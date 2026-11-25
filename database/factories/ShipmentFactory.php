<?php

namespace Database\Factories;

use App\Models\Fulfilment;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'fulfilment_id' => Fulfilment::factory(),
            'status' => Shipment::STATUS_PENDING,
            'carrier' => null,
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
        ];
    }
}
