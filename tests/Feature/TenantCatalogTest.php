<?php

use App\Actions\Catalog\CreateProduct;
use App\Http\Controllers\Tenant\LoginController;
use App\Jobs\ExportProducts;
use App\Jobs\ImportProducts;
use App\Jobs\ProcessProductImportChunk;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

function catalogTenantBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->domains()->firstOrFail()->domain.'.saas.test';
}

function catalogLoginUrl(Tenant $tenant, User $user): string
{
    return catalogTenantBaseUrl($tenant).'/login/'.LoginController::issueToken($user, $tenant);
}

function catalogTenantWithAdmin(string $domain): array
{
    $tenant = Tenant::create([
        'name' => ucfirst($domain).' Catalog Labs',
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

/** @return array<string, mixed> */
function catalogProductPayload(string $name, string $sku, ?int $categoryId = null): array
{
    return [
        'name' => $name,
        'description' => 'Test product description',
        'product_category_id' => $categoryId,
        'type' => Product::TYPE_SIMPLE,
        'status' => Product::STATUS_ACTIVE,
        'low_stock_threshold' => 5,
        'buffer_stock' => 1,
        'variants' => [
            [
                'sku' => $sku,
                'title' => 'Default',
                'barcode' => null,
                'is_primary' => 1,
            ],
        ],
    ];
}

it('seeds a default warehouse for a new tenant', function () {
    $tenant = Tenant::create([
        'name' => 'Default Warehouse Labs',
        'email' => 'warehouse@example.com',
    ]);

    $warehouse = DB::table('warehouses')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('code', 'default')
        ->firstOrFail();

    expect($warehouse->name)->toBe('Default')
        ->and((bool) $warehouse->is_default)->toBeTrue()
        ->and($warehouse->default_marker)->toBe($tenant->getTenantKey().':default');
});

it('renders primary and removable variant controls on the product form', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $this->get($baseUrl.'/products/create')
        ->assertOk()
        ->assertSee('Set primary')
        ->assertSee('data-primary-radio', false)
        ->assertSee('data-remove-variant', false);
});

it('creates products and variants for the current tenant', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/products', catalogProductPayload('Widget', 'WIDGET-1'))
        ->assertRedirect();

    $product = DB::table('products')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('name', 'Widget')
        ->firstOrFail();

    expect(DB::table('product_variants')->where('product_id', $product->id)->count())->toBe(1);

    $this->assertDatabaseHas('product_variants', [
        'product_id' => $product->id,
        'tenant_id' => $tenant->getTenantKey(),
        'sku' => 'WIDGET-1',
        'is_primary' => 1,
    ]);

    expect(DB::table('activity_log')
        ->where('log_name', 'products')
        ->where('subject_id', $product->id)
        ->where('event', 'created')
        ->exists())->toBeTrue();
});

it('enforces tenant scoped SKU uniqueness', function () {
    [$tenantA, $userA] = catalogTenantWithAdmin('alpha');

    $this->get(catalogLoginUrl($tenantA, $userA))->assertRedirect();
    $this->post(catalogTenantBaseUrl($tenantA).'/products', catalogProductPayload('Alpha Widget', 'TENANT-SKU'))
        ->assertRedirect();

    [$tenantB, $userB] = catalogTenantWithAdmin('beta');

    $this->get(catalogLoginUrl($tenantB, $userB))->assertRedirect();
    $this->post(catalogTenantBaseUrl($tenantB).'/products', catalogProductPayload('Beta Widget', 'TENANT-SKU'))
        ->assertRedirect();

    expect(DB::table('product_variants')->where('sku', 'TENANT-SKU')->count())->toBe(2);

    $this->get(catalogLoginUrl($tenantA, $userA))->assertRedirect();
    $this->post(catalogTenantBaseUrl($tenantA).'/products', catalogProductPayload('Duplicate Widget', 'TENANT-SKU'))
        ->assertSessionHasErrors('variants.0.sku');
});

it('rejects duplicate SKUs within one product submission', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $payload = catalogProductPayload('Duplicate Variants', 'DUPLICATE-1');
    $payload['variants'][] = [
        'sku' => 'DUPLICATE-1',
        'title' => 'Second',
        'barcode' => null,
        'is_primary' => 0,
    ];

    $this->post($baseUrl.'/products', $payload)->assertSessionHasErrors('variants');
});

it('archives products through the action backed route', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();
    $this->post($baseUrl.'/products', catalogProductPayload('Archive Me', 'ARCHIVE-1'))
        ->assertRedirect();

    $productId = DB::table('products')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('name', 'Archive Me')
        ->value('id');

    $this->post($baseUrl.'/products/'.$productId.'/archive')->assertRedirect();

    expect(DB::table('products')->where('id', $productId)->value('status'))
        ->toBe(Product::STATUS_ARCHIVED);
});

it('creates updates and deletes categories for the current tenant', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/categories', [
        'name' => 'Office Supplies',
        'description' => null,
        'is_active' => 1,
    ])->assertRedirect();

    $category = DB::table('product_categories')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('slug', 'office-supplies')
        ->firstOrFail();

    $this->get($baseUrl.'/categories/'.$category->id.'/edit')->assertOk();

    $this->put($baseUrl.'/categories/'.$category->id, [
        'name' => 'Stationery',
        'description' => null,
        'is_active' => 1,
    ])->assertRedirect();

    expect(DB::table('product_categories')->where('id', $category->id)->value('slug'))
        ->toBe('stationery');

    $this->delete($baseUrl.'/categories/'.$category->id)->assertRedirect();

    expect(DB::table('product_categories')->where('id', $category->id)->exists())->toBeFalse();
});

