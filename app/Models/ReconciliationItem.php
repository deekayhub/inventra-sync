<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'tenant_id',
    'reconciliation_run_id',
    'channel_connection_id',
    'channel_listing_id',
    'product_variant_id',
    'inventory_movement_id',
    'status',
    'expected_quantity',
    'remote_quantity',
    'difference',
    'correction_quantity',
    'error',
    'metadata',
    'corrected_at',
])]
class ReconciliationItem extends Model
{
    public const STATUS_MATCH = 'match';

    public const STATUS_DISCREPANCY = 'discrepancy';

    public const STATUS_CORRECTED = 'corrected';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'remote_quantity' => 'integer',
            'difference' => 'integer',
            'correction_quantity' => 'integer',
            'metadata' => 'array',
            'corrected_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'reconciliation_run_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'inventory_movement_id');
    }

    public function scopeDiscrepancies(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DISCREPANCY);
    }
}
