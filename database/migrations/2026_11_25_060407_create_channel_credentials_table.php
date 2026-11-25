<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('encrypted_value');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'channel_connection_id', 'key'], 'channel_credentials_tenant_connection_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_credentials');
    }
};
