<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Events\TenantCreated;

class SeedTenantDefaultWarehouse
{
    public function handle(TenantCreated $event): void
    {
        $tenantId = (string) $event->tenant->getTenantKey();

        $exists = DB::table('warehouses')
            ->where('tenant_id', $tenantId)
            ->where('code', 'default')
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('warehouses')->insert([
            'tenant_id' => $tenantId,
            'name' => 'Default',
            'code' => 'default',
            'address' => null,
            'is_default' => true,
            'default_marker' => $tenantId.':default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
