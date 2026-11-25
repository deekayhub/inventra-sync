<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_outbox_event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction');
            $table->string('status');
            $table->string('event_type')->nullable();
            $table->integer('quantity')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at'], 'channel_sync_logs_tenant_status_created_index');
            $table->index(['tenant_id', 'channel_connection_id', 'created_at'], 'channel_sync_logs_tenant_connection_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sync_logs');
    }
};
