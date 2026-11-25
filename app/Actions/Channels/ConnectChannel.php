<?php

namespace App\Actions\Channels;

use App\Adapters\ChannelAdapterResolver;
use App\Models\ChannelConnection;
use App\Models\ChannelCredential;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ConnectChannel
{
    public function __construct(private ChannelAdapterResolver $resolver) {}

    /** @param array{provider: string, name: string, credentials: array<string, string>} $data */
    public function __invoke(array $data): ChannelConnection
    {
        $provider = $data['provider'];
        $credentials = $data['credentials'];
        $adapter = $this->resolver->resolve($provider);

        try {
            $isValid = $adapter->validateCredentials($credentials);
        } catch (InvalidArgumentException) {
            $isValid = false;
        }

        if (! $isValid) {
            throw ValidationException::withMessages([
                'credentials' => __('The provided credentials could not be verified.'),
            ]);
        }

        $accountIdentifier = $provider === ChannelConnection::PROVIDER_SHOPIFY
            ? $credentials['shop_domain']
            : rtrim($credentials['site_url'], '/');

        return DB::transaction(function () use ($data, $credentials, $provider, $accountIdentifier): ChannelConnection {
            $connection = ChannelConnection::create([
                'provider' => $provider,
                'name' => $data['name'],
                'account_identifier' => $accountIdentifier,
                'status' => ChannelConnection::STATUS_ACTIVE,
                'activated_at' => now(),
            ]);

            foreach ($credentials as $key => $value) {
                ChannelCredential::create([
                    'channel_connection_id' => $connection->id,
                    'key' => $key,
                    'encrypted_value' => Crypt::encryptString($value),
                ]);
            }

            activity('channels')
                ->performedOn($connection)
                ->causedBy(auth()->user())
                ->event('connected')
                ->log('connected');

            return $connection;
        });
    }
}
