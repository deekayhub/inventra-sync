<?php

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

class ChannelCredentialFactory extends Factory
{
    protected $model = ChannelCredential::class;

    public function definition(): array
    {
        return [
            'channel_connection_id' => ChannelConnection::factory(),
            'key' => 'access_token',
            'encrypted_value' => Crypt::encryptString(fake()->password()),
        ];
    }
}
