<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $tenantId = (string) $tenantId;

            if (DB::table('warehouses')->where('tenant_id', $tenantId)->exists()) {
                continue;
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            DB::table('warehouses')
                ->where('tenant_id', $tenantId)
                ->where('default_marker', $tenantId.':default')
                ->delete();
        }
    }
};
