<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'warehouse_id', 'product_variant_id', 'on_hand', 'reserved', 'low_stock_threshold', 'buffer_stock'])]
class InventoryStock extends Model
{
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
            'buffer_stock' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function availableStock(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): int => (int) $attributes['on_hand'] - (int) $attributes['reserved']);
    }

    protected function channelAvailableStock(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): int => max(0, (int) $attributes['on_hand'] - (int) $attributes['reserved'] - (int) $attributes['buffer_stock']));
    }
}
