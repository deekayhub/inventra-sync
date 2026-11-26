<?php

use App\Actions\Channels\ProcessInventoryOutboxEvent;
use App\Http\Controllers\Tenant\LoginController;
use App\Jobs\DispatchInventoryOutbox;
use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\ChannelSyncLog;
use App\Models\InventoryOutboxEvent;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

function outboxTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Sync Labs',
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

function outboxBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function outboxLoginUrl(Tenant $tenant, User $user): string
{
    return outboxBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

function outboxShopifyConnection(Tenant $tenant, string $token = 'shpat_outbox_secret'): ChannelConnection
{
    $connection = ChannelConnection::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Outbox Store',
        'account_identifier' => 'demo-store.myshopify.com',
        'status' => ChannelConnection::STATUS_ACTIVE,
    ]);

    foreach ([
        'shop_domain' => 'demo-store.myshopify.com',
        'access_token' => $token,
        'location_id' => '9',
        'webhook_secret' => 'outbox-webhook-secret',
    ] as $key => $value) {
        ChannelCredential::create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'key' => $key,
            'encrypted_value' => Crypt::encryptString($value),
        ]);
    }

    return $connection;
}

function outboxMappedListing(
    Tenant $tenant,
    ChannelConnection $connection,
    ProductVariant $variant,
    int $bufferStock = 0,
    bool $paused = false,
): ChannelListing {
    $listing = ChannelListing::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'channel_connection_id' => $connection->id,
        'external_id' => '901',
        'status' => ChannelListing::STATUS_ACTIVE,
    ]);

    $mapping = ChannelProductMapping::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'channel_connection_id' => $connection->id,
        'channel_listing_id' => $listing->id,
        'product_variant_id' => $variant->id,
    ]);

    ChannelInventorySetting::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'channel_product_mapping_id' => $mapping->id,
        'buffer_stock' => $bufferStock,
        'paused' => $paused,
    ]);

    return $listing;
}

function outboxVariantWithStock(Tenant $tenant, int $onHand): array
{
    $warehouse = Warehouse::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'is_active' => true,
    ]);
    $product = Product::factory()->create(['tenant_id' => $tenant->getTenantKey()]);
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'product_id' => $product->id,
    ]);

    InventoryStock::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'on_hand' => $onHand,
        'reserved' => 0,
        'buffer_stock' => 0,
    ]);

    return [$warehouse, $variant];
}

test('stock adjustments enqueue an outbox event and push the buffered quantity', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json' => Http::response([], 200),
    ]);

    [$tenant, $user] = outboxTenantWithAdmin('outbox-adjustment');
    $connection = outboxShopifyConnection($tenant);
    [$warehouse, $variant] = outboxVariantWithStock($tenant, 20);
    $listing = outboxMappedListing($tenant, $connection, $variant, 2);

    $this->get(outboxLoginUrl($tenant, $user))->assertRedirect();

    $this->post(outboxBaseUrl($tenant).'/inventory/adjustments', [
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity' => 10,
        'reason' => 'Cycle count correction',
        'reference' => null,
        'idempotency_key' => 'outbox-adjustment-1',
    ])->assertRedirectContains('/inventory/stocks');

    tenancy()->initialize($tenant);

    try {
        $event = InventoryOutboxEvent::query()->firstOrFail();

        expect($event->status)->toBe(InventoryOutboxEvent::STATUS_SUCCESS)
            ->and($event->payload['quantity'])->toBe(28)
            ->and($event->attempts)->toBe(1);

        expect(ChannelSyncLog::query()->where('status', ChannelSyncLog::STATUS_SUCCESS)->count())->toBe(1);
    } finally {
        tenancy()->end();
    }

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_ends_with($request->url(), '/inventory_levels/set.json')
        && $request['inventory_item_id'] === $listing->external_id
        && $request['available'] === 28);
});

test('dispatcher skips an older event when a newer event already succeeded', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    [$tenant] = outboxTenantWithAdmin('outbox-stale');
    $connection = outboxShopifyConnection($tenant);
    [, $variant] = outboxVariantWithStock($tenant, 10);
    $listing = outboxMappedListing($tenant, $connection, $variant);

    tenancy()->initialize($tenant);

    try {
        $older = InventoryOutboxEvent::query()->create([
            'event_type' => 'adjusted',
            'product_variant_id' => $variant->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $listing->id,
            'payload' => [],
            'status' => InventoryOutboxEvent::STATUS_PENDING,
            'idempotency_key' => 'outbox-stale-older',
        ]);
        InventoryOutboxEvent::query()->create([
            'event_type' => 'adjusted',
            'product_variant_id' => $variant->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $listing->id,
            'payload' => [],
            'status' => InventoryOutboxEvent::STATUS_SUCCESS,
            'processed_at' => now(),
            'idempotency_key' => 'outbox-stale-newer',
        ]);

        app(ProcessInventoryOutboxEvent::class)($older);

        expect($older->refresh()->status)->toBe(InventoryOutboxEvent::STATUS_SKIPPED)
            ->and(ChannelSyncLog::query()->where('status', ChannelSyncLog::STATUS_SKIPPED)->count())->toBe(1);
    } finally {
        tenancy()->end();
    }

    Http::assertNothingSent();
});

