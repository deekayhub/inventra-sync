<?php

namespace App\Models;

use Database\Factories\ChannelProductMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'channel_connection_id', 'channel_listing_id', 'product_variant_id'])]
class ChannelProductMapping extends Model
{
    /** @use HasFactory<ChannelProductMappingFactory> */
    use BelongsToTenant, HasFactory;

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    /** @return BelongsTo<ChannelListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(ChannelListing::class, 'channel_listing_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasOne<ChannelInventorySetting, $this> */
    public function inventorySetting(): HasOne
    {
        return $this->hasOne(ChannelInventorySetting::class);
    }
}
