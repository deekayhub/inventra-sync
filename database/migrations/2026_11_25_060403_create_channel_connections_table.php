<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider');
            $table->string('name');
            $table->string('account_identifier');
            $table->string('status')->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider', 'account_identifier'], 'channel_connections_tenant_provider_account_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};
