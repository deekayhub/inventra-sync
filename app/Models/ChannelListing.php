<?php

namespace App\Models;

use Database\Factories\ChannelListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'channel_connection_id', 'external_id', 'title', 'status', 'last_imported_at'])]
class ChannelListing extends Model
{
    /** @use HasFactory<ChannelListingFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'last_imported_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    /** @return HasOne<ChannelProductMapping, $this> */
    public function mapping(): HasOne
    {
        return $this->hasOne(ChannelProductMapping::class);
    }

    /** @return HasOneThrough<ChannelInventorySetting, ChannelProductMapping, $this> */
    public function inventorySetting(): HasOneThrough
    {
        return $this->hasOneThrough(
            ChannelInventorySetting::class,
            ChannelProductMapping::class,
            'channel_listing_id',
            'channel_product_mapping_id',
            'id',
            'id',
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
