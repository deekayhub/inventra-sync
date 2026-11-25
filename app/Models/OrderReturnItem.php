<?php

namespace App\Models;

use Database\Factories\OrderReturnItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'return_id', 'order_item_id', 'quantity', 'condition', 'disposition', 'metadata'])]
class OrderReturnItem extends Model
{
    /** @use HasFactory<OrderReturnItemFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'return_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<OrderReturn, $this> */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }
}
