<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            'inventory_movements' => [
                'inventory_movements_tenant_type_created_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'movement_type', 'created_at'], 'inventory_movements_tenant_type_created_index'),
                'inventory_movements_tenant_reference_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'reference'], 'inventory_movements_tenant_reference_index'),
            ],
            'channel_sync_logs' => [
                'channel_sync_logs_tenant_direction_status_created_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'direction', 'status', 'created_at'], 'channel_sync_logs_tenant_direction_status_created_index'),
                'channel_sync_logs_tenant_variant_created_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'product_variant_id', 'created_at'], 'channel_sync_logs_tenant_variant_created_index'),
            ],
            'channel_connections' => [
                'channel_connections_tenant_last_synced_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'last_synced_at'], 'channel_connections_tenant_last_synced_index'),
            ],
        ];

        foreach ($indexes as $tableName => $tableIndexes) {
            foreach ($tableIndexes as $indexName => $createIndex) {
                if (Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, fn (Blueprint $table) => $createIndex($table));
            }
        }
    }

    public function down(): void
    {
        $indexes = [
            'inventory_movements' => [
                'inventory_movements_tenant_type_created_index',
                'inventory_movements_tenant_reference_index',
            ],
            'channel_sync_logs' => [
                'channel_sync_logs_tenant_direction_status_created_index',
                'channel_sync_logs_tenant_variant_created_index',
            ],
            'channel_connections' => [
                'channel_connections_tenant_last_synced_index',
            ],
        ];

        foreach ($indexes as $tableName => $indexNames) {
            foreach ($indexNames as $indexName) {
                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
            }
        }
    }
};
