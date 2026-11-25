<?php

use App\Http\Controllers\Tenant\LoginController;
use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

function channelsTenantBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function channelsLoginUrl(Tenant $tenant, User $user): string
{
    return channelsTenantBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

/** @return array{Tenant, User} */
function channelsTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Channels Labs',
        'email' => $domain.'@example.com',
    ]);
    $tenant->domains()->create(['domain' => $domain]);

    $user = User::factory()->create([
        'name' => ucfirst($domain).' Owner',
        'tenant_id' => $tenant->getTenantKey(),
    ]);

    assignTenantAdminRole($user, $tenant);

    return [$tenant, $user];
}

function channelsShopifyConnection(Tenant $tenant, string $domain = 'demo-store.myshopify.com'): ChannelConnection
{
    return ChannelConnection::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Demo Store',
        'account_identifier' => $domain,
    ]);
}

function channelsGiveShopifyCredentials(Tenant $tenant, ChannelConnection $connection, string $token): void
{
    foreach ([
        'shop_domain' => $connection->account_identifier,
        'access_token' => $token,
        'location_id' => '9',
    ] as $key => $value) {
        ChannelCredential::create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'key' => $key,
            'encrypted_value' => Crypt::encryptString($value),
        ]);
    }
}

test('connecting a channel verifies credentials, encrypts them, and never exposes them', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/shop.json' => Http::response(['shop' => ['id' => 1]], 200),
    ]);

    [$tenant, $user] = channelsTenantWithAdmin('channels-connect');
    $baseUrl = channelsTenantBaseUrl($tenant);
    $token = 'shpat_plain_secret_token';

    $this->get(channelsLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/channels', [
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Demo Store',
        'credentials' => [
            'shop_domain' => 'demo-store.myshopify.com',
            'access_token' => $token,
            'location_id' => '9',
        ],
    ])->assertRedirect();

    $connection = ChannelConnection::withoutGlobalScopes()
        ->where('tenant_id', $tenant->getTenantKey())
        ->firstOrFail();
    $credential = ChannelCredential::withoutGlobalScopes()
        ->where('channel_connection_id', $connection->id)
        ->where('key', 'access_token')
        ->firstOrFail();

    expect($connection->status)->toBe(ChannelConnection::STATUS_ACTIVE)
        ->and($connection->activated_at)->not->toBeNull()
        ->and($credential->decrypt())->toBe($token)
        ->and($credential->encrypted_value)->not->toContain($token);

    $serialized = $credential->toJson().Activity::query()->get()->toJson();

    expect($serialized)->not->toContain($token);

    $this->get($baseUrl.'/channels')
        ->assertOk()
        ->assertDontSee($token);
});

test('invalid credentials do not activate a channel connection', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/shop.json' => Http::response(['errors' => 'Unauthorized'], 401),
    ]);

    [$tenant, $user] = channelsTenantWithAdmin('channels-invalid');
    $baseUrl = channelsTenantBaseUrl($tenant);

    $this->get(channelsLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/channels', [
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Demo Store',
        'credentials' => [
            'shop_domain' => 'demo-store.myshopify.com',
            'access_token' => 'bad-token',
            'location_id' => '9',
        ],
    ])->assertSessionHasErrors('credentials');

    expect(DB::table('channel_connections')->where('tenant_id', $tenant->getTenantKey())->count())->toBe(0)
        ->and(DB::table('channel_credentials')->where('tenant_id', $tenant->getTenantKey())->count())->toBe(0);
});

