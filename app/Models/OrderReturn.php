<?php

namespace App\Models;

use Database\Factories\OrderReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'order_id', 'order_item_id', 'quantity', 'condition', 'status', 'reason', 'received_at', 'classified_at', 'metadata'])]
class OrderReturn extends Model
{
    public const STATUS_REQUESTED = 'requested';

    public const STATUS_CLASSIFIED = 'classified';

    public const STATUS_COMPLETED = 'completed';

    public const CONDITION_SELLABLE = 'sellable';

    public const CONDITION_DAMAGED = 'damaged';

    /** @use HasFactory<OrderReturnFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'returns';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_at' => 'datetime',
            'classified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    /** @return HasMany<OrderReturnItem, $this> */
    public function returnItems(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class, 'return_id');
    }
}