it('keeps one default warehouse per tenant and protects it from deletion', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $this->post($baseUrl.'/warehouses', [
        'name' => 'Main',
        'code' => 'main',
        'address' => null,
        'is_default' => 1,
        'is_active' => 1,
    ])->assertRedirect();

    $main = DB::table('warehouses')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('code', 'main')
        ->firstOrFail();

    $seededDefault = DB::table('warehouses')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('code', 'default')
        ->firstOrFail();

    expect($main->default_marker)->toBe($tenant->getTenantKey().':default')
        ->and((bool) $seededDefault->is_default)->toBeFalse();

    $this->delete($baseUrl.'/warehouses/'.$main->id)->assertSessionHasErrors('warehouse');

    $this->post($baseUrl.'/warehouses', [
        'name' => 'Overflow',
        'code' => 'overflow',
        'address' => null,
        'is_default' => 0,
        'is_active' => 1,
    ])->assertRedirect();

    $overflowId = DB::table('warehouses')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('code', 'overflow')
        ->value('id');

    $this->delete($baseUrl.'/warehouses/'.$overflowId)->assertRedirect();

    expect(DB::table('warehouses')->where('id', $overflowId)->exists())->toBeFalse();
});

it('gates catalog screens by tenant permission', function () {
    [$tenant, $admin] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $member = User::factory()->create([
        'name' => 'Catalog Member',
        'tenant_id' => $tenant->getTenantKey(),
    ]);

    $this->get(catalogLoginUrl($tenant, $member))->assertRedirect();
    $this->get($baseUrl.'/products')->assertForbidden();
    $this->get($baseUrl.'/categories')->assertForbidden();
    $this->get($baseUrl.'/warehouses')->assertForbidden();

    $this->get(catalogLoginUrl($tenant, $admin))->assertRedirect();
    $this->get($baseUrl.'/products')->assertOk()->assertSee('Products');
    $this->get($baseUrl.'/categories')->assertOk()->assertSee('Categories');
    $this->get($baseUrl.'/warehouses')->assertOk()->assertSee('Warehouses');
});

it('queues product import and export jobs', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    Storage::fake('local');
    config()->set('tenancy.filesystem.disks', []);
    Queue::fake();

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $file = UploadedFile::fake()->createWithContent('products.csv', "sku,name\nQUEUED-1,Queued Widget");

    $this->post($baseUrl.'/products/import', ['file' => $file])->assertRedirect();
    $this->get($baseUrl.'/products/export')->assertRedirect();

    Queue::assertPushed(ImportProducts::class);
    Queue::assertPushed(ExportProducts::class);
});

it('downloads a product import example CSV', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $response = $this->get($baseUrl.'/products/import/example');

    $content = $response->assertOk()
        ->assertStreamed()
        ->streamedContent();

    expect($content)
        ->toContain(implode(',', ImportProducts::CSV_HEADERS))
        ->toContain('SAMPLE-WIDGET-001');
});

it('shows drag and drop upload controls and the example download link', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');
    $baseUrl = catalogTenantBaseUrl($tenant);

    $this->get(catalogLoginUrl($tenant, $user))->assertRedirect();

    $this->get($baseUrl.'/products/import')
        ->assertOk()
        ->assertSee('Download example CSV')
        ->assertSee('Drag and drop your CSV here')
        ->assertSee('@drop.prevent', false)
        ->assertSee($baseUrl.'/products/import/example', false);
});

it('creates products from processed import rows', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');

    tenancy()->initialize($tenant);
    Auth::setUser($user);
    tenancy()->end();

    $job = new ProcessProductImportChunk(
        [['sku' => 'IMPORT-1', 'name' => 'Imported Widget', 'category' => 'Tools']],
        (string) $tenant->getTenantKey(),
        $user->id,
    );

    $job->handle(app(CreateProduct::class));

    $product = DB::table('products')
        ->where('tenant_id', $tenant->getTenantKey())
        ->where('name', 'Imported Widget')
        ->firstOrFail();

    expect(DB::table('product_variants')
        ->where('product_id', $product->id)
        ->where('sku', 'IMPORT-1')
        ->exists())->toBeTrue();
});

it('exports products to a tenant scoped CSV file', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');

    Storage::fake('local');
    config()->set('tenancy.filesystem.disks', []);

    tenancy()->initialize($tenant);
    Auth::setUser($user);
    app(CreateProduct::class)(catalogProductPayload('Export Widget', 'EXPORT-1'));
    tenancy()->end();

    $fileName = 'products-export-'.$tenant->getTenantKey().'-test.csv';

    (new ExportProducts((string) $tenant->getTenantKey(), $user->id, $fileName))->handle();

    $contents = Storage::disk('local')->get('product-exports/'.$fileName);

    expect(str_contains($contents, 'EXPORT-1'))->toBeTrue()
        ->and(str_contains($contents, 'Export Widget'))->toBeTrue();
});

it('records product activity for action backed writes', function () {
    [$tenant, $user] = catalogTenantWithAdmin('catalog');

    tenancy()->initialize($tenant);
    Auth::setUser($user);

    $product = app(CreateProduct::class)(catalogProductPayload('Logged Widget', 'LOGGED-1'));

    tenancy()->end();

    $events = Activity::query()
        ->inLog('products')
        ->where('subject_id', $product->id)
        ->pluck('event')
        ->all();

    expect($events)->toContain('created');
});
