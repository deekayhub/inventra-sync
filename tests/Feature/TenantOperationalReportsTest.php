<?php

use App\Actions\Channels\RunChannelReconciliation;
use App\Events\InventoryAdjusted;
use App\Events\InventoryChanged;
use App\Http\Controllers\Tenant\LoginController;
use App\Jobs\DispatchChannelReconciliation;
use App\Jobs\ExportReport;
use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\ChannelInventorySetting;
use App\Models\ChannelListing;
use App\Models\ChannelProductMapping;
use App\Models\ChannelSyncLog;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function reportsWithinTenant(Tenant $tenant, callable $callback): mixed
{
    tenancy()->initialize($tenant);

    try {
        return $callback();
    } finally {
        tenancy()->end();
    }
}

function reportsTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Reports Labs',
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

function reportsTenantWithUser(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Reports Labs',
        'email' => $domain.'@example.com',
    ]);
    $tenant->domains()->create(['domain' => $domain]);

    $user = User::factory()->create([
        'name' => ucfirst($domain).' Viewer',
        'tenant_id' => $tenant->getTenantKey(),
    ]);

    return [$tenant, $user];
}

function reportsBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function reportsLoginUrl(Tenant $tenant, User $user): string
{
    return reportsBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

function reportsStockFixture(
    Tenant $tenant,
    string $sku,
    int $onHand,
    int $reserved = 0,
    int $threshold = 2,
    int $bufferStock = 0,
): array {
    return reportsWithinTenant($tenant, function () use ($tenant, $sku, $onHand, $reserved, $threshold, $bufferStock): array {
        $warehouse = Warehouse::query()->where('code', 'default')->firstOrFail();
        $product = Product::factory()->create(['name' => 'Report product '.$sku]);
        $variant = ProductVariant::factory()->for($product)->create([
            'sku' => $sku,
            'is_primary' => true,
        ]);
        $stock = InventoryStock::factory()
            ->for($warehouse)
            ->for($variant, 'variant')
            ->create([
                'tenant_id' => $tenant->getTenantKey(),
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'low_stock_threshold' => $threshold,
                'buffer_stock' => $bufferStock,
            ]);

        return [$warehouse, $product, $variant, $stock];
    });
}

function reportsConnectionFixture(
    Tenant $tenant,
    string $name = 'Report Store',
    bool $withCredentials = true,
    string $domain = 'demo-store.myshopify.com',
): ChannelConnection {
    return reportsWithinTenant($tenant, function () use ($tenant, $name, $withCredentials, $domain): ChannelConnection {
        $connection = ChannelConnection::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'provider' => ChannelConnection::PROVIDER_SHOPIFY,
            'name' => $name,
            'account_identifier' => $domain,
            'status' => ChannelConnection::STATUS_ACTIVE,
            'activated_at' => now(),
            'last_synced_at' => now()->subHour(),
        ]);

        if ($withCredentials) {
            foreach ([
                'shop_domain' => $domain,
                'access_token' => 'shpat_reports_secret',
                'location_id' => '1',
                'webhook_secret' => 'reports-webhook-secret',
            ] as $key => $value) {
                ChannelCredential::create([
                    'tenant_id' => $tenant->getTenantKey(),
                    'channel_connection_id' => $connection->id,
                    'key' => $key,
                    'encrypted_value' => Crypt::encryptString($value),
                ]);
            }
        }

        return $connection;
    });
}

function reportsMappedListingFixture(
    Tenant $tenant,
    ChannelConnection $connection,
    ProductVariant $variant,
    string $externalId = '901',
): void {
    reportsWithinTenant($tenant, function () use ($tenant, $connection, $variant, $externalId): void {
        $listing = ChannelListing::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'external_id' => $externalId,
            'title' => 'Report listing '.$externalId,
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
            'buffer_stock' => 0,
            'paused' => false,
        ]);
    });
}

function reportsMovementFixture(
    Tenant $tenant,
    Warehouse $warehouse,
    ProductVariant $variant,
    int $quantity,
    string $reason,
): InventoryMovement {
    return reportsWithinTenant($tenant, function () use ($tenant, $warehouse, $variant, $quantity, $reason): InventoryMovement {
        return InventoryMovement::factory()
            ->for($warehouse)
            ->for($variant, 'variant')
            ->create([
                'tenant_id' => $tenant->getTenantKey(),
                'movement_type' => 'adjustment',
                'quantity_change' => $quantity,
                'quantity_after' => max(0, $quantity),
                'reserved_after' => 0,
                'reason' => $reason,
                'source' => 'manual',
            ]);
    });
}

