<?php

namespace App\Adapters;

use App\Contracts\ChannelAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class ShopifyChannelAdapter implements ChannelAdapter
{
    public function validateCredentials(array $credentials): bool
    {
        $shopDomain = $this->credential($credentials, 'shop_domain');
        $this->assertShopDomain($shopDomain);

        try {
            $response = $this->client($this->credential($credentials, 'access_token'))
                ->get($this->url($shopDomain, 'shop.json'));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && is_array($response->json('shop'));
    }

    public function fetchListings(array $credentials): array
    {
        $shopDomain = $this->credential($credentials, 'shop_domain');
        $this->assertShopDomain($shopDomain);

        $response = $this->client($this->credential($credentials, 'access_token'))
            ->get($this->url($shopDomain, 'products.json?limit=250&fields=id,title,status,variants'))
            ->throw();

        $listings = [];

        foreach ($response->json('products', []) as $product) {
            foreach ($product['variants'] ?? [] as $variant) {
                $externalId = (string) ($variant['inventory_item_id'] ?? $variant['id'] ?? '');

                if ($externalId === '') {
                    continue;
                }

                $title = trim(((string) ($product['title'] ?? '')).' '.((string) ($variant['title'] ?? '')));
                $listings[] = [
                    'external_id' => $externalId,
                    'title' => $title === '' ? $externalId : $title,
                    'status' => (string) ($product['status'] ?? 'active'),
                ];
            }
        }

        return $listings;
    }

    public function pushInventory(array $credentials, string $externalId, int $quantity): bool
    {
        $shopDomain = $this->credential($credentials, 'shop_domain');
        $this->assertShopDomain($shopDomain);

        $response = $this->client($this->credential($credentials, 'access_token'))
            ->put($this->url($shopDomain, 'inventory_levels/set.json'), [
                'location_id' => $this->credential($credentials, 'location_id'),
                'inventory_item_id' => $externalId,
                'available' => $quantity,
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

    private function assertShopDomain(string $shopDomain): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i', $shopDomain)) {
            throw new InvalidArgumentException('Invalid Shopify domain.');
        }
    }

    private function url(string $shopDomain, string $path): string
    {
        $apiVersion = (string) config('channels.shopify.api_version');

        return "https://{$shopDomain}/admin/api/{$apiVersion}/{$path}";
    }

    private function client(string $accessToken): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'X-Shopify-Access-Token' => $accessToken,
        ])
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
