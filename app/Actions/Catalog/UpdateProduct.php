<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    public function __invoke(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product->update([
                'product_category_id' => array_key_exists('product_category_id', $data)
                    ? $data['product_category_id']
                    : $product->product_category_id,
                'name' => $data['name'] ?? $product->name,
                'description' => array_key_exists('description', $data)
                    ? $data['description']
                    : $product->description,
                'type' => $data['type'] ?? $product->type,
                'status' => $data['status'] ?? $product->status,
                'low_stock_threshold' => $data['low_stock_threshold'] ?? $product->low_stock_threshold,
                'buffer_stock' => $data['buffer_stock'] ?? $product->buffer_stock,
            ]);

            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            }

            activity('products')
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->event('updated')
                ->log('updated');

            return $product->load('variants');
        });
    }

    /** @param list<array<string, mixed>> $variants */
    private function syncVariants(Product $product, array $variants): void
    {
        $normalized = array_values(array_filter(
            $variants,
            fn (mixed $variant): bool => is_array($variant) && filled($variant['sku'] ?? null),
        ));

        $primaryIndex = null;

        foreach ($normalized as $index => $variant) {
            if (! empty($variant['is_primary'])) {
                $primaryIndex = $index;

                break;
            }
        }

        if ($primaryIndex === null && $product->variants()->where('is_primary', true)->exists() === false) {
            $primaryIndex = 0;
        }

        foreach ($normalized as $index => $variant) {
            $attributes = [
                'sku' => $variant['sku'],
                'barcode' => $variant['barcode'] ?? null,
                'title' => $variant['title'] ?? null,
                'variant_attributes' => $variant['variant_attributes'] ?? null,
                'sort_order' => $variant['sort_order'] ?? $index,
                'status' => $variant['status'] ?? 'active',
            ];

            if ($primaryIndex !== null) {
                $attributes['is_primary'] = $index === $primaryIndex;
            }

            if (filled($variant['id'] ?? null)) {
                $product->variants()
                    ->whereKey($variant['id'])
                    ->firstOrFail()
                    ->update($attributes);

                continue;
            }

            $product->variants()->create($attributes + ['is_primary' => $attributes['is_primary'] ?? false]);
        }
    }
}
