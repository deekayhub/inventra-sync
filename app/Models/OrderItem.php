<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'order_id', 'product_variant_id', 'inventory_reservation_id', 'external_item_id', 'sku_snapshot', 'title_snapshot', 'price_snapshot', 'quantity', 'reserved_quantity', 'fulfilled_quantity', 'cancelled_quantity', 'returned_quantity', 'metadata'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'fulfilled_quantity' => 'integer',
            'cancelled_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<InventoryReservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class, 'inventory_reservation_id');
    }

    /** @return HasMany<Fulfilment, $this> */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(Fulfilment::class);
    }

    /** @return HasMany<Cancellation, $this> */
    public function cancellations(): HasMany
    {
        return $this->hasMany(Cancellation::class);
    }

    /** @return HasMany<OrderReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class);
    }

    public function unfulfilledQuantity(): int
    {
        return max(0, $this->quantity - $this->fulfilled_quantity - $this->cancelled_quantity);
    }
}
