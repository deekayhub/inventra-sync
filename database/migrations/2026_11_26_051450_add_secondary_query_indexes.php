<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = [
            'orders' => [
                'orders_tenant_status_ordered_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'status', 'ordered_at'], 'orders_tenant_status_ordered_index'),
            ],
            'channel_listings' => [
                'channel_listings_tenant_imported_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'last_imported_at'], 'channel_listings_tenant_imported_index'),
            ],
            'inventory_reservations' => [
                'inventory_reservations_tenant_status_created_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'status', 'created_at'], 'inventory_reservations_tenant_status_created_index'),
            ],
            'users' => [
                'users_tenant_created_index' => fn (Blueprint $table) => $table
                    ->index(['tenant_id', 'created_at'], 'users_tenant_created_index'),
            ],
            'activity_log' => [
                'activity_log_log_event_created_index' => fn (Blueprint $table) => $table
                    ->index(['log_name', 'event', 'created_at'], 'activity_log_log_event_created_index'),
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
            'orders' => [
                'orders_tenant_status_ordered_index',
            ],
            'channel_listings' => [
                'channel_listings_tenant_imported_index',
            ],
            'inventory_reservations' => [
                'inventory_reservations_tenant_status_created_index',
            ],
            'users' => [
                'users_tenant_id_foreign',
                'users_tenant_created_index',
            ],
            'activity_log' => [
                'activity_log_log_event_created_index',
            ],
        ];

        foreach ($indexes as $tableName => $indexNames) {
            foreach ($indexNames as $indexName) {
                if ($tableName === 'users' && $indexName === 'users_tenant_id_foreign') {
                    if (! Schema::hasIndex($tableName, $indexName)) {
                        Schema::table($tableName, fn (Blueprint $table) => $table
                            ->index('tenant_id', 'users_tenant_id_foreign'));
                    }

                    continue;
                }

                if (! Schema::hasIndex($tableName, $indexName)) {
                    continue;
                }

                Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
            }
        }
    }
};
