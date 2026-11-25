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

    public function up(): void
    {
        $context = $this->capturePermissionContext();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->useTenantPermissionContext((string) $tenantId);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            (new PermissionSeeder)->run();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            Role::findOrCreate(PermissionSeeder::ADMIN_ROLE)
                ->syncPermissions(Permission::query()->whereIn('name', PermissionSeeder::names())->get());
        }

        $this->restorePermissionContext($context);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $context = $this->capturePermissionContext();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $this->useTenantPermissionContext((string) $tenantId);

            Role::query()
                ->where('name', PermissionSeeder::ADMIN_ROLE)
                ->first()
                ?->revokePermissionTo(['channels.view', 'channels.manage', 'channels.sync']);
        }

        $this->useCentralPermissionContext();

        Permission::query()
            ->whereIn('name', ['channels.view', 'channels.manage', 'channels.sync'])
            ->each(function (Permission $permission) {
                if ($permission->roles()->doesntExist() && $permission->users()->doesntExist()) {
                    $permission->delete();
                }
            });

        $this->restorePermissionContext($context);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
