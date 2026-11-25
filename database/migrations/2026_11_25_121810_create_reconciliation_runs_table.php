<?php

use App\Models\ReconciliationRun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('channel_connection_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default(ReconciliationRun::STATUS_PENDING);
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('discrepancies_count')->default(0);
            $table->unsignedInteger('corrections_count')->default(0);
            $table->unsignedInteger('failures_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status', 'created_at'], 'reconciliation_runs_tenant_status_created_index');
            $table->index(['tenant_id', 'channel_connection_id', 'created_at'], 'reconciliation_runs_tenant_connection_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
