<?php

namespace App\Models;

use Database\Factories\InventoryReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'warehouse_id', 'product_variant_id', 'actor_id', 'external_reference', 'status', 'quantity', 'expires_at', 'released_at', 'committed_at', 'metadata'])]
class InventoryReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';

    public const STATUS_RELEASED = 'released';

    public const STATUS_COMMITTED = 'committed';

    public const STATUS_EXPIRED = 'expired';

    /** @use HasFactory<InventoryReservationFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'expires_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'metadata' => 'array',
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
