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

class WooCommerceChannelAdapter implements ChannelAdapter
{
    use VerifiesWebhookSignatures;

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

    public function fetchInventory(array $credentials, string $externalId): ?int
    {
        $siteUrl = $this->siteUrl($credentials);
        $response = $this->client($credentials)
            ->get($siteUrl.'/wp-json/wc/v3/products/'.rawurlencode($externalId).'?fields=id,manage_stock,stock_quantity');

        if ($response->notFound()) {
            return null;
        }

        $product = $response->throw()->json();

        if (! is_array($product) || empty($product['manage_stock'])) {
            return null;
        }

        $quantity = $product['stock_quantity'] ?? null;

        return is_numeric($quantity) ? (int) $quantity : null;
    }

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool
    {
        return $this->signaturesMatch($payload, $signature, $secret);
    }

    public function parseWebhookEvent(string $payload, array $headers): array
    {
        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Invalid WooCommerce webhook payload.');
        }

        $order = $this->normalizeOrder($decoded);
        $externalEventId = $this->headerValue($headers, 'X-WC-Webhook-ID');

        if ($externalEventId === '') {
            $externalEventId = hash('sha256', $payload);
        }

        return [
            'external_event_id' => $externalEventId,
            'topic' => $this->headerValue($headers, 'X-WC-Webhook-Topic') ?: 'woocommerce_order_created',
            'external_order_id' => $order['external_order_id'] ?? null,
            'payload' => $order,
        ];
    }

    public function fetchOrder(array $credentials, string $externalOrderId): array
    {
        $siteUrl = $this->siteUrl($credentials);

        $order = $this->client($credentials)
            ->get($siteUrl.'/wp-json/wc/v3/orders/'.rawurlencode($externalOrderId))
            ->throw()
            ->json();

        if (! is_array($order)) {
            throw new InvalidArgumentException('WooCommerce order response was invalid.');
        }

        return $this->normalizeOrder($order);
    }

    /** @param array<string, mixed> $order */
    private function normalizeOrder(array $order): array
    {
        $externalOrderId = (string) ($order['id'] ?? '');

        if ($externalOrderId === '') {
            throw new InvalidArgumentException('WooCommerce order did not include an ID.');
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
                'title' => (string) ($lineItem['name'] ?? $externalItemId),
                'price' => (float) ($lineItem['price'] ?? 0),
                'quantity' => max(1, (int) ($lineItem['quantity'] ?? 1)),
            ];
        }

        $billing = is_array($order['billing'] ?? null) ? $order['billing'] : [];
        $customerName = trim(
            ((string) ($billing['first_name'] ?? '')).' '.((string) ($billing['last_name'] ?? '')),
        );
        $status = match (mb_strtolower((string) ($order['status'] ?? ''))) {
            'completed' => 'fulfilled',
            'processing' => 'processing',
            'on-hold', 'pending' => 'pending',
            'cancelled', 'canceled', 'refunded' => 'cancelled',
            'failed' => 'failed',
            default => 'confirmed',
        };

        return [
            'external_order_id' => $externalOrderId,
            'status' => $status,
            'currency' => strtoupper((string) ($order['currency'] ?? 'USD')),
            'customer_name' => $customerName === '' ? null : $customerName,
            'customer_email' => $billing['email'] ?? null,
            'total_amount' => (float) ($order['total'] ?? 0),
            'ordered_at' => $order['date_created'] ?? null,
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