function reportsOrderFixture(
    Tenant $tenant,
    ChannelConnection $connection,
    string $status,
    float $total,
    ?Carbon $orderedAt = null,
): Order {
    return reportsWithinTenant($tenant, function () use ($tenant, $connection, $status, $total, $orderedAt): Order {
        return Order::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'status' => $status,
            'total_amount' => $total,
            'ordered_at' => $orderedAt ?? now(),
        ]);
    });
}

function reportsReconciliationFixture(
    Tenant $tenant,
    ChannelConnection $connection,
    ProductVariant $variant,
    array $itemAttributes = [],
): array {
    return reportsWithinTenant($tenant, function () use ($tenant, $connection, $variant, $itemAttributes): array {
        $run = ReconciliationRun::factory()->create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'status' => ReconciliationRun::STATUS_COMPLETED,
            'items_count' => 1,
            'discrepancies_count' => 1,
            'corrections_count' => 0,
            'failures_count' => 0,
        ]);

        $item = ReconciliationItem::factory()->create(array_merge([
            'tenant_id' => $tenant->getTenantKey(),
            'reconciliation_run_id' => $run->id,
            'channel_connection_id' => $connection->id,
            'product_variant_id' => $variant->id,
            'status' => ReconciliationItem::STATUS_DISCREPANCY,
            'expected_quantity' => 10,
            'remote_quantity' => 7,
            'difference' => -3,
            'correction_quantity' => -3,
        ], $itemAttributes));

        return [$run, $item];
    });
}

test('renders operational report pages with tenant scoped data', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-render');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    [$warehouse, , $variant] = reportsStockFixture($tenant, 'RENDER-SKU', 10);
    reportsMovementFixture($tenant, $warehouse, $variant, 5, 'Receipt for render report');
    $connection = reportsConnectionFixture($tenant);
    reportsMappedListingFixture($tenant, $connection, $variant);
    reportsOrderFixture($tenant, $connection, Order::STATUS_FULFILLED, 75.00);
    reportsReconciliationFixture($tenant, $connection, $variant);

    foreach ([
        '/reports/inventory/current' => 'RENDER-SKU',
        '/reports/inventory/movements' => 'Receipt for render report',
        '/reports/orders-by-channel' => 'Report Store',
        '/reports/channels' => 'Report Store',
        '/reports/reconciliation' => 'RENDER-SKU',
    ] as $path => $expected) {
        $this->get(reportsBaseUrl($tenant).$path)
            ->assertOk()
            ->assertSee($expected);
    }
});

test('filters low and out of stock reports and reports ledger totals', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-stock');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    [$warehouse, , $lowVariant] = reportsStockFixture($tenant, 'LOW-SKU', 5, 2, 4);
    [, , $outVariant] = reportsStockFixture($tenant, 'OUT-SKU', 1, 2, 4);
    [, , $healthyVariant] = reportsStockFixture($tenant, 'HEALTHY-SKU', 20, 0, 2);
    reportsMovementFixture($tenant, $warehouse, $lowVariant, 5, 'Receipt alpha');
    reportsMovementFixture($tenant, $warehouse, $outVariant, -2, 'Usage beta');
    reportsMovementFixture($tenant, $warehouse, $healthyVariant, 9, 'Ignored movement');

    $baseUrl = reportsBaseUrl($tenant);

    $this->get($baseUrl.'/reports/inventory/low-stock')
        ->assertOk()
        ->assertSee('LOW-SKU')
        ->assertDontSee('OUT-SKU')
        ->assertDontSee('HEALTHY-SKU')
        ->assertViewHas('summary', fn ($summary): bool => (int) $summary->stock_rows === 1);

    $this->get($baseUrl.'/reports/inventory/out-of-stock')
        ->assertOk()
        ->assertSee('OUT-SKU')
        ->assertDontSee('LOW-SKU')
        ->assertDontSee('HEALTHY-SKU')
        ->assertViewHas('summary', fn ($summary): bool => (int) $summary->stock_rows === 1);

    $this->get($baseUrl.'/reports/inventory/movements?movement_type=adjustment&search=alpha')
        ->assertOk()
        ->assertViewHas('summary', fn ($summary): bool => (int) $summary->stock_in === 5
            && (int) $summary->stock_out === 0
            && (int) $summary->net_change === 5
            && (int) $summary->movement_count === 1);
});

