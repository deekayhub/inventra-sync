<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index(['tenant_id', 'movement_type', 'created_at'], 'inventory_movements_tenant_type_created_index');
            $table->index(['tenant_id', 'reference'], 'inventory_movements_tenant_reference_index');
        });

        Schema::table('channel_sync_logs', function (Blueprint $table): void {
            $table->index(['tenant_id', 'direction', 'status', 'created_at'], 'channel_sync_logs_tenant_direction_status_created_index');
            $table->index(['tenant_id', 'product_variant_id', 'created_at'], 'channel_sync_logs_tenant_variant_created_index');
        });

        Schema::table('channel_connections', function (Blueprint $table): void {
            $table->index(['tenant_id', 'last_synced_at'], 'channel_connections_tenant_last_synced_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_tenant_type_created_index');
            $table->dropIndex('inventory_movements_tenant_reference_index');
        });

        Schema::table('channel_sync_logs', function (Blueprint $table): void {
            $table->dropIndex('channel_sync_logs_tenant_direction_status_created_index');
            $table->dropIndex('channel_sync_logs_tenant_variant_created_index');
        });

        Schema::table('channel_connections', function (Blueprint $table): void {
            $table->dropIndex('channel_connections_tenant_last_synced_index');
        });
    }
};
