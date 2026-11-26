<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Channels\ChannelConnectionController;
use App\Http\Controllers\Channels\ChannelListingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryLedgerController;
use App\Http\Controllers\Inventory\InventoryReservationController;
use App\Http\Controllers\Inventory\InventoryStockController;
use App\Http\Controllers\Inventory\ProductCategoryController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductImportController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\Orders\OrderCancellationController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\OrderFulfilmentController;
use App\Http\Controllers\Orders\OrderReturnController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\ChannelReportController;
use App\Http\Controllers\Reports\InventoryReportController;
use App\Http\Controllers\Reports\OrderReportController;
use App\Http\Controllers\Reports\ReconciliationController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Reports\SyncFailureController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Tenant\LoginController;
use App\Http\Controllers\Webhooks\ChannelWebhookController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureTenantIsActive;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Features\UserImpersonation;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomainOrSubdomain::class,
    EnsureTenantIsActive::class,
])->group(function (): void {
    Route::get('/impersonate/{token}', fn (string $token) => UserImpersonation::makeResponse($token))
        ->name('tenant.impersonate');

    Route::get('/login/{token}', LoginController::class)
        ->middleware('throttle:6,1')
        ->name('tenant.login');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('signed')
        ->name('logout');

    Route::post('webhooks/{provider}', ChannelWebhookController::class)
        ->where('provider', 'shopify|woocommerce')
        ->middleware('throttle:webhooks')
        ->name('tenant.webhooks.receive');

    Route::middleware('auth')->group(function (): void {
        Route::get('email/verify', [EmailVerificationPromptController::class, '__invoke'])
            ->name('tenant.verification.notice');
        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware(['auth', 'verified:tenant.verification.notice'])
        ->name('tenant.dashboard');

    Route::middleware(['auth', 'verified:tenant.verification.notice'])->group(function (): void {
        Route::get('orders', [OrderController::class, 'index'])
            ->middleware('permission:orders.view')
            ->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])
            ->middleware('permission:orders.view')
            ->name('orders.show');
        Route::post('orders/{order}/fulfil', [OrderFulfilmentController::class, 'store'])
            ->middleware('permission:orders.fulfill')
            ->name('orders.fulfil');
        Route::post('orders/{order}/cancel', [OrderCancellationController::class, 'store'])
            ->middleware('permission:orders.manage')
            ->name('orders.cancel');
        Route::post('orders/{order}/returns', [OrderReturnController::class, 'store'])
            ->middleware('permission:orders.manage')
            ->name('orders.returns.store');

        Route::get('channels/listings', [ChannelListingController::class, 'index'])
            ->middleware('permission:channels.view')
            ->name('channels.listings.index');
        Route::post('channels/listings/sync', [ChannelListingController::class, 'sync'])
            ->middleware(['permission:channels.sync', 'throttle:tenant-operations'])
            ->name('channels.listings.sync');
        Route::get('channels/listings/{listing}/edit', [ChannelListingController::class, 'edit'])
            ->middleware('permission:channels.manage')
            ->name('channels.listings.edit');
        Route::put('channels/listings/{listing}', [ChannelListingController::class, 'update'])
            ->middleware('permission:channels.manage')
            ->name('channels.listings.update');

        Route::post('channels/{channel}/import', [ChannelConnectionController::class, 'import'])
            ->middleware(['permission:channels.manage', 'throttle:tenant-operations'])
            ->name('channels.import');
        Route::post('channels/{channel}/deactivate', [ChannelConnectionController::class, 'deactivate'])
            ->middleware('permission:channels.manage')
            ->name('channels.deactivate');
        Route::resource('channels', ChannelConnectionController::class)
            ->except(['show', 'destroy', 'edit', 'update'])
            ->names('channels')
            ->middlewareFor('index', 'permission:channels.view')
            ->middlewareFor(['create', 'store'], 'permission:channels.manage');

        Route::get('reports/sync-failures', [SyncFailureController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('reports.failures.index');
        Route::post('reports/sync-failures/{outboxEvent}/retry', [SyncFailureController::class, 'retry'])
            ->middleware(['permission:channels.sync', 'throttle:tenant-operations'])
            ->name('reports.failures.retry');

        Route::get('reports/inventory/current', [InventoryReportController::class, 'currentStock'])
            ->middleware('permission:reports.view')
            ->name('reports.inventory.current');
        Route::get('reports/inventory/low-stock', [InventoryReportController::class, 'lowStock'])
            ->middleware('permission:reports.view')
            ->name('reports.inventory.low-stock');
        Route::get('reports/inventory/out-of-stock', [InventoryReportController::class, 'outOfStock'])
            ->middleware('permission:reports.view')
            ->name('reports.inventory.out-of-stock');
        Route::get('reports/inventory/movements', [InventoryReportController::class, 'movements'])
            ->middleware('permission:reports.view')
            ->name('reports.inventory.movements');
        Route::get('reports/orders-by-channel', [OrderReportController::class, 'byChannel'])
            ->middleware('permission:reports.view')
            ->name('reports.orders-by-channel');
        Route::get('reports/channels', [ChannelReportController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('reports.channels');
        Route::get('reports/reconciliation', [ReconciliationController::class, 'index'])
            ->middleware('permission:reports.view')
            ->name('reports.reconciliation.index');
        Route::post('reports/reconciliation/run', [ReconciliationController::class, 'run'])
            ->middleware(['permission:channels.sync', 'throttle:tenant-operations'])
            ->name('reports.reconciliation.run');
        Route::post('reports/reconciliation/{item}/correct', [ReconciliationController::class, 'correct'])
            ->middleware(['permission:inventory.adjust', 'throttle:tenant-operations'])
            ->name('reports.reconciliation.correct');
        Route::get('reports/exports/{fileName}', [ReportExportController::class, 'download'])
            ->where('fileName', '[A-Za-z0-9_-]+\.csv')
            ->middleware('permission:reports.view')
            ->name('reports.export.download');
        Route::get('reports/{report}/export', [ReportExportController::class, 'export'])
            ->where('report', 'current-inventory|low-stock|out-of-stock|movements|orders-by-channel|channels|reconciliation')
            ->middleware(['permission:reports.view', 'throttle:tenant-operations'])
            ->name('reports.export');

        Route::get('products/import', [ProductImportController::class, 'create'])
            ->middleware('permission:products.create')
            ->name('products.import.create');
        Route::get('products/import/example', [ProductImportController::class, 'example'])
            ->middleware('permission:products.create')
            ->name('products.import.example');
        Route::post('products/import', [ProductImportController::class, 'store'])
            ->middleware(['permission:products.create', 'throttle:tenant-operations'])
            ->name('products.import.store');
        Route::get('products/export', [ProductImportController::class, 'export'])
            ->middleware(['permission:products.view', 'throttle:tenant-operations'])
            ->name('products.export');
        Route::get('products/export/{fileName}', [ProductImportController::class, 'download'])
            ->where('fileName', '[A-Za-z0-9_-]+\.csv')
            ->middleware('permission:products.view')
            ->name('products.export.download');

        Route::post('products/{product}/archive', [ProductController::class, 'archive'])
            ->middleware('permission:products.update')
            ->name('products.archive');
        Route::resource('products', ProductController::class)
            ->except('show', 'destroy')
            ->names('products')
            ->middlewareFor('index', 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update');

        Route::resource('categories', ProductCategoryController::class)
            ->except('show')
            ->names('categories')
            ->middlewareFor('index', 'permission:products.view')
            ->middlewareFor(['create', 'store'], 'permission:products.create')
            ->middlewareFor(['edit', 'update'], 'permission:products.update')
            ->middlewareFor('destroy', 'permission:products.delete');

        Route::resource('warehouses', WarehouseController::class)
            ->except('show')
            ->names('warehouses')
            ->middlewareFor('index', 'permission:warehouses.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:warehouses.manage');

        Route::get('inventory/stocks', [InventoryStockController::class, 'index'])
            ->middleware('permission:inventory.view_ledger')
            ->name('inventory.stocks.index');

        Route::get('inventory/adjustments/create', [InventoryAdjustmentController::class, 'create'])
            ->middleware('permission:inventory.adjust')
            ->name('inventory.adjustments.create');
        Route::post('inventory/adjustments', [InventoryAdjustmentController::class, 'store'])
            ->middleware(['permission:inventory.adjust', 'throttle:tenant-operations'])
            ->name('inventory.adjustments.store');

        Route::get('inventory/ledger', [InventoryLedgerController::class, 'index'])
            ->middleware('permission:inventory.view_ledger')
            ->name('inventory.ledger.index');

        Route::get('inventory/reservations', [InventoryReservationController::class, 'index'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.index');
        Route::get('inventory/reservations/create', [InventoryReservationController::class, 'create'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.create');
        Route::post('inventory/reservations', [InventoryReservationController::class, 'store'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.store');
        Route::post('inventory/reservations/{reservation}/release', [InventoryReservationController::class, 'release'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.release');
        Route::post('inventory/reservations/{reservation}/commit', [InventoryReservationController::class, 'commit'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.commit');
        Route::post('inventory/reservations/{reservation}/expire', [InventoryReservationController::class, 'expire'])
            ->middleware('permission:inventory.manage')
            ->name('inventory.reservations.expire');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::put('password', [PasswordController::class, 'update'])->name('password.update');

        Route::get('/activity', [ActivityController::class, 'index'])
            ->middleware('permission:activity-logs.view')
            ->name('tenant.activity.index');

        Route::prefix('settings')->as('settings.')->group(function (): void {
            Route::get('workspace', [WorkspaceController::class, 'edit'])
                ->middleware('permission:workspace.view')
                ->name('workspace.edit');
            Route::patch('workspace', [WorkspaceController::class, 'update'])
                ->middleware('permission:workspace.update')
                ->name('workspace.update');

            Route::resource('roles', RoleController::class)
                ->except('show')
                ->names('roles')
                ->middlewareFor('index', 'permission:roles.view')
                ->middlewareFor(['create', 'store'], 'permission:roles.create')
                ->middlewareFor(['edit', 'update'], 'permission:roles.edit')
                ->middlewareFor('destroy', 'permission:roles.delete');

            Route::resource('members', MemberController::class)
                ->except('show')
                ->names('members')
                ->middlewareFor('index', 'permission:users.view')
                ->middlewareFor(['create', 'store'], 'permission:users.create')
                ->middlewareFor(['edit', 'update'], 'permission:users.edit')
                ->middlewareFor('destroy', 'permission:users.delete');
        });
    });
});
