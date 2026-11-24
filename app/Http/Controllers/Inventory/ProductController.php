<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Catalog\ArchiveProduct;
use App\Actions\Catalog\CreateProduct;
use App\Actions\Catalog\UpdateProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category', 'variants'])
            ->search($request->string('search')->toString())
            ->withStatus($request->string('status')->toString())
            ->withCategory($request->integer('category') ?: null)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('tenant.products.index', [
            'products' => $products,
            'categories' => ProductCategory::query()->orderBy('name')->get(),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'category' => $request->integer('category') ?: null,
            ],
        ]);
    }

    public function create(): View
    {
        return view('tenant.products.create', [
            'product' => null,
            'categories' => ProductCategory::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProduct $createProduct): RedirectResponse
    {
        $createProduct($request->validated());

        return redirect()->route('products.index')
            ->with('status', __('Product created.'));
    }

    public function edit(Product $product): View
    {
        return view('tenant.products.edit', [
            'product' => $product->load('variants'),
            'categories' => ProductCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $updateProduct): RedirectResponse
    {
        $updateProduct($product, $request->validated());

        return redirect()->route('products.index')
            ->with('status', __('Product updated.'));
    }

    public function archive(Product $product, ArchiveProduct $archiveProduct): RedirectResponse
    {
        $archiveProduct($product);

        return redirect()->route('products.index')
            ->with('status', __('Product archived.'));
    }
}
