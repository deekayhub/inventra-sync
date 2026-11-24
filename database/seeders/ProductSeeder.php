<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $now = now();

        $categories = ProductCategory::query()
            ->whereIn('slug', ['office-supplies', 'beverages', 'homeware'])
            ->get()
            ->keyBy('slug');

        $products = [
            [
                'name' => 'A4 Notebook',
                'description' => 'Ruled A4 notebook for daily office use.',
                'category' => 'office-supplies',
                'type' => Product::TYPE_SIMPLE,
                'low_stock_threshold' => 12,
                'buffer_stock' => 2,
                'variants' => [
                    ['sku' => 'DEMO-NOTE-A4', 'title' => 'A4 ruled', 'is_primary' => true, 'sort_order' => 0, 'attributes' => null],
                ],
            ],
            [
                'name' => 'Blue Ballpoint Pen',
                'description' => 'Blue ink retractable ballpoint pen.',
                'category' => 'office-supplies',
                'type' => Product::TYPE_SIMPLE,
                'low_stock_threshold' => 30,
                'buffer_stock' => 5,
                'variants' => [
                    ['sku' => 'DEMO-PEN-BLUE', 'title' => 'Blue ink', 'is_primary' => true, 'sort_order' => 0, 'attributes' => null],
                ],
            ],
            [
                'name' => 'Ground Coffee 250g',
                'description' => 'Medium roast ground coffee in a 250g bag.',
                'category' => 'beverages',
                'type' => Product::TYPE_SIMPLE,
                'low_stock_threshold' => 8,
                'buffer_stock' => 2,
                'variants' => [
                    ['sku' => 'DEMO-COFFEE-250G', 'title' => '250g bag', 'is_primary' => true, 'sort_order' => 0, 'attributes' => null],
                ],
            ],
            [
                'name' => 'Ceramic Mug',
                'description' => 'Ceramic mug available in two colours.',
                'category' => 'homeware',
                'type' => Product::TYPE_VARIABLE,
                'low_stock_threshold' => 10,
                'buffer_stock' => 2,
                'variants' => [
                    ['sku' => 'DEMO-MUG-BLACK', 'title' => 'Black', 'is_primary' => true, 'sort_order' => 0, 'attributes' => ['colour' => 'Black']],
                    ['sku' => 'DEMO-MUG-WHITE', 'title' => 'White', 'is_primary' => false, 'sort_order' => 1, 'attributes' => ['colour' => 'White']],
                ],
            ],
        ];

        $variantRows = [];

        foreach ($products as $product) {
            $category = $categories->get($product['category']);

            if (! $category instanceof ProductCategory) {
                continue;
            }

            $record = Product::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $product['name']],
                [
                    'product_category_id' => $category->id,
                    'description' => $product['description'],
                    'type' => $product['type'],
                    'status' => Product::STATUS_ACTIVE,
                    'low_stock_threshold' => $product['low_stock_threshold'],
                    'buffer_stock' => $product['buffer_stock'],
                ],
            );

            foreach ($product['variants'] as $variant) {
                $variantRows[] = [
                    'tenant_id' => $tenantId,
                    'product_id' => $record->id,
                    'sku' => $variant['sku'],
                    'barcode' => null,
                    'title' => $variant['title'],
                    'variant_attributes' => $variant['attributes'] === null ? null : json_encode($variant['attributes']),
                    'sort_order' => $variant['sort_order'],
                    'is_primary' => (int) $variant['is_primary'],
                    'status' => ProductVariant::STATUS_ACTIVE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($variantRows !== []) {
            ProductVariant::query()->insertOrIgnore($variantRows);
        }
    }
}
