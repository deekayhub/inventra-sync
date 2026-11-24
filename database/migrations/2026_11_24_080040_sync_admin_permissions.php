<?php

use App\Listeners\Concerns\ScopesPermissionToTenant;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    use ScopesPermissionToTenant;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        (new PermissionSeeder)->run();

        $permissionIds = Permission::query()
            ->whereIn('name', PermissionSeeder::names())
            ->pluck('id');

        $context = $this->capturePermissionContext();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->useTenantPermissionContext((string) $tenantId);

            Role::findOrCreate(PermissionSeeder::ADMIN_ROLE)
                ->syncPermissions($permissionIds);
        }

        $this->restorePermissionContext($context);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $context = $this->capturePermissionContext();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->useTenantPermissionContext((string) $tenantId);

            Role::query()
                ->where('name', PermissionSeeder::ADMIN_ROLE)
                ->first()
                ?->revokePermissionTo(PermissionSeeder::names());
        }

        $this->useCentralPermissionContext();

        Permission::query()
            ->whereIn('name', PermissionSeeder::names())
            ->each(function (Permission $permission) {
                if ($permission->roles()->doesntExist() && $permission->users()->doesntExist()) {
                    $permission->delete();
                }
            });

        $this->restorePermissionContext($context);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
