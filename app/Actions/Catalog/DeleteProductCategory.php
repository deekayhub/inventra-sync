<?php

namespace App\Actions\Catalog;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;

class DeleteProductCategory
{
    public function __invoke(ProductCategory $category): void
    {
        DB::transaction(function () use ($category): void {
            activity('product-categories')
                ->performedOn($category)
                ->causedBy(auth()->user())
                ->event('deleted')
                ->log('deleted');

            $category->delete();
        });
    }
}
