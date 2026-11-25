<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_outbox_events', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('event_type');
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_listing_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_try_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('idempotency_key');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'idempotency_key'], 'inventory_outbox_tenant_idempotency_unique');
            $table->index(['tenant_id', 'status', 'next_try_at'], 'inventory_outbox_tenant_status_next_try_index');
            $table->index(['tenant_id', 'channel_listing_id', 'id'], 'inventory_outbox_tenant_listing_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_outbox_events');
    }
};
