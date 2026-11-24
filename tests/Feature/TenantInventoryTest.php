<?php

use App\Events\InventoryAdjusted;
use App\Events\InventoryChanged;
use App\Http\Controllers\Tenant\LoginController;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

function inventoryTenantBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function inventoryLoginUrl(Tenant $tenant, User $user): string
{
    return inventoryTenantBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

function inventoryTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Inventory Labs',
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

function inventoryTenantWithUser(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Inventory Labs',
        'email' => $domain.'@example.com',
    ]);
    $tenant->domains()->create(['domain' => $domain]);

    $user = User::factory()->create([
        'name' => ucfirst($domain).' Viewer',
        'tenant_id' => $tenant->getTenantKey(),
    ]);

    return [$tenant, $user];
}

function inventoryFixture(
    Tenant $tenant,
    User $user,
    string $sku,
    int $onHand = 10,
    int $reserved = 0,
    int $bufferStock = 0,
): array {
    tenancy()->initialize($tenant);
    Auth::setUser($user);

    $warehouse = Warehouse::query()->where('code', 'default')->firstOrFail();
    $product = Product::factory()->create(['buffer_stock' => $bufferStock]);
    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => $sku,
        'is_primary' => true,
    ]);
    $stock = InventoryStock::factory()
        ->for($warehouse)
        ->for($variant, 'variant')
        ->create([
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'low_stock_threshold' => 2,
            'buffer_stock' => $bufferStock,
        ]);

    tenancy()->end();

    return [$warehouse, $variant, $stock];
}

function inventoryAdjustmentPayload(
    Warehouse $warehouse,
    ProductVariant $variant,
    int $quantity,
    string $idempotencyKey,
    ?string $reference = null,
): array {
    return [
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'reason' => 'Cycle count correction',
        'reference' => $reference,
        'idempotency_key' => $idempotencyKey,
    ];
}

function inventoryReservationPayload(
    Warehouse $warehouse,
    ProductVariant $variant,
    int $quantity,
    string $externalReference,
): array {
    return [
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'external_reference' => $externalReference,
    ];
}

it('records a ledger entry and dispatches events for a stock adjustment', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('ledger');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'LEDGER-1', 10);

    Event::fake([InventoryAdjusted::class, InventoryChanged::class]);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();

    $this->post(
        inventoryTenantBaseUrl($tenant).'/inventory/adjustments',
        inventoryAdjustmentPayload($warehouse, $variant, 8, 'receipt-ledger', 'PO-100'),
    )->assertRedirectContains('/inventory/stocks');

    $stock->refresh();

    expect($stock->on_hand)->toBe(18)
        ->and($stock->available_stock)->toBe(18)
        ->and($stock->channel_available_stock)->toBe(18);

    $this->assertDatabaseHas('inventory_movements', [
        'tenant_id' => $tenant->getTenantKey(),
        'warehouse_id' => $warehouse->id,
        'product_variant_id' => $variant->id,
        'movement_type' => 'adjustment',
        'quantity_change' => 8,
        'quantity_after' => 18,
        'reserved_after' => 0,
        'reference' => 'PO-100',
        'idempotency_key' => 'receipt-ledger',
    ]);

    Event::assertDispatched(InventoryAdjusted::class);
    Event::assertDispatched(InventoryChanged::class);

    expect(Activity::query()
        ->inLog('inventory')
        ->where('event', 'adjusted')
        ->where('subject_id', $stock->id)
        ->exists())->toBeTrue();
});

it('applies an adjustment only once for a repeated idempotency key', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('idempotent');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'IDEMPOTENT-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();

    $payload = inventoryAdjustmentPayload($warehouse, $variant, 5, 'receipt-idempotent');

    $this->post(inventoryTenantBaseUrl($tenant).'/inventory/adjustments', $payload)
        ->assertRedirectContains('/inventory/stocks');
    $this->post(inventoryTenantBaseUrl($tenant).'/inventory/adjustments', $payload)
        ->assertRedirectContains('/inventory/stocks');

    expect($stock->refresh()->on_hand)->toBe(15);

    $this->assertDatabaseCount('inventory_movements', 1);
});

