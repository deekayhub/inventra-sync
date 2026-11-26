<?php

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\FulfilOrder;
use App\Actions\Orders\GuardOrderStatus;
use App\Actions\Orders\ImportChannelOrder;
use App\Actions\Orders\RecordOrderReturn;
use App\Adapters\ChannelAdapterResolver;
use App\Http\Controllers\Tenant\LoginController;
use App\Jobs\ProcessChannelWebhook;
use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use App\Models\Fulfilment;
use App\Models\InventoryMovement;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function ordersTenantBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function ordersLoginUrl(Tenant $tenant, User $user): string
{
    return ordersTenantBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

function ordersTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Orders Labs',
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

function ordersConnection(Tenant $tenant, string $accountIdentifier = 'orders-store.myshopify.com'): ChannelConnection
{
    return ChannelConnection::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'provider' => ChannelConnection::PROVIDER_SHOPIFY,
        'name' => 'Orders Store',
        'account_identifier' => $accountIdentifier,
        'status' => ChannelConnection::STATUS_ACTIVE,
    ]);
}

function ordersCredentials(Tenant $tenant, ChannelConnection $connection): void
{
    foreach ([
        'shop_domain' => $connection->account_identifier,
        'access_token' => 'orders-access-token',
        'location_id' => '9',
        'webhook_secret' => 'orders-webhook-secret',
    ] as $key => $value) {
        ChannelCredential::create([
            'tenant_id' => $tenant->getTenantKey(),
            'channel_connection_id' => $connection->id,
            'key' => $key,
            'encrypted_value' => Crypt::encryptString($value),
        ]);
    }
}

/** @return array{Warehouse, ProductVariant, InventoryStock} */
function ordersStockFixture(Tenant $tenant, string $sku, int $onHand = 20): array
{
    tenancy()->initialize($tenant);

    $warehouse = Warehouse::query()->active()->where('is_default', true)->firstOrFail();
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create([
        'sku' => $sku,
        'is_primary' => true,
    ]);
    $stock = InventoryStock::factory()
        ->for($warehouse)
        ->for($variant, 'variant')
        ->create([
            'on_hand' => $onHand,
            'reserved' => 0,
        ]);

    tenancy()->end();

    return [$warehouse, $variant, $stock];
}

/** @return array<string, mixed> */
function ordersShopifyPayload(string $externalOrderId, string $sku, int $quantity = 2): array
{
    return [
        'id' => $externalOrderId,
        'order_number' => '#'.$externalOrderId,
        'financial_status' => 'paid',
        'fulfillment_status' => null,
        'currency' => 'USD',
        'total_price' => '49.00',
        'email' => 'customer@example.com',
        'customer' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'customer@example.com',
        ],
        'created_at' => '2026-11-25T10:00:00Z',
        'line_items' => [
            [
                'id' => 'line-'.$externalOrderId,
                'sku' => $sku,
                'title' => 'Analytical Engine',
                'price' => '24.50',
                'quantity' => $quantity,
            ],
        ],
    ];
}

function ordersImport(Tenant $tenant, ChannelConnection $connection, array $payload): Order
{
    tenancy()->initialize($tenant);

    try {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $parsed = app(ChannelAdapterResolver::class)
            ->resolve($connection->provider)
            ->parseWebhookEvent($body, []);

        return app(ImportChannelOrder::class)($connection, $parsed['payload'], $body);
    } finally {
        tenancy()->end();
    }
}

