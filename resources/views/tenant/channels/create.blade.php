<x-app-layout :title="__('Connect channel')">
    <x-slot name="header">
        <div>
            <p class="eyebrow">{{ __('Integrations') }}</p>
            <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Connect channel') }}</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Credentials are verified once, encrypted at rest, and never shown again.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('channels.store') }}" class="space-y-5" x-data="{ provider: '{{ old('provider', \App\Models\ChannelConnection::PROVIDER_SHOPIFY) }}' }">
        @csrf

        <section class="surface-card grid grid-cols-1 gap-4 p-4 sm:p-5 lg:grid-cols-2">
            <div class="min-w-0">
                <label for="name" class="field-label">{{ __('Connection name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" class="field-input" required autofocus>
                @error('name')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
            <div class="min-w-0">
                <label for="provider" class="field-label">{{ __('Provider') }}</label>
                <select id="provider" name="provider" x-model="provider" class="field-input" required>
                    @foreach ($providers as $value => $label)
                        <option value="{{ $value }}" @selected(old('provider', \App\Models\ChannelConnection::PROVIDER_SHOPIFY) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('provider')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section class="surface-card grid grid-cols-1 gap-4 p-4 sm:p-5 lg:grid-cols-2">
            <div class="min-w-0 space-y-4" x-show="provider === 'shopify'">
                <div>
                    <label for="shop_domain" class="field-label">{{ __('Shopify domain') }}</label>
                    <input id="shop_domain" name="credentials[shop_domain]" type="text" value="{{ old('credentials.shop_domain') }}" placeholder="example-store.myshopify.com" class="field-input" autocomplete="off">
                    @error('credentials.shop_domain')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="access_token" class="field-label">{{ __('Admin API access token') }}</label>
                    <input id="access_token" name="credentials[access_token]" type="password" class="field-input" autocomplete="off">
                    @error('credentials.access_token')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="location_id" class="field-label">{{ __('Inventory location ID') }}</label>
                    <input id="location_id" name="credentials[location_id]" type="text" value="{{ old('credentials.location_id') }}" class="field-input" autocomplete="off">
                    @error('credentials.location_id')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="min-w-0 space-y-4" x-show="provider === 'woocommerce'">
                <div>
                    <label for="site_url" class="field-label">{{ __('WooCommerce site URL') }}</label>
                    <input id="site_url" name="credentials[site_url]" type="url" value="{{ old('credentials.site_url') }}" placeholder="https://shop.example.com" class="field-input" autocomplete="off">
                    @error('credentials.site_url')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="consumer_key" class="field-label">{{ __('Consumer key') }}</label>
                    <input id="consumer_key" name="credentials[consumer_key]" type="password" class="field-input" autocomplete="off">
                    @error('credentials.consumer_key')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="consumer_secret" class="field-label">{{ __('Consumer secret') }}</label>
                    <input id="consumer_secret" name="credentials[consumer_secret]" type="password" class="field-input" autocomplete="off">
                    @error('credentials.consumer_secret')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        @error('credentials')
            <p class="text-sm font-medium text-destructive">{{ $message }}</p>
        @enderror

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">{{ __('Connect channel') }}</button>
            <a href="{{ route('channels.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