it('rejects an adjustment that would make on-hand stock negative', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('negative');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'NEGATIVE-1', 5);

    Event::fake([InventoryAdjusted::class, InventoryChanged::class]);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();

    $this->post(
        inventoryTenantBaseUrl($tenant).'/inventory/adjustments',
        inventoryAdjustmentPayload($warehouse, $variant, -10, 'receipt-negative'),
    )->assertSessionHasErrors([
        'quantity' => 'The adjustment would make on-hand stock negative.',
    ]);

    expect($stock->refresh()->on_hand)->toBe(5);

    $this->assertDatabaseCount('inventory_movements', 0);

    Event::assertNotDispatched(InventoryAdjusted::class);
    Event::assertNotDispatched(InventoryChanged::class);
});

it('prevents a second reservation from consuming unavailable stock', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('oversell');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'OVERSELL-1', 10, 0, 2);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 6, 'order-oversell-1'),
    )->assertRedirectContains('/inventory/reservations');

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 6, 'order-oversell-2'),
    )->assertSessionHasErrors([
        'quantity' => 'There is not enough available stock to reserve.',
    ]);

    $stock->refresh();

    expect($stock->reserved)->toBe(6)
        ->and($stock->available_stock)->toBe(4)
        ->and($stock->channel_available_stock)->toBe(2);

    $this->assertDatabaseCount('inventory_reservations', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
    $this->assertDatabaseHas('inventory_movements', [
        'tenant_id' => $tenant->getTenantKey(),
        'movement_type' => 'reservation',
        'quantity_change' => 0,
        'reserved_after' => 6,
        'reference' => 'order-oversell-1',
        'idempotency_key' => 'reservation:order-oversell-1',
    ]);
});

it('reuses an existing reservation for a repeated external reference', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('reservation-idempotent');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'RESERVATION-IDEMPOTENT-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 3, 'order-idempotent'),
    )->assertRedirectContains('/inventory/reservations');

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 4, 'order-idempotent'),
    )->assertRedirectContains('/inventory/reservations');

    expect($stock->refresh()->reserved)->toBe(3);

    $this->assertDatabaseCount('inventory_reservations', 1);
    $this->assertDatabaseCount('inventory_movements', 1);
    $this->assertDatabaseHas('inventory_reservations', [
        'tenant_id' => $tenant->getTenantKey(),
        'external_reference' => 'order-idempotent',
        'status' => 'reserved',
        'quantity' => 3,
    ]);
});

it('commits a reservation and reduces on-hand and reserved stock', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('commit');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'COMMIT-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 4, 'order-commit'),
    )->assertRedirectContains('/inventory/reservations');

    $reservation = DB::table('inventory_reservations')
        ->where('external_reference', 'order-commit')
        ->firstOrFail();

    $this->post($baseUrl.'/inventory/reservations/'.$reservation->id.'/commit')
        ->assertRedirectContains('/inventory/reservations');

    expect($stock->refresh()->on_hand)->toBe(6)
        ->and($stock->reserved)->toBe(0);

    $this->assertDatabaseHas('inventory_reservations', [
        'id' => $reservation->id,
        'status' => 'committed',
    ]);
    $this->assertDatabaseHas('inventory_movements', [
        'movement_type' => 'commitment',
        'quantity_change' => -4,
        'quantity_after' => 6,
        'reserved_after' => 0,
        'reference' => 'order-commit',
        'idempotency_key' => 'reservation_commit:order-commit',
    ]);
});

it('releases a reservation and restores available stock', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('release');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'RELEASE-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 4, 'order-release'),
    )->assertRedirectContains('/inventory/reservations');

    $reservation = DB::table('inventory_reservations')
        ->where('external_reference', 'order-release')
        ->firstOrFail();

    $this->post($baseUrl.'/inventory/reservations/'.$reservation->id.'/release')
        ->assertRedirectContains('/inventory/reservations');

    expect($stock->refresh()->on_hand)->toBe(10)
        ->and($stock->reserved)->toBe(0)
        ->and($stock->available_stock)->toBe(10);

    $this->assertDatabaseHas('inventory_reservations', [
        'id' => $reservation->id,
        'status' => 'released',
    ]);
    $this->assertDatabaseHas('inventory_movements', [
        'movement_type' => 'reservation_release',
        'quantity_change' => 0,
        'reserved_after' => 0,
        'reference' => 'order-release',
        'idempotency_key' => 'reservation_release:order-release',
    ]);
});

