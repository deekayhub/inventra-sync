<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductImageFactory extends Factory
{
    protected $model = ProductImage::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'path' => 'product-images/'.fake()->uuid().'.jpg',
            'alt_text' => fake()->optional(0.7)->sentence(),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
