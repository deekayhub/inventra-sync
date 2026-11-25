<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('payload_hash')->nullable()->after('external_event_id');
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_try_at')->nullable()->after('attempts');
            $table->text('last_error')->nullable()->after('next_try_at');

            $table->unique(
                ['tenant_id', 'channel_connection_id', 'payload_hash'],
                'webhook_events_tenant_connection_payload_hash_unique',
            );
            $table->index(['tenant_id', 'status', 'next_try_at'], 'webhook_events_tenant_status_next_try_index');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropIndex('webhook_events_tenant_status_next_try_index');
            $table->dropUnique('webhook_events_tenant_connection_payload_hash_unique');
            $table->dropColumn(['payload_hash', 'attempts', 'next_try_at', 'last_error']);
        });
    }
};