test('sync skips unmapped and paused listings and pushes buffered stock for mapped listings', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/products.json*' => Http::response([
            'products' => [
                ['id' => 1, 'title' => 'Mapped', 'status' => 'active', 'variants' => [['id' => 301, 'inventory_item_id' => 901, 'title' => 'Default']]],
                ['id' => 2, 'title' => 'Paused', 'status' => 'active', 'variants' => [['id' => 302, 'inventory_item_id' => 902, 'title' => 'Default']]],
                ['id' => 3, 'title' => 'Unmapped', 'status' => 'active', 'variants' => [['id' => 303, 'inventory_item_id' => 903, 'title' => 'Default']]],
            ],
        ], 200),
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json' => Http::response([], 200),
    ]);

    [$tenant, $user] = channelsTenantWithAdmin('channels-sync');
    $baseUrl = channelsTenantBaseUrl($tenant);
    $token = 'shpat_sync_secret';
    $tenantKey = $tenant->getTenantKey();
    $connection = channelsShopifyConnection($tenant);
    channelsGiveShopifyCredentials($tenant, $connection, $token);

    $warehouse = Warehouse::factory()->create(['tenant_id' => $tenantKey]);
    $product = Product::factory()->create(['tenant_id' => $tenantKey]);
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenantKey,
        'product_id' => $product->id,
    ]);
    InventoryStock::factory()->create([
        'tenant_id' => $tenantKey,
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'on_hand' => 30,
        'reserved' => 5,
        'buffer_stock' => 3,
    ]);

    $this->get(channelsLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/channels/'.$connection->id.'/import')->assertRedirect();

    expect(DB::table('channel_listings')->where('channel_connection_id', $connection->id)->count())->toBe(3);

    $mappedListingId = DB::table('channel_listings')
        ->where('channel_connection_id', $connection->id)
        ->where('external_id', '901')
        ->value('id');

    $this->put($baseUrl.'/channels/listings/'.$mappedListingId, [
        'product_variant_id' => $variant->id,
        'buffer_stock' => 2,
        'paused' => false,
    ])->assertRedirect();

    $pausedProduct = Product::factory()->create(['tenant_id' => $tenantKey]);
    $pausedVariant = ProductVariant::factory()->create([
        'tenant_id' => $tenantKey,
        'product_id' => $pausedProduct->id,
    ]);
    $pausedListingId = DB::table('channel_listings')
        ->where('channel_connection_id', $connection->id)
        ->where('external_id', '902')
        ->value('id');
    $pausedMapping = ChannelProductMapping::factory()->create([
        'tenant_id' => $tenantKey,
        'channel_connection_id' => $connection->id,
        'channel_listing_id' => $pausedListingId,
        'product_variant_id' => $pausedVariant->id,
    ]);
    ChannelInventorySetting::factory()->create([
        'tenant_id' => $tenantKey,
        'channel_product_mapping_id' => $pausedMapping->id,
        'paused' => true,
    ]);

    $this->post($baseUrl.'/channels/listings/sync')->assertRedirect();

    $pushRequests = collect(Http::recorded())
        ->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/inventory_levels/set.json'));

    expect($pushRequests)->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->header('X-Shopify-Access-Token') === [$token]
        && $request['inventory_item_id'] === '901'
        && $request['available'] === 20);

    expect($pushRequests->contains(fn (Request $request): bool => $request['inventory_item_id'] === '903'))->toBeFalse();

    $lastSyncedAt = DB::table('channel_connections')->where('id', $connection->id)->value('last_synced_at');
    expect($lastSyncedAt)->not->toBeNull()
        ->and(Activity::query()->get()->toJson())->not->toContain($token);

    $this->get($baseUrl.'/channels/listings')->assertOk()->assertDontSee($token);
});

test('one external listing cannot be mapped to an unrelated variant', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    [$tenant, $user] = channelsTenantWithAdmin('channels-mapping');
    $baseUrl = channelsTenantBaseUrl($tenant);
    $tenantKey = $tenant->getTenantKey();
    $connection = channelsShopifyConnection($tenant);
    $listing = ChannelListing::factory()->create([
        'tenant_id' => $tenantKey,
        'channel_connection_id' => $connection->id,
    ]);
    $originalProduct = Product::factory()->create(['tenant_id' => $tenantKey]);
    $originalVariant = ProductVariant::factory()->create([
        'tenant_id' => $tenantKey,
        'product_id' => $originalProduct->id,
    ]);
    $otherProduct = Product::factory()->create(['tenant_id' => $tenantKey]);
    $otherVariant = ProductVariant::factory()->create([
        'tenant_id' => $tenantKey,
        'product_id' => $otherProduct->id,
    ]);

    $mapping = ChannelProductMapping::factory()->create([
        'tenant_id' => $tenantKey,
        'channel_connection_id' => $connection->id,
        'channel_listing_id' => $listing->id,
        'product_variant_id' => $originalVariant->id,
    ]);
    ChannelInventorySetting::factory()->create([
        'tenant_id' => $tenantKey,
        'channel_product_mapping_id' => $mapping->id,
    ]);

    $this->get(channelsLoginUrl($tenant, $user))->assertRedirect();

    $this->put($baseUrl.'/channels/listings/'.$listing->id, [
        'product_variant_id' => $otherVariant->id,
        'buffer_stock' => 0,
        'paused' => false,
    ])->assertSessionHasErrors('product_variant_id');

    expect(DB::table('channel_product_mappings')->where('id', $mapping->id)->value('product_variant_id'))->toBe($originalVariant->id);
});

test('channel connections and listings are isolated by tenant', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    [$tenant, $user] = channelsTenantWithAdmin('channels-primary');
    $baseUrl = channelsTenantBaseUrl($tenant);
    $foreignTenant = Tenant::create([
        'name' => 'Foreign Channels Labs',
        'email' => 'foreign@example.com',
    ]);

    $foreignConnectionId = DB::table('channel_connections')->insertGetId([
        'tenant_id' => $foreignTenant->getTenantKey(),
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Foreign Store',
        'account_identifier' => 'foreign-store.myshopify.com',
        'status' => ChannelConnection::STATUS_ACTIVE,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get(channelsLoginUrl($tenant, $user))->assertRedirect();

    $this->get($baseUrl.'/channels/'.$foreignConnectionId.'/edit')->assertNotFound();
    $this->post($baseUrl.'/channels/'.$foreignConnectionId.'/import')->assertNotFound();
});
