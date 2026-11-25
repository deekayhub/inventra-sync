<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_event_id');
            $table->string('topic')->nullable();
            $table->string('external_order_id')->nullable();
            $table->longText('raw_payload');
            $table->json('payload')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('signature_verified_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'channel_connection_id', 'external_event_id'],
                'webhook_events_tenant_connection_event_unique',
            );
            $table->index(['tenant_id', 'status', 'created_at'], 'webhook_events_tenant_status_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