test('aggregates orders by channel and filters by status', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-orders');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    $firstConnection = reportsConnectionFixture($tenant, 'First Store');
    $secondConnection = reportsConnectionFixture($tenant, 'Second Store', true, 'second-store.myshopify.com');
    reportsOrderFixture($tenant, $firstConnection, Order::STATUS_FULFILLED, 100.00);
    reportsOrderFixture($tenant, $firstConnection, Order::STATUS_PENDING, 50.00);
    reportsOrderFixture($tenant, $secondConnection, Order::STATUS_FULFILLED, 25.00);
    reportsOrderFixture($tenant, $secondConnection, Order::STATUS_FULFILLED, 999.00, now()->subMonth());

    $this->get(reportsBaseUrl($tenant).'/reports/orders-by-channel?status=fulfilled&from='.now()->subDay()->toDateString())
        ->assertOk()
        ->assertSee('First Store')
        ->assertSee('Second Store')
        ->assertViewHas('summary', fn ($summary): bool => (int) $summary->orders_count === 2
            && (float) $summary->revenue === 125.0
            && (int) $summary->fulfilled_count === 2)
        ->assertViewHas('channels', fn ($channels): bool => $channels->total() === 2);
});

test('channel status report keeps tenant scoped search', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-visible');
    [$otherTenant] = reportsTenantWithAdmin('reports-hidden');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    reportsConnectionFixture($tenant, 'Visible Channel');
    reportsConnectionFixture($otherTenant, 'Secret Channel');

    $this->get(reportsBaseUrl($tenant).'/reports/channels?search=Secret')
        ->assertOk()
        ->assertDontSee('Secret Channel')
        ->assertViewHas('connections', fn ($connections): bool => $connections->total() === 0);
});

test('queues reconciliation and export jobs from report actions', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-queue');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    $connection = reportsConnectionFixture($tenant);
    Queue::fake([DispatchChannelReconciliation::class, ExportReport::class]);

    $this->post(reportsBaseUrl($tenant).'/reports/reconciliation/run', [
        'channel_connection_id' => $connection->id,
    ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Reconciliation queued.');

    Queue::assertPushedTimes(DispatchChannelReconciliation::class, 1);

    $this->post(reportsBaseUrl($tenant).'/reports/reconciliation/run', [
        'channel_connection_id' => 999999,
    ])->assertNotFound();

    Queue::assertPushedTimes(DispatchChannelReconciliation::class, 1);

    $this->get(reportsBaseUrl($tenant).'/reports/current-inventory/export?warehouse_id=1')
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status): bool => str_starts_with($status, 'Report export queued: report-export-'));

    Queue::assertPushedTimes(ExportReport::class, 1);
});

test('corrects a discrepancy through the ledger and blocks cross tenant access', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-correct');
    [$otherTenant, $otherUser] = reportsTenantWithAdmin('reports-other');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    [$warehouse, , $variant, $stock] = reportsStockFixture($tenant, 'CORRECT-SKU', 10);
    $connection = reportsConnectionFixture($tenant);
    [$run, $item] = reportsReconciliationFixture($tenant, $connection, $variant);

    Event::fake([InventoryAdjusted::class, InventoryChanged::class]);

    $this->post(reportsBaseUrl($tenant).'/reports/reconciliation/'.$item->id.'/correct', [
        'warehouse_id' => $warehouse->id,
        'reason' => 'Cycle count confirmed remote quantity',
    ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Ledger adjustment recorded for this discrepancy.');

    expect($stock->refresh()->on_hand)->toBe(7);

    $this->assertDatabaseHas('inventory_movements', [
        'tenant_id' => $tenant->getTenantKey(),
        'product_variant_id' => $variant->id,
        'movement_type' => 'adjustment',
        'quantity_change' => -3,
        'reason' => 'Cycle count confirmed remote quantity',
        'idempotency_key' => 'reconciliation-correction:'.$item->id,
        'source' => 'reconciliation',
    ]);
    $this->assertDatabaseHas('reconciliation_items', [
        'id' => $item->id,
        'status' => ReconciliationItem::STATUS_CORRECTED,
        'inventory_movement_id' => InventoryMovement::query()->where('idempotency_key', 'reconciliation-correction:'.$item->id)->value('id'),
    ]);
    expect($run->refresh()->corrections_count)->toBe(1);

    Event::assertDispatched(InventoryAdjusted::class);
    Event::assertDispatched(InventoryChanged::class);

    $this->get(reportsLoginUrl($otherTenant, $otherUser))->assertRedirect();

    $this->post(reportsBaseUrl($otherTenant).'/reports/reconciliation/'.$item->id.'/correct', [
        'warehouse_id' => $warehouse->id,
        'reason' => 'Cross tenant attempt',
    ])->assertNotFound();

    expect($stock->refresh()->on_hand)->toBe(7);
});

test('requires a reason when correcting a discrepancy', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-validation');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    [$warehouse, , $variant] = reportsStockFixture($tenant, 'VALIDATION-SKU', 10);
    $connection = reportsConnectionFixture($tenant);
    [, $item] = reportsReconciliationFixture($tenant, $connection, $variant);

    $this->post(reportsBaseUrl($tenant).'/reports/reconciliation/'.$item->id.'/correct', [
        'warehouse_id' => $warehouse->id,
        'reason' => '',
    ])->assertSessionHasErrors(['reason' => 'The reason field is required.']);

    expect($item->refresh()->status)->toBe(ReconciliationItem::STATUS_DISCREPANCY);

    $this->assertDatabaseCount('inventory_movements', 0);
});

