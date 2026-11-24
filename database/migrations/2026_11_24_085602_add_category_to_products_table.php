<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('product_category_id')->nullable()->after('description')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'product_category_id'], 'products_tenant_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_tenant_category_index');
            $table->dropForeign(['product_category_id']);
            $table->dropColumn('product_category_id');
        });
    }
};
