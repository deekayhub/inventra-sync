<?php

namespace App\Adapters;

use App\Adapters\Concerns\VerifiesWebhookSignatures;
use App\Contracts\ChannelAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class ShopifyChannelAdapter implements ChannelAdapter
{
    use VerifiesWebhookSignatures;

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

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        return $this->signaturesMatch($payload, $signature, $secret);
    }

    public function parseWebhookEvent(string $payload, array $headers): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid Shopify webhook payload.');
        }

        $order = $this->normalizeOrder($decoded);
        $externalEventId = $this->headerValue($headers, 'X-Shopify-Webhook-Id');

        if ($externalEventId === '') {
            $externalEventId = hash('sha256', $payload);
        }

        return [
            'external_event_id' => $externalEventId,
            'topic' => $this->headerValue($headers, 'X-Shopify-Topic') ?: 'orders/create',
            'external_order_id' => $order['external_order_id'] ?? null,
            'payload' => $order,
        ];
    }

    public function fetchOrder(array $credentials, string $externalOrderId): array
    {
        $shopDomain = $this->credential($credentials, 'shop_domain');
        $this->assertShopDomain($shopDomain);

        $order = $this->client($this->credential($credentials, 'access_token'))
            ->get($this->url($shopDomain, 'orders/'.rawurlencode($externalOrderId).'.json'))
            ->throw()
            ->json('order', []);

        if (! is_array($order)) {
            throw new InvalidArgumentException('Shopify order response was invalid.');
        }

        return $this->normalizeOrder($order);
    }

    /** @param array<string, mixed> $order */
    private function normalizeOrder(array $order): array
    {
        $externalOrderId = (string) ($order['id'] ?? '');

        if ($externalOrderId === '') {
            throw new InvalidArgumentException('Shopify order did not include an ID.');
        }

        $items = [];

        foreach (is_array($order['line_items'] ?? null) ? $order['line_items'] : [] as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            $externalItemId = (string) ($lineItem['id'] ?? $lineItem['sku'] ?? '');

            if ($externalItemId === '') {
                continue;
            }

            $items[] = [
                'external_item_id' => $externalItemId,
                'sku' => isset($lineItem['sku']) ? (string) $lineItem['sku'] : null,
                'title' => (string) ($lineItem['title'] ?? $lineItem['name'] ?? $externalItemId),
                'price' => (float) ($lineItem['price'] ?? 0),
                'quantity' => max(1, (int) ($lineItem['quantity'] ?? 1)),
            ];
        }

        $billingAddress = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : [];
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $firstName = (string) ($billingAddress['first_name'] ?? $customer['first_name'] ?? '');
        $lastName = (string) ($billingAddress['last_name'] ?? $customer['last_name'] ?? '');
        $customerName = trim($firstName.' '.$lastName);
        $fulfillmentStatus = mb_strtolower((string) ($order['fulfillment_status'] ?? ''));
        $financialStatus = mb_strtolower((string) ($order['financial_status'] ?? ''));

        $status = match (true) {
            ! empty($order['cancelled_at']) => 'cancelled',
            $fulfillmentStatus === 'fulfilled' => 'fulfilled',
            $fulfillmentStatus === 'partial' => 'partially_fulfilled',
            $financialStatus === 'refunded' => 'cancelled',
            $financialStatus === 'voided' => 'failed',
            $financialStatus === 'paid' => 'processing',
            $financialStatus === 'pending' => 'pending',
            default => 'confirmed',
        };

        return [
            'external_order_id' => $externalOrderId,
            'status' => $status,
            'currency' => strtoupper((string) ($order['currency'] ?? 'USD')),
            'customer_name' => $customerName === '' ? null : $customerName,
            'customer_email' => $billingAddress['email'] ?? $customer['email'] ?? $order['email'] ?? null,
            'total_amount' => (float) ($order['total_price'] ?? 0),
            'ordered_at' => $order['created_at'] ?? null,
            'items' => $items,
        ];
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
