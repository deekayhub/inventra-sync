<?php

use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ChannelSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Database\Eloquent\Builder;

it('seeds fifty active demo tenants with domains and Admin owners', function () {
    $this->seed();

    expect(Tenant::query()->count())->toBe(TenantSeeder::TENANT_COUNT)
        ->and(Tenant::query()->active()->count())->toBe(TenantSeeder::TENANT_COUNT)
        ->and(User::query()->where('email', 'like', 'owner%@demo.test')->count())
        ->toBe(TenantSeeder::TENANT_COUNT)
        ->and(User::query()->where('email', 'admin@example.com')->firstOrFail()->is_super_admin)
        ->toBeTrue();

    $tenant = Tenant::query()
        ->whereHas('domains', function (Builder $query): void {
            $query->where('domain', 'demo-01');
        })
        ->firstOrFail();

    $owner = User::query()->where('email', 'owner01@demo.test')->firstOrFail();

    expect($tenant->domains()->pluck('domain')->all())->toContain('demo-01')
        ->and((string) $tenant->getTenantKey())->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
        ->and((string) $owner->tenant_id)->toBe($tenant->getTenantKey())
        ->and(usingTenantPermissionContext(
            $tenant,
            fn (): bool => $owner->fresh()->hasRole(PermissionSeeder::ADMIN_ROLE),
        ))->toBeTrue();

    $channels = $tenant->run(fn (): array => [
        'connections' => ChannelConnection::query()->count(),
        'listings' => ChannelListing::query()->count(),
        'mappings' => ChannelProductMapping::query()->count(),
        'paused' => ChannelInventorySetting::query()->where('paused', true)->count(),
        'credentials' => ChannelCredential::query()->pluck('encrypted_value')->implode('|'),
    ]);

    expect($channels['connections'])->toBe(2)
        ->and($channels['listings'])->toBe(4)
        ->and($channels['mappings'])->toBe(3)
        ->and($channels['paused'])->toBe(1)
        ->and($channels['credentials'])->not->toContain(ChannelSeeder::DUMMY_SHOPIFY_TOKEN)
        ->and($channels['credentials'])->not->toContain(ChannelSeeder::DUMMY_WOOCOMMERCE_SECRET);
});

it('does not duplicate demo tenants when run again', function () {
    (new TenantSeeder)->run();
    (new TenantSeeder)->run();

    expect(Tenant::query()->count())->toBe(TenantSeeder::TENANT_COUNT)
        ->and(User::query()->where('email', 'like', 'owner%@demo.test')->count())
        ->toBe(TenantSeeder::TENANT_COUNT);

    $tenant = Tenant::query()
        ->whereHas('domains', function (Builder $query): void {
            $query->where('domain', 'demo-01');
        })
        ->firstOrFail();

    expect($tenant->run(fn (): int => ChannelConnection::query()->count()))->toBe(2)
        ->and($tenant->run(fn (): int => ChannelListing::query()->count()))->toBe(4);
});
