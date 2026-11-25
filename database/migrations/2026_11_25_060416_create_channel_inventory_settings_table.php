<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_inventory_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_product_mapping_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('buffer_stock')->default(0);
            $table->boolean('paused')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'channel_product_mapping_id'], 'channel_inventory_settings_tenant_mapping_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_inventory_settings');
    }
};
