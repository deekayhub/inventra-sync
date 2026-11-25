<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'channel_listing_id'], 'channel_product_mappings_tenant_listing_unique');
            $table->unique(['tenant_id', 'channel_connection_id', 'product_variant_id'], 'channel_product_mappings_tenant_connection_variant_unique');
            $table->index(['tenant_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_product_mappings');
    }
};
