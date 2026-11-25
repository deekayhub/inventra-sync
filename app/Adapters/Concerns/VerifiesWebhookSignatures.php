<?php

namespace App\Adapters\Concerns;

trait VerifiesWebhookSignatures
{
    /** @param array<string, mixed> $headers */
    protected function headerValue(array $headers, string $key): string
    {
        foreach ([$key, mb_strtolower($key), str_replace('-', '_', mb_strtolower($key))] as $candidate) {
            $value = $headers[$candidate] ?? null;

            if (is_array($value)) {
                $value = $value[0] ?? null;
            }

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function signaturesMatch(string $payload, string $signature, string $secret): bool
    {
        $signature = trim($signature);

        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        return hash_equals($expected, $signature);
    }
}
