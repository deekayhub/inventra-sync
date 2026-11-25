<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_listings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamp('last_imported_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'channel_connection_id', 'external_id'], 'channel_listings_tenant_connection_external_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_listings');
    }
};
