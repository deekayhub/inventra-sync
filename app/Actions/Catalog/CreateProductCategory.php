<?php

namespace App\Actions\Catalog;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateProductCategory
{
    public function __invoke(array $data): ProductCategory
    {
        return DB::transaction(function () use ($data): ProductCategory {
            $category = ProductCategory::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            activity('product-categories')
                ->performedOn($category)
                ->causedBy(auth()->user())
                ->event('created')
                ->log('created');

            return $category;
        });
    }
}
