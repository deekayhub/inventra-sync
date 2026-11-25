<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_order_id');
            $table->string('status')->default('pending');
            $table->string('currency', 3)->default('USD');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->longText('raw_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(
                ['tenant_id', 'channel_connection_id', 'external_order_id'],
                'orders_tenant_connection_external_order_unique',
            );
            $table->index(['tenant_id', 'status', 'created_at'], 'orders_tenant_status_created_index');
            $table->index(['tenant_id', 'channel_connection_id', 'ordered_at'], 'orders_tenant_channel_ordered_index');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_item_id');
            $table->string('sku_snapshot')->nullable();
            $table->string('title_snapshot');
            $table->decimal('price_snapshot', 14, 2)->default(0);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('fulfilled_quantity')->default(0);
            $table->unsignedInteger('cancelled_quantity')->default(0);
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'order_id', 'external_item_id'], 'order_items_tenant_order_external_item_unique');
            $table->index(['tenant_id', 'product_variant_id'], 'order_items_tenant_variant_index');
        });

        Schema::create('fulfilments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedInteger('quantity');
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'order_item_id'], 'fulfilments_tenant_order_item_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'fulfilments_tenant_status_created_index');
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fulfilment_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at'], 'shipments_tenant_status_created_index');
        });

        Schema::create('cancellations', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('completed');
            $table->string('reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'order_id'], 'cancellations_tenant_order_unique');
        });

        Schema::create('returns', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition')->nullable();
            $table->string('status')->default('requested');
            $table->string('reason')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at'], 'returns_tenant_status_created_index');
        });

        Schema::create('return_items', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition');
            $table->string('disposition');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'return_id', 'order_item_id'], 'return_items_tenant_return_order_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
        Schema::dropIfExists('cancellations');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('fulfilments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