test('logs reconciliation discrepancies without mutating stock', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/*/inventory_levels.json*' => Http::response([
            'inventory_levels' => [
                ['inventory_item_id' => '901', 'available' => 7],
            ],
        ]),
    ]);

    [$tenant] = reportsTenantWithAdmin('reports-reconcile');
    [, , $variant, $stock] = reportsStockFixture($tenant, 'RECONCILE-SKU', 10);
    $connection = reportsConnectionFixture($tenant);
    reportsMappedListingFixture($tenant, $connection, $variant, '901');

    $run = reportsWithinTenant($tenant, fn (): ReconciliationRun => app(RunChannelReconciliation::class)($connection));

    expect($run->status)->toBe(ReconciliationRun::STATUS_COMPLETED)
        ->and($run->items_count)->toBe(1)
        ->and($run->discrepancies_count)->toBe(1)
        ->and($run->failures_count)->toBe(0)
        ->and($stock->refresh()->on_hand)->toBe(10);

    $this->assertDatabaseHas('reconciliation_items', [
        'reconciliation_run_id' => $run->id,
        'status' => ReconciliationItem::STATUS_DISCREPANCY,
        'expected_quantity' => 10,
        'remote_quantity' => 7,
        'difference' => -3,
        'metadata->expected_quantity' => 10,
        'metadata->remote_quantity' => 7,
    ]);
    expect(ChannelSyncLog::query()->where('channel_connection_id', $connection->id)->where('status', ChannelSyncLog::STATUS_DISCREPANCY)->exists())->toBeTrue();
});

test('counts failed reconciliation items in run totals', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/*/inventory_levels.json*' => Http::response([], 500),
    ]);
    config(['channels.http.retry_times' => 0]);

    [$tenant] = reportsTenantWithAdmin('reports-failure');
    [, , $variant] = reportsStockFixture($tenant, 'FAIL-SKU', 10);
    $connection = reportsConnectionFixture($tenant);
    reportsMappedListingFixture($tenant, $connection, $variant, '902');

    $run = reportsWithinTenant($tenant, fn (): ReconciliationRun => app(RunChannelReconciliation::class)($connection));

    expect($run->status)->toBe(ReconciliationRun::STATUS_FAILED)
        ->and($run->items_count)->toBe(1)
        ->and($run->failures_count)->toBe(1);

    $this->assertDatabaseHas('reconciliation_items', [
        'reconciliation_run_id' => $run->id,
        'status' => ReconciliationItem::STATUS_FAILED,
    ]);
});

test('exports a report to storage and protects downloads by tenant', function () {
    [$tenant, $user] = reportsTenantWithAdmin('reports-export');
    [$otherTenant, $otherUser] = reportsTenantWithAdmin('reports-download');
    [, , , $stock] = reportsStockFixture($tenant, 'EXPORT-SKU', 12, 2, 3);
    config([
        'tenancy.filesystem.disks' => [],
        'tenancy.filesystem.suffix_storage_path' => false,
    ]);
    Storage::fake('local');

    $fileName = 'report-export-'.$tenant->getTenantKey().'-current-inventory-test.csv';

    (new ExportReport(
        (string) $tenant->getTenantKey(),
        (int) $user->id,
        'current-inventory',
        $fileName,
        ['warehouse_id' => (string) $stock->warehouse_id],
    ))->handle();

    $path = 'report-exports/'.$fileName;

    reportsWithinTenant($tenant, function () use ($path): void {
        expect(Storage::disk('local')->exists($path))->toBeTrue()
            ->and(Storage::disk('local')->get($path))->toContain('EXPORT-SKU');
    });

    expect(Activity::query()->inLog('reports')->where('event', 'exported')->where('properties->file', $fileName)->exists())->toBeTrue();

    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    $response = $this->get(reportsBaseUrl($tenant).'/reports/exports/'.$fileName)->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain($fileName);

    $this->get(reportsLoginUrl($otherTenant, $otherUser))->assertRedirect();

    $this->get(reportsBaseUrl($otherTenant).'/reports/exports/'.$fileName)->assertNotFound();
});

test('forbids report access without the reports permission', function () {
    [$tenant, $user] = reportsTenantWithUser('reports-forbidden');
    $this->get(reportsLoginUrl($tenant, $user))->assertRedirect();

    $this->get(reportsBaseUrl($tenant).'/reports/inventory/current')->assertForbidden();
});
