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
}
