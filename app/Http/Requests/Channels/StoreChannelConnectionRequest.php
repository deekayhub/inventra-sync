<?php

namespace App\Http\Requests\Channels;

use App\Models\ChannelConnection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChannelConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $provider = $this->input('provider');
        $credentials = $this->input('credentials', []);

        if (! is_array($credentials)) {
            $credentials = [];
        }

        if (is_string($provider)) {
            $credentials = $this->normalizeCredential($credentials, 'shop_domain', mb_strtolower(trim((string) $this->input('credentials.shop_domain'))));
            $credentials = $this->normalizeCredential($credentials, 'site_url', rtrim(trim((string) $this->input('credentials.site_url')), '/'));
        }

        $this->merge([
            'provider' => is_string($provider) ? mb_strtolower(trim($provider)) : $provider,
            'credentials' => $credentials,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::in(ChannelConnection::PROVIDERS)],
            'name' => ['required', 'string', 'max:255'],
            'credentials' => ['required', 'array'],
            'credentials.shop_domain' => [
                'required_if:provider,shopify',
                'exclude_unless:provider,shopify',
                'string',
                'max:255',
                'regex:/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/i',
            ],
            'credentials.access_token' => [
                'required_if:provider,shopify',
                'exclude_unless:provider,shopify',
                'string',
                'max:255',
            ],
            'credentials.location_id' => [
                'required_if:provider,shopify',
                'exclude_unless:provider,shopify',
                'string',
                'max:255',
                'regex:/^\d+$/',
            ],
            'credentials.site_url' => [
                'required_if:provider,woocommerce',
                'exclude_unless:provider,woocommerce',
                'string',
                'url:https',
                'max:255',
            ],
            'credentials.consumer_key' => [
                'required_if:provider,woocommerce',
                'exclude_unless:provider,woocommerce',
                'string',
                'max:255',
            ],
            'credentials.consumer_secret' => [
                'required_if:provider,woocommerce',
                'exclude_unless:provider,woocommerce',
                'string',
                'max:255',
            ],
            'credentials.webhook_secret' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<(callable(Validator): void)> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $provider = (string) $this->input('provider');

                if ($provider === ChannelConnection::PROVIDER_SHOPIFY) {
                    $accountIdentifier = (string) $this->input('credentials.shop_domain');
                } else {
                    $siteUrl = (string) $this->input('credentials.site_url');
                    $parts = parse_url($siteUrl);
                    $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';

                    if (
                        ! is_array($parts)
                        || ($parts['scheme'] ?? null) !== 'https'
                        || $host === ''
                        || filter_var($host, FILTER_VALIDATE_IP) !== false
                        || in_array(mb_strtolower($host), ['localhost'], true)
                    ) {
                        $validator->errors()->add('credentials.site_url', __('Enter a public HTTPS site URL.'));

                        return;
                    }

                    $accountIdentifier = rtrim($siteUrl, '/');
                }

                $exists = ChannelConnection::query()
                    ->where('provider', $provider)
                    ->where('account_identifier', $accountIdentifier)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('credentials', __('This channel account is already connected.'));
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function normalizeCredential(array $credentials, string $key, string $value): array
    {
        if ($value !== '') {
            $credentials[$key] = $value;
        }

        return $credentials;
    }
}
