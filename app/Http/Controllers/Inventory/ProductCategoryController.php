<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Catalog\CreateProductCategory;
use App\Actions\Catalog\DeleteProductCategory;
use App\Actions\Catalog\UpdateProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductCategoryRequest;
use App\Http\Requests\Inventory\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = ProductCategory::query()
            ->withCount('products')
            ->when($request->boolean('active'), fn (Builder $query): Builder => $query->active())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('tenant.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('tenant.categories.create', [
            'category' => null,
        ]);
    }

    public function store(StoreProductCategoryRequest $request, CreateProductCategory $createCategory): RedirectResponse
    {
        $createCategory($request->validated());

        return redirect()->route('categories.index')
            ->with('status', __('Category created.'));
    }

    public function edit(ProductCategory $category): View
    {
        return view('tenant.categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $category,
        UpdateProductCategory $updateCategory,
    ): RedirectResponse {
        $updateCategory($category, $request->validated());

        return redirect()->route('categories.index')
            ->with('status', __('Category updated.'));
    }

    public function destroy(ProductCategory $category, DeleteProductCategory $deleteCategory): RedirectResponse
    {
        $deleteCategory($category);

        return redirect()->route('categories.index')
            ->with('status', __('Category deleted.'));
    }
}
