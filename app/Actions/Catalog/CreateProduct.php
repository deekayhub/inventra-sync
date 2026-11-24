<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __invoke(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create([
                'product_category_id' => $data['product_category_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? Product::TYPE_SIMPLE,
                'status' => $data['status'] ?? Product::STATUS_DRAFT,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
                'buffer_stock' => $data['buffer_stock'] ?? 0,
            ]);

            $variants = $this->normalizeVariants($data['variants'] ?? []);

            foreach ($variants as $sortOrder => $variant) {
                $product->variants()->create([
                    'sku' => $variant['sku'],
                    'barcode' => $variant['barcode'] ?? null,
                    'title' => $variant['title'] ?? null,
                    'variant_attributes' => $variant['variant_attributes'] ?? null,
                    'sort_order' => $variant['sort_order'] ?? $sortOrder,
                    'is_primary' => $variant['is_primary'] ?? $sortOrder === 0,
                    'status' => $variant['status'] ?? 'active',
                ]);
            }

            activity('products')
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->event('created')
                ->log('created');

            return $product->load('variants');
        });
    }

    /** @return list<array<string, mixed>> */
    private function normalizeVariants(array $variants): array
    {
        $normalized = array_values(array_filter(
            $variants,
            fn (mixed $variant): bool => is_array($variant) && filled($variant['sku'] ?? null),
        ));

        if ($normalized === []) {
            return [];
        }

        $primaryIndex = null;

        foreach ($normalized as $index => $variant) {
            if (! empty($variant['is_primary'])) {
                $primaryIndex = $index;

                break;
            }
        }

        $primaryIndex ??= 0;

        foreach ($normalized as $index => $variant) {
            $normalized[$index]['is_primary'] = $index === $primaryIndex;
        }

        return $normalized;
    }
}
