<?php

use App\Models\ReconciliationItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('reconciliation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_connection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('channel_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_movement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(ReconciliationItem::STATUS_SKIPPED);
            $table->integer('expected_quantity');
            $table->integer('remote_quantity')->nullable();
            $table->integer('difference')->nullable();
            $table->integer('correction_quantity')->nullable();
            $table->text('error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'reconciliation_run_id', 'channel_listing_id'], 'reconciliation_items_tenant_run_listing_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'reconciliation_items_tenant_status_created_index');
            $table->index(['tenant_id', 'product_variant_id', 'created_at'], 'reconciliation_items_tenant_variant_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
    }
};
