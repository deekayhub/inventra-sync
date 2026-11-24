<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'product_category_id', 'name', 'description', 'type', 'status', 'low_stock_threshold', 'buffer_stock'])]
class Product extends Model
{
    public const TYPE_SIMPLE = 'simple';

    public const TYPE_VARIABLE = 'variable';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'low_stock_threshold' => 'integer',
            'buffer_stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function stocks(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryStock::class, ProductVariant::class);
    }

    public function movements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, ProductVariant::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if ($search === null || trim($search) === '') {
            return $query;
        }

        $term = '%'.trim($search).'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhereHas('variants', fn (Builder $query): Builder => $query
                    ->where('sku', 'like', $term)
                    ->orWhere('barcode', 'like', $term));
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null || $status === '') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeWithCategory(Builder $query, ?int $categoryId): Builder
    {
        if ($categoryId === null) {
            return $query;
        }

        return $query->where('product_category_id', $categoryId);
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
