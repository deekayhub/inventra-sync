<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->longText('raw_payload')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('webhook_events')
            ->whereNull('raw_payload')
            ->update(['raw_payload' => '']);

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->longText('raw_payload')->change();
        });
    }
};
