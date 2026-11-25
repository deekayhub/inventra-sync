<?php

namespace App\Models;

use Database\Factories\ChannelInventorySettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'channel_product_mapping_id', 'buffer_stock', 'paused'])]
class ChannelInventorySetting extends Model
{
    /** @use HasFactory<ChannelInventorySettingFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'buffer_stock' => 'integer',
            'paused' => 'boolean',
        ];
    }

    /** @return BelongsTo<ChannelProductMapping, $this> */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ChannelProductMapping::class, 'channel_product_mapping_id');
    }
}
