<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class WooCommerceChannelAdapter implements ChannelAdapter
{
    public function validateCredentials(array $credentials): bool
    {
        $siteUrl = $this->siteUrl($credentials);

        try {
            $response = $this->client($credentials)
                ->get($siteUrl.'/wp-json/wc/v3/products?per_page=1');
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && is_array($response->json());
    }

    public function fetchListings(array $credentials): array
    {
        $siteUrl = $this->siteUrl($credentials);

        $products = $this->client($credentials)
            ->get($siteUrl.'/wp-json/wc/v3/products?per_page=100')
            ->throw()
            ->json();

        $listings = [];

        foreach (is_array($products) ? $products : [] as $product) {
            $externalId = (string) ($product['id'] ?? '');

            if ($externalId === '') {
                continue;
            }

            $listings[] = [
                'external_id' => $externalId,
                'title' => (string) ($product['name'] ?? $externalId),
                'status' => (string) ($product['status'] ?? 'publish'),
            ];
        }

        return $listings;
    }

    public function pushInventory(array $credentials, string $externalId, int $quantity): bool
    {
        $siteUrl = $this->siteUrl($credentials);

        $response = $this->client($credentials)
            ->put($siteUrl.'/wp-json/wc/v3/products/'.$externalId, [
                'manage_stock' => true,
                'stock_quantity' => $quantity,
            ]);

        return $response->throw()->successful();
    }

    /** @param array<string, string> $credentials */
    private function credential(array $credentials, string $key): string
    {
        $value = $credentials[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidArgumentException("Missing channel credential [{$key}].");
        }

        return $value;
    }

    /** @param array<string, string> $credentials */
    private function siteUrl(array $credentials): string
    {
        $siteUrl = rtrim($this->credential($credentials, 'site_url'), '/');
        $parts = parse_url($siteUrl);

        if (
            $parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || empty($parts['host'])
            || filter_var($parts['host'], FILTER_VALIDATE_IP) !== false
            || in_array(mb_strtolower((string) $parts['host']), ['localhost'], true)
        ) {
            throw new InvalidArgumentException('Invalid WooCommerce site URL.');
        }

        return $siteUrl;
    }

    /** @param array<string, string> $credentials */
    private function client(array $credentials): PendingRequest
    {
        return Http::withBasicAuth(
            $this->credential($credentials, 'consumer_key'),
            $this->credential($credentials, 'consumer_secret'),
        )
            ->acceptJson()
            ->connectTimeout((int) config('channels.http.connect_timeout'))
            ->timeout((int) config('channels.http.timeout'))
            ->retry(
                (int) config('channels.http.retry_times'),
                (int) config('channels.http.retry_delay_ms'),
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429)),
                throw: false,
            );
    }
}
