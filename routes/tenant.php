<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\PasswordController;
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
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Tenant\LoginController;
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

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('auth')
        ->name('tenant.dashboard');

    Route::middleware('auth')->group(function (): void {
        Route::get('products/import', [ProductImportController::class, 'create'])
            ->middleware('permission:products.create')
            ->name('products.import.create');
        Route::post('products/import', [ProductImportController::class, 'store'])
            ->middleware('permission:products.create')
            ->name('products.import.store');
        Route::get('products/export', [ProductImportController::class, 'export'])
            ->middleware('permission:products.view')
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
            ->middleware('permission:inventory.adjust')
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

        Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
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
