<?php

use App\Adapters\ChannelAdapterResolver;
use App\Adapters\ShopifyChannelAdapter;
use App\Adapters\WooCommerceChannelAdapter;
use App\Models\ChannelConnection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config()->set('channels.shopify.api_version', '2025-10');
});

test('shopify adapter validates credentials against the shop endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/shop.json' => Http::response(['shop' => ['id' => 1]], 200),
    ]);

    $valid = app(ShopifyChannelAdapter::class)->validateCredentials([
        'shop_domain' => 'demo-store.myshopify.com',
        'access_token' => 'secret-token',
        'location_id' => '9',
    ]);

    expect($valid)->toBeTrue();
});

test('shopify adapter fails safely for invalid credentials', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/shop.json' => Http::response(['errors' => 'Unauthorized'], 401),
    ]);

    $valid = app(ShopifyChannelAdapter::class)->validateCredentials([
        'shop_domain' => 'demo-store.myshopify.com',
        'access_token' => 'bad-token',
        'location_id' => '9',
    ]);

    expect($valid)->toBeFalse();
});

test('shopify adapter maps product variants to inventory item listings', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/products.json*' => Http::response([
            'products' => [
                [
                    'id' => 101,
                    'title' => 'Widget',
                    'status' => 'active',
                    'variants' => [
                        ['id' => 201, 'inventory_item_id' => 301, 'title' => 'Default'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $listings = app(ShopifyChannelAdapter::class)->fetchListings([
        'shop_domain' => 'demo-store.myshopify.com',
        'access_token' => 'secret-token',
        'location_id' => '9',
    ]);

    expect($listings)->toBe([
        ['external_id' => '301', 'title' => 'Widget Default', 'status' => 'active'],
    ]);
});

test('shopify adapter pushes inventory to the configured location', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json' => Http::response([], 200),
    ]);

    $pushed = app(ShopifyChannelAdapter::class)->pushInventory([
        'shop_domain' => 'demo-store.myshopify.com',
        'access_token' => 'secret-token',
        'location_id' => '9',
    ], '301', 12);

    expect($pushed)->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://demo-store.myshopify.com/admin/api/2025-10/inventory_levels/set.json'
        && $request->header('X-Shopify-Access-Token') === ['secret-token']
        && $request['location_id'] === '9'
        && $request['inventory_item_id'] === '301'
        && $request['available'] === 12);
});

test('woocommerce adapter validates credentials against the products endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example.com/wp-json/wc/v3/products?per_page=1' => Http::response([], 200),
    ]);

    $valid = app(WooCommerceChannelAdapter::class)->validateCredentials([
        'site_url' => 'https://shop.example.com',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_secret',
    ]);

    expect($valid)->toBeTrue();
});

test('woocommerce adapter fetches listings and pushes stock', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://shop.example.com/wp-json/wc/v3/products?per_page=100' => Http::response([
            ['id' => 77, 'name' => 'Tee', 'status' => 'publish'],
        ], 200),
        'https://shop.example.com/wp-json/wc/v3/products/77' => Http::response([], 200),
    ]);

    $adapter = app(WooCommerceChannelAdapter::class);
    $credentials = [
        'site_url' => 'https://shop.example.com',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_secret',
    ];

    expect($adapter->fetchListings($credentials))->toBe([
        ['external_id' => '77', 'title' => 'Tee', 'status' => 'publish'],
    ]);

    expect($adapter->pushInventory($credentials, '77', 8))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://shop.example.com/wp-json/wc/v3/products/77'
        && $request->hasHeader('Authorization')
        && $request['stock_quantity'] === 8
        && $request['manage_stock'] === true);
});

test('woocommerce adapter rejects local site urls before sending a request', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    expect(fn () => app(WooCommerceChannelAdapter::class)->fetchListings([
        'site_url' => 'http://localhost',
        'consumer_key' => 'ck_test',
        'consumer_secret' => 'cs_secret',
    ]))->toThrow(InvalidArgumentException::class);
});

test('resolver returns the adapter for each supported provider', function (): void {
    $resolver = app(ChannelAdapterResolver::class);

    expect($resolver->resolve(ChannelConnection::PROVIDER_SHOPIFY))->toBeInstanceOf(ShopifyChannelAdapter::class)
        ->and($resolver->resolve(ChannelConnection::PROVIDER_WOOCOMMERCE))->toBeInstanceOf(WooCommerceChannelAdapter::class);
});
