<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ImportProductsRequest;
use App\Jobs\ExportProducts;
use App\Jobs\ImportProducts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportController extends Controller
{
    public function create(): View
    {
        return view('tenant.products.import');
    }

    public function store(ImportProductsRequest $request): RedirectResponse
    {
        $path = $request->file('file')->store('product-imports');

        ImportProducts::dispatch(
            $path,
            (string) tenant()->getTenantKey(),
            (int) auth()->id(),
        );

        return redirect()->route('products.index')
            ->with('status', __('Product import queued.'));
    }

    public function export(): RedirectResponse
    {
        $fileName = 'products-export-'.tenant()->getTenantKey().'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(6)).'.csv';

        ExportProducts::dispatch(
            (string) tenant()->getTenantKey(),
            (int) auth()->id(),
            $fileName,
        );

        return redirect()->route('products.index')
            ->with('status', __('Product export queued: :file', ['file' => $fileName]));
    }

    public function download(string $fileName): StreamedResponse
    {
        $tenantKey = (string) tenant()->getTenantKey();

        abort_unless(Str::startsWith($fileName, 'products-export-'.$tenantKey.'-'), 404);

        $path = 'product-exports/'.$fileName;

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $fileName);
    }
}
