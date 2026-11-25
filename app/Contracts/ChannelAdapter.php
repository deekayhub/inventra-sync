<?php

namespace App\Contracts;

interface ChannelAdapter
{
    /** @param array<string, string> $credentials */
    public function validateCredentials(array $credentials): bool;

    /**
     * @param  array<string, string>  $credentials
     * @return list<array{external_id: string, title: string, status: string}>
     */
    public function fetchListings(array $credentials): array;

    /** @param array<string, string> $credentials */
    public function pushInventory(array $credentials, string $externalId, int $quantity): bool;

    /** @param array<string, string> $credentials */
    public function fetchInventory(array $credentials, string $externalId): ?int;

    public function verifyWebhookSignature(string $payload, string $signature, string $secret): bool;

    /**
     * @param  array<string, string>  $headers
     * @return array{external_event_id: string, topic: string, external_order_id: string|null, payload: array<string, mixed>}
     */
    public function parseWebhookEvent(string $payload, array $headers): array;

    /**
     * @param  array<string, string>  $credentials
     * @return array<string, mixed>
     */
    public function fetchOrder(array $credentials, string $externalOrderId): array;
}