test('duplicate webhook events create one order and reserve stock once', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-webhook');
    $connection = ordersConnection($tenant);
    ordersCredentials($tenant, $connection);
    ordersStockFixture($tenant, 'WEBHOOK-SKU', 20);

    Queue::fake();

    $payload = ordersShopifyPayload('1001', 'WEBHOOK-SKU');
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $body, 'orders-webhook-secret', true));
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => $signature,
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $connection->account_identifier,
    ];

    $this->call('POST', ordersTenantBaseUrl($tenant).'/webhooks/shopify', [], [], [], $server, $body)->assertStatus(202);
    $this->call('POST', ordersTenantBaseUrl($tenant).'/webhooks/shopify', [], [], [], $server, $body)->assertStatus(202);

    expect(WebhookEvent::withoutGlobalScopes()->where('tenant_id', $tenant->getTenantKey())->count())->toBe(1);

    Queue::assertPushed(ProcessChannelWebhook::class, 1);

    $event = WebhookEvent::withoutGlobalScopes()->firstOrFail();
    (new ProcessChannelWebhook($event->id, (string) $tenant->getTenantKey()))
        ->handle(app(ChannelAdapterResolver::class), app(ImportChannelOrder::class));

    tenancy()->initialize($tenant);

    $order = Order::query()->firstOrFail();
    $item = $order->items()->firstOrFail();
    $stock = InventoryStock::query()->firstOrFail();

    expect(Order::query()->count())->toBe(1)
        ->and(InventoryReservation::query()->count())->toBe(1)
        ->and($stock->reserved)->toBe(2)
        ->and($item->sku_snapshot)->toBe('WEBHOOK-SKU')
        ->and($item->title_snapshot)->toBe('Analytical Engine')
        ->and($item->price_snapshot)->toBe('24.50');

    $processedWebhookEvent = WebhookEvent::query()->firstOrFail();

    expect($processedWebhookEvent->status)->toBe(WebhookEvent::STATUS_PROCESSED)
        ->and($processedWebhookEvent->raw_payload)->toBeNull();

    tenancy()->end();
});

test('webhooks with an invalid signature are rejected and not persisted', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-invalid-signature');
    $connection = ordersConnection($tenant);
    ordersCredentials($tenant, $connection);

    Queue::fake();

    $body = json_encode(ordersShopifyPayload('1002', 'MISSING-SKU'), JSON_THROW_ON_ERROR);

    $this->call('POST', ordersTenantBaseUrl($tenant).'/webhooks/shopify', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SHOPIFY_HMAC_SHA256' => base64_encode('not-a-real-signature'),
        'HTTP_X_SHOPIFY_TOPIC' => 'orders/create',
        'HTTP_X_SHOPIFY_SHOP_DOMAIN' => $connection->account_identifier,
    ], $body)->assertStatus(401);

    expect(WebhookEvent::withoutGlobalScopes()->count())->toBe(0);

    Queue::assertNothingPushed();
});

test('external order ids are unique per channel connection but reusable across connections', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-external-id');
    $firstConnection = ordersConnection($tenant, 'first-orders.myshopify.com');
    $secondConnection = ordersConnection($tenant, 'second-orders.myshopify.com');
    ordersStockFixture($tenant, 'EXTERNAL-SKU', 20);

    $payload = ordersShopifyPayload('2001', 'EXTERNAL-SKU');

    ordersImport($tenant, $firstConnection, $payload);
    ordersImport($tenant, $firstConnection, $payload);
    ordersImport($tenant, $secondConnection, $payload);

    tenancy()->initialize($tenant);

    expect(Order::query()->count())->toBe(2)
        ->and(Order::query()->where('channel_connection_id', $firstConnection->id)->count())->toBe(1)
        ->and(Order::query()->where('channel_connection_id', $secondConnection->id)->count())->toBe(1);

    tenancy()->end();
});

test('failed imports can be retried safely', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-retry');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'RETRY-SKU', 20);

    tenancy()->initialize($tenant);
    Order::factory()->create([
        'tenant_id' => $tenant->getTenantKey(),
        'channel_connection_id' => $connection->id,
        'external_order_id' => '3001',
        'status' => Order::STATUS_FAILED,
    ]);
    tenancy()->end();

    $order = ordersImport($tenant, $connection, ordersShopifyPayload('3001', 'RETRY-SKU'));

    tenancy()->initialize($tenant);

    $order->refresh();

    expect(Order::query()->count())->toBe(1)
        ->and($order->status)->toBe(Order::STATUS_PROCESSING)
        ->and($order->items()->count())->toBe(1)
        ->and(InventoryReservation::query()->count())->toBe(1);

    tenancy()->end();
});

