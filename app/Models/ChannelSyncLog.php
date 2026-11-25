<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'tenant_id',
    'channel_connection_id',
    'channel_listing_id',
    'product_variant_id',
    'inventory_outbox_event_id',
    'direction',
    'status',
    'event_type',
    'quantity',
    'attempts',
    'metadata',
    'error',
])]
class ChannelSyncLog extends Model
{
    public const DIRECTION_PUSH = 'push';

    public const DIRECTION_RECONCILE = 'reconcile';

    public const DIRECTION_MANUAL = 'manual';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_DISCREPANCY = 'discrepancy';

    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
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
}
