<?php

namespace App\Models;

use Database\Factories\ChannelConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'provider', 'name', 'account_identifier', 'status', 'activated_at', 'last_synced_at'])]
class ChannelConnection extends Model
{
    /** @use HasFactory<ChannelConnectionFactory> */
    use BelongsToTenant, HasFactory;

    public const PROVIDER_SHOPIFY = 'shopify';

    public const PROVIDER_WOOCOMMERCE = 'woocommerce';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    /** @var list<string> */
    public const PROVIDERS = [self::PROVIDER_SHOPIFY, self::PROVIDER_WOOCOMMERCE];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    /** @return HasMany<ChannelCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(ChannelCredential::class);
    }

    /** @return HasMany<ChannelListing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(ChannelListing::class);
    }

    /** @return HasMany<ChannelProductMapping, $this> */
    public function mappings(): HasMany
    {
        return $this->hasMany(ChannelProductMapping::class);
    }

    public function latestSyncLog(): HasOne
    {
        return $this->hasOne(ChannelSyncLog::class)->latestOfMany();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** @return array<string, string> */
    public function decryptedCredentials(): array
    {
        return $this->credentials
            ->mapWithKeys(fn (ChannelCredential $credential): array => [$credential->key => $credential->decrypt()])
            ->all();
    }
}
