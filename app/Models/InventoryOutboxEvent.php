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
    'event_type',
    'product_variant_id',
    'channel_connection_id',
    'channel_listing_id',
    'payload',
    'status',
    'attempts',
    'next_try_at',
    'last_error',
    'idempotency_key',
    'processed_at',
])]
class InventoryOutboxEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'next_try_at' => 'datetime',
            'processed_at' => 'datetime',
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

    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_RETRYING])
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('next_try_at')
                ->orWhere('next_try_at', '<=', now()));
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
