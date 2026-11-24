<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ExportProducts implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly int $userId,
        private readonly string $fileName,
    ) {}

    public function handle(): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $handle = fopen('php://temp', 'r+');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'sku',
                'name',
                'description',
                'category',
                'status',
                'barcode',
                'title',
                'variant_attributes',
                'low_stock_threshold',
                'buffer_stock',
            ]);

            Product::query()
                ->with(['category', 'variants'])
                ->orderBy('name')
                ->orderBy('id')
                ->chunk(100, function ($products) use ($handle): void {
                    foreach ($products as $product) {
                        foreach ($product->variants as $variant) {
                            fputcsv($handle, [
                                $variant->sku,
                                $product->name,
                                $product->description,
                                $product->category?->slug,
                                $product->status,
                                $variant->barcode,
                                $variant->title,
                                json_encode($variant->variant_attributes),
                                $product->low_stock_threshold,
                                $product->buffer_stock,
                            ]);
                        }
                    }
                });

            rewind($handle);
            Storage::disk('local')->put('product-exports/'.$this->fileName, stream_get_contents($handle));
            fclose($handle);

            activity('products')
                ->causedBy(User::query()->find($this->userId))
                ->event('exported')
                ->withProperties(['file' => $this->fileName])
                ->log('exported');
        } finally {
            tenancy()->end();
        }
    }
}
