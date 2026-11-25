<?php

namespace App\Models;

use Database\Factories\ChannelCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

#[Fillable(['tenant_id', 'channel_connection_id', 'key', 'encrypted_value'])]
class ChannelCredential extends Model
{
    /** @use HasFactory<ChannelCredentialFactory> */
    use BelongsToTenant, HasFactory;

    protected $hidden = ['encrypted_value'];

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function decrypt(): string
    {
        return Crypt::decryptString($this->encrypted_value);
    }
}