it('expires a reservation and records the expiry in the ledger', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('expire');
    [$warehouse, $variant, $stock] = inventoryFixture($tenant, $user, 'EXPIRE-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->post(
        $baseUrl.'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 4, 'order-expire'),
    )->assertRedirectContains('/inventory/reservations');

    $reservation = DB::table('inventory_reservations')
        ->where('external_reference', 'order-expire')
        ->firstOrFail();

    $this->post($baseUrl.'/inventory/reservations/'.$reservation->id.'/expire')
        ->assertRedirectContains('/inventory/reservations');

    expect($stock->refresh()->on_hand)->toBe(10)
        ->and($stock->reserved)->toBe(0);

    $this->assertDatabaseHas('inventory_reservations', [
        'id' => $reservation->id,
        'status' => 'expired',
    ]);
    $this->assertDatabaseHas('inventory_movements', [
        'movement_type' => 'reservation_expiry',
        'quantity_change' => 0,
        'reserved_after' => 0,
        'reference' => 'order-expire',
        'idempotency_key' => 'reservation_expiry:order-expire',
    ]);
});

it('denies inventory pages and writes to users without tenant permissions', function () {
    [$tenant, $user] = inventoryTenantWithUser('gate');

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->get($baseUrl.'/inventory/stocks')->assertForbidden();
    $this->get($baseUrl.'/inventory/adjustments/create')->assertForbidden();
    $this->get($baseUrl.'/inventory/ledger')->assertForbidden();
    $this->get($baseUrl.'/inventory/reservations')->assertForbidden();
    $this->post($baseUrl.'/inventory/adjustments', [])->assertForbidden();
    $this->post($baseUrl.'/inventory/reservations', [])->assertForbidden();
});

it('does not expose another tenant reservation record', function () {
    [$tenantA, $userA] = inventoryTenantWithAdmin('alpha');
    [$warehouse, $variant] = inventoryFixture($tenantA, $userA, 'CROSS-TENANT-1', 10);

    $this->get(inventoryLoginUrl($tenantA, $userA))->assertRedirect();

    $this->post(
        inventoryTenantBaseUrl($tenantA).'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 2, 'order-cross-tenant'),
    )->assertRedirectContains('/inventory/reservations');

    $reservation = DB::table('inventory_reservations')
        ->where('external_reference', 'order-cross-tenant')
        ->firstOrFail();

    [$tenantB, $userB] = inventoryTenantWithAdmin('beta');

    $this->get(inventoryLoginUrl($tenantB, $userB))->assertRedirect();

    $this->post(
        inventoryTenantBaseUrl($tenantB).'/inventory/reservations/'.$reservation->id.'/release',
    )->assertNotFound();

    $this->assertDatabaseHas('inventory_reservations', [
        'id' => $reservation->id,
        'status' => 'reserved',
    ]);
});

it('renders the inventory stock, adjustment, ledger, and reservation pages', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('pages');
    [$warehouse, $variant] = inventoryFixture($tenant, $user, 'PAGE-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();
    $baseUrl = inventoryTenantBaseUrl($tenant);

    $this->get($baseUrl.'/inventory/stocks')
        ->assertOk()
        ->assertViewIs('tenant.inventory.stocks.index')
        ->assertSee('PAGE-1');

    $this->get($baseUrl.'/inventory/adjustments/create')
        ->assertOk()
        ->assertViewIs('tenant.inventory.adjustments.create')
        ->assertSee('Record adjustment');

    $this->get($baseUrl.'/inventory/ledger')
        ->assertOk()
        ->assertViewIs('tenant.inventory.ledger.index')
        ->assertSee('Inventory ledger');

    $this->get($baseUrl.'/inventory/reservations')
        ->assertOk()
        ->assertViewIs('tenant.inventory.reservations.index')
        ->assertSee('New reservation');

    $this->get($baseUrl.'/inventory/reservations/create')
        ->assertOk()
        ->assertViewIs('tenant.inventory.reservations.create')
        ->assertSee('Reserve stock');
});

it('validates reservation input through the inventory endpoint', function () {
    [$tenant, $user] = inventoryTenantWithAdmin('validation');
    [$warehouse, $variant] = inventoryFixture($tenant, $user, 'VALIDATION-1', 10);

    $this->get(inventoryLoginUrl($tenant, $user))->assertRedirect();

    $this->post(inventoryTenantBaseUrl($tenant).'/inventory/reservations', [])
        ->assertSessionHasErrors(['warehouse_id', 'product_variant_id', 'quantity', 'external_reference']);

    $this->post(
        inventoryTenantBaseUrl($tenant).'/inventory/reservations',
        inventoryReservationPayload($warehouse, $variant, 0, 'order-invalid'),
    )->assertSessionHasErrors(['quantity']);
});
