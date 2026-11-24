<?php

namespace App\Actions\Catalog;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateProductCategory
{
    public function __invoke(ProductCategory $category, array $data): ProductCategory
    {
        return DB::transaction(function () use ($category, $data): ProductCategory {
            $category->update([
                'name' => $data['name'] ?? $category->name,
                'slug' => $data['slug'] ?? Str::slug($data['name'] ?? $category->name),
                'description' => array_key_exists('description', $data) ? $data['description'] : $category->description,
                'is_active' => $data['is_active'] ?? $category->is_active,
            ]);

            activity('product-categories')
                ->performedOn($category)
                ->causedBy(auth()->user())
                ->event('updated')
                ->log('updated');

            return $category;
        });
    }
}
