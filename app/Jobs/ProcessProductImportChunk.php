<?php

namespace App\Jobs;

use App\Actions\Catalog\CreateProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessProductImportChunk implements ShouldQueue
{
    use Queueable;

    /** @param list<array<string, string>> $rows */
    public function __construct(
        private readonly array $rows,
        private readonly string $tenantId,
        private readonly int $userId,
    ) {}

    public function handle(CreateProduct $createProduct): void
    {
        tenancy()->initialize($this->tenantId);

        try {
            $user = User::query()->find($this->userId);

            if ($user !== null && (string) $user->tenant_id === $this->tenantId) {
                Auth::setUser($user);
            }

            foreach ($this->rows as $row) {
                if ($this->skuExists($row)) {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? ''));
                $sku = Str::upper(trim((string) ($row['sku'] ?? '')));

                if ($name === '' || $sku === '') {
                    continue;
                }

                $status = (string) ($row['status'] ?? Product::STATUS_DRAFT);

                $createProduct([
                    'name' => $name,
                    'description' => filled($row['description'] ?? null) ? (string) $row['description'] : null,
                    'product_category_id' => $this->categoryId($row),
                    'type' => Product::TYPE_SIMPLE,
                    'status' => in_array($status, [
                        Product::STATUS_DRAFT,
                        Product::STATUS_ACTIVE,
                        Product::STATUS_ARCHIVED,
                    ], true) ? $status : Product::STATUS_DRAFT,
                    'low_stock_threshold' => max(0, (int) ($row['low_stock_threshold'] ?? 5)),
                    'buffer_stock' => max(0, (int) ($row['buffer_stock'] ?? 0)),
                    'variants' => [[
                        'sku' => $sku,
                        'barcode' => filled($row['barcode'] ?? null) ? (string) $row['barcode'] : null,
                        'title' => filled($row['title'] ?? null) ? (string) $row['title'] : null,
                        'variant_attributes' => $this->variantAttributes($row),
                        'is_primary' => true,
                    ]],
                ]);
            }
        } finally {
            tenancy()->end();
        }
    }

    /** @param array<string, string> $row */
    private function skuExists(array $row): bool
    {
        $sku = Str::upper(trim((string) ($row['sku'] ?? '')));

        if ($sku === '') {
            return false;
        }

        return DB::table('product_variants')
            ->where('tenant_id', $this->tenantId)
            ->where('sku', $sku)
            ->exists();
    }

    /** @param array<string, string> $row */
    private function categoryId(array $row): ?int
    {
        $category = trim((string) ($row['category'] ?? ''));

        if ($category === '') {
            return null;
        }

        $categoryId = ProductCategory::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('slug', Str::slug($category))
                ->orWhere('name', $category))
            ->value('id');

        return $categoryId === null ? null : (int) $categoryId;
    }

    /** @param array<string, string> $row
     * @return array<string, mixed>|null
     */
    private function variantAttributes(array $row): ?array
    {
        $attributes = $row['variant_attributes'] ?? null;

        if (! filled($attributes)) {
            return null;
        }

        if (is_array($attributes)) {
            return $attributes;
        }

        $decoded = json_decode((string) $attributes, true);

        return is_array($decoded) ? $decoded : null;
    }
}
