<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $tenantId = (string) tenant()->getTenantKey();
        $now = now();

        $categories = [
            ['name' => 'Office Supplies', 'description' => 'Notebooks, pens, and desk essentials.'],
            ['name' => 'Beverages', 'description' => 'Coffee, tea, and bottled drinks.'],
            ['name' => 'Homeware', 'description' => 'Mugs and everyday household items.'],
        ];

        $rows = array_map(fn (array $category): array => [
            'tenant_id' => $tenantId,
            'name' => $category['name'],
            'slug' => ProductCategory::makeSlug($category['name']),
            'description' => $category['description'],
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ], $categories);

        ProductCategory::query()->insertOrIgnore($rows);
    }
}
