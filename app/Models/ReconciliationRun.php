<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable([
    'tenant_id',
    'channel_connection_id',
    'status',
    'items_count',
    'discrepancies_count',
    'corrections_count',
    'failures_count',
    'started_at',
    'finished_at',
    'metadata',
])]
class ReconciliationRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'items_count' => 'integer',
            'discrepancies_count' => 'integer',
            'corrections_count' => 'integer',
            'failures_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
    }
}
