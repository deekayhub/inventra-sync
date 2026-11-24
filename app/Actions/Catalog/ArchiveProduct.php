<?php

namespace App\Actions\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ArchiveProduct
{
    public function __invoke(Product $product): Product
    {
        return DB::transaction(function () use ($product): Product {
            $product->update(['status' => Product::STATUS_ARCHIVED]);

            activity('products')
                ->performedOn($product)
                ->causedBy(auth()->user())
                ->event('archived')
                ->log('archived');

            return $product;
        });
    }
}