test('fulfilment commits a reservation and deducts stock exactly once', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-fulfilment');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'FULFIL-SKU', 20);
    $order = ordersImport($tenant, $connection, ordersShopifyPayload('4001', 'FULFIL-SKU', 2));

    tenancy()->initialize($tenant);

    app(FulfilOrder::class)($order);
    app(FulfilOrder::class)($order);

    $stock = InventoryStock::query()->firstOrFail();
    $reservation = InventoryReservation::query()->firstOrFail();

    expect(Fulfilment::query()->count())->toBe(1)
        ->and(Fulfilment::query()->firstOrFail()->quantity)->toBe(2)
        ->and($reservation->status)->toBe(InventoryReservation::STATUS_COMMITTED)
        ->and($stock->on_hand)->toBe(18)
        ->and($stock->reserved)->toBe(0)
        ->and(InventoryMovement::query()->where('movement_type', 'commitment')->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(Order::STATUS_FULFILLED);

    tenancy()->end();
});

test('cancellation releases a reservation exactly once', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-cancellation');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'CANCEL-SKU', 20);
    $order = ordersImport($tenant, $connection, ordersShopifyPayload('5001', 'CANCEL-SKU', 2));

    tenancy()->initialize($tenant);

    app(CancelOrder::class)($order, 'Customer changed mind');
    app(CancelOrder::class)($order, 'Customer changed mind');

    $stock = InventoryStock::query()->firstOrFail();
    $reservation = InventoryReservation::query()->firstOrFail();

    expect($order->refresh()->status)->toBe(Order::STATUS_CANCELLED)
        ->and($reservation->status)->toBe(InventoryReservation::STATUS_RELEASED)
        ->and($stock->reserved)->toBe(0)
        ->and($stock->on_hand)->toBe(20)
        ->and(InventoryMovement::query()->where('movement_type', 'reservation_release')->count())->toBe(1);

    tenancy()->end();
});

test('returns require condition classification before ledger entries are recorded', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-returns');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'RETURN-SKU', 20);
    $order = ordersImport($tenant, $connection, ordersShopifyPayload('6001', 'RETURN-SKU', 2));

    tenancy()->initialize($tenant);

    app(FulfilOrder::class)($order);
    $item = $order->items()->firstOrFail();

    expect(fn (): mixed => app(RecordOrderReturn::class)($order, $item, 1, 'unknown'))
        ->toThrow(ValidationException::class);

    $return = app(RecordOrderReturn::class)($order, $item, 1, OrderReturn::CONDITION_DAMAGED, 'Damaged in transit');
    $movement = InventoryMovement::query()->where('source', 'return')->firstOrFail();

    expect($return->condition)->toBe(OrderReturn::CONDITION_DAMAGED)
        ->and($return->status)->toBe(OrderReturn::STATUS_COMPLETED)
        ->and($movement->quantity_change)->toBe(0)
        ->and($movement->reason)->toContain('Inspected')
        ->and($item->refresh()->returned_quantity)->toBe(1);

    tenancy()->end();
});

test('invalid order status transitions are rejected', function (): void {
    [$tenant] = ordersTenantWithAdmin('orders-status-guard');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'GUARD-SKU', 20);
    $order = ordersImport($tenant, $connection, ordersShopifyPayload('7001', 'GUARD-SKU'));
    $order->status = Order::STATUS_FULFILLED;

    tenancy()->initialize($tenant);

    expect(fn (): mixed => app(GuardOrderStatus::class)($order, Order::STATUS_PENDING))
        ->toThrow(ValidationException::class);

    tenancy()->end();
});

test('order pages are available to admins', function (): void {
    [$tenant, $user] = ordersTenantWithAdmin('orders-pages');
    $connection = ordersConnection($tenant);
    ordersStockFixture($tenant, 'PAGES-SKU', 20);
    $order = ordersImport($tenant, $connection, ordersShopifyPayload('8001', 'PAGES-SKU'));

    $this->get(ordersLoginUrl($tenant, $user))->assertRedirect();

    $this->get(ordersTenantBaseUrl($tenant).'/orders')
        ->assertOk()
        ->assertSee('8001');

    $this->get(ordersTenantBaseUrl($tenant).'/orders/'.$order->id)
        ->assertOk()
        ->assertSee('Analytical Engine');
});