test('dispatcher retries transient failures with backoff and then succeeds', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json' => Http::sequence()
            ->push([], 500)
            ->push([], 500)
            ->push([], 500)
            ->push([], 200),
    ]);

    [$tenant] = outboxTenantWithAdmin('outbox-retry');
    $connection = outboxShopifyConnection($tenant);
    [, $variant] = outboxVariantWithStock($tenant, 10);
    $listing = outboxMappedListing($tenant, $connection, $variant);

    tenancy()->initialize($tenant);

    try {
        $event = InventoryOutboxEvent::query()->create([
            'event_type' => 'adjusted',
            'product_variant_id' => $variant->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $listing->id,
            'payload' => [],
            'status' => InventoryOutboxEvent::STATUS_PENDING,
            'idempotency_key' => 'outbox-retry-1',
        ]);

        DispatchInventoryOutbox::dispatch($tenant->getTenantKey());

        expect($event->refresh()->status)->toBe(InventoryOutboxEvent::STATUS_RETRYING)
            ->and($event->attempts)->toBe(1)
            ->and($event->next_try_at?->greaterThan(now()))->toBeTrue();

        Carbon::setTestNow(now()->addMinute());

        DispatchInventoryOutbox::dispatch($tenant->getTenantKey());

        expect($event->refresh()->status)->toBe(InventoryOutboxEvent::STATUS_SUCCESS)
            ->and($event->attempts)->toBe(2);

        expect(ChannelSyncLog::query()
            ->whereIn('status', [ChannelSyncLog::STATUS_RETRYING, ChannelSyncLog::STATUS_SUCCESS])
            ->count())->toBe(2);
    } finally {
        Carbon::setTestNow();
        tenancy()->end();
    }

    Http::assertSentCount(4);
});

test('reconciliation logs a discrepancy without changing stock', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels.json*' => Http::response([
            'inventory_levels' => [[
                'inventory_item_id' => 901,
                'location_id' => 9,
                'available' => 7,
            ]],
        ], 200),
    ]);

    [$tenant] = outboxTenantWithAdmin('outbox-reconcile');
    $connection = outboxShopifyConnection($tenant);
    [, $variant] = outboxVariantWithStock($tenant, 10);
    outboxMappedListing($tenant, $connection, $variant);

    $this->artisan('inventory:reconcile-channels')->assertSuccessful();

    expect(InventoryStock::query()->firstOrFail()->on_hand)->toBe(10);

    $log = ChannelSyncLog::query()->where('status', ChannelSyncLog::STATUS_DISCREPANCY)->firstOrFail();

    expect($log->metadata['expected_quantity'])->toBe(10)
        ->and($log->metadata['remote_quantity'])->toBe(7);
});

test('failed sync report allows an admin to retry an event', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json' => Http::response([], 200),
    ]);

    [$tenant, $user] = outboxTenantWithAdmin('outbox-report');
    $connection = outboxShopifyConnection($tenant);
    [, $variant] = outboxVariantWithStock($tenant, 12);
    $listing = outboxMappedListing($tenant, $connection, $variant);

    tenancy()->initialize($tenant);

    try {
        $event = InventoryOutboxEvent::query()->create([
            'event_type' => 'adjusted',
            'product_variant_id' => $variant->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $listing->id,
            'payload' => [],
            'status' => InventoryOutboxEvent::STATUS_FAILED,
            'attempts' => 3,
            'last_error' => 'Temporary failure.',
            'idempotency_key' => 'outbox-report-1',
        ]);
    } finally {
        tenancy()->end();
    }

    $this->get(outboxLoginUrl($tenant, $user))->assertRedirect();

    $this->get(outboxBaseUrl($tenant).'/reports/sync-failures')
        ->assertOk()
        ->assertSee('Sync failures')
        ->assertSee($listing->title);

    $this->post(outboxBaseUrl($tenant).'/reports/sync-failures/'.$event->id.'/retry')->assertRedirect();

    expect($event->refresh()->status)->toBe(InventoryOutboxEvent::STATUS_SUCCESS)
        ->and($event->attempts)->toBe(1)
        ->and($event->last_error)->toBeNull();
});

test('queued jobs skip suspended tenants', function (): void {
    Http::preventStrayRequests();

    [$tenant] = outboxTenantWithAdmin('suspended-jobs');
    $connection = outboxShopifyConnection($tenant);
    [, $variant] = outboxVariantWithStock($tenant, 10);
    $listing = outboxMappedListing($tenant, $connection, $variant);

    tenancy()->initialize($tenant);

    try {
        $event = InventoryOutboxEvent::query()->create([
            'event_type' => 'adjusted',
            'product_variant_id' => $variant->id,
            'channel_connection_id' => $connection->id,
            'channel_listing_id' => $listing->id,
            'payload' => [],
            'status' => InventoryOutboxEvent::STATUS_PENDING,
            'idempotency_key' => 'suspended-jobs-1',
        ]);
    } finally {
        tenancy()->end();
    }

    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    (new DispatchInventoryOutbox((string) $tenant->getTenantKey()))
        ->handle(app(ProcessInventoryOutboxEvent::class));

    expect($event->fresh()->status)->toBe(InventoryOutboxEvent::STATUS_PENDING);
});
