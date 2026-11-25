<x-app-layout :title="__('Map channel listing')">
    <x-slot name="header">
        <div>
            <p class="eyebrow">{{ __('Integrations') }}</p>
            <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Map channel listing') }}</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Choose the internal variant that represents this external listing and control its sync buffer.') }}</p>
        </div>
    </x-slot>

    <form method="POST" action="{{ route('channels.listings.update', $listing) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <section class="surface-card grid grid-cols-1 gap-4 p-4 sm:p-5 lg:grid-cols-2">
            <div>
                <p class="eyebrow">{{ __('External listing') }}</p>
                <p class="mt-2 text-sm font-semibold text-foreground">{{ $listing->title }}</p>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ $listing->external_id }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ $listing->connection?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="eyebrow">{{ __('Current mapping') }}</p>
                <p class="mt-2 text-sm font-semibold text-foreground">{{ $mapping?->variant?->product?->name ?? __('Unmapped') }}</p>
                <p class="mt-0.5 font-mono text-xs text-muted-foreground">{{ $mapping?->variant?->sku ?? '—' }}</p>
            </div>
        </section>

        <section class="surface-card grid grid-cols-1 gap-4 p-4 sm:p-5 lg:grid-cols-2">
            <div class="min-w-0">
                <label for="product_variant_id" class="field-label">{{ __('Internal variant') }}</label>
                <select id="product_variant_id" name="product_variant_id" class="field-input" required>
                    <option value="">{{ __('Select a variant') }}</option>
                    @foreach ($variants as $variantId => $variantLabel)
                        <option value="{{ $variantId }}" @selected((string) old('product_variant_id', $mapping?->product_variant_id) === (string) $variantId)>{{ $variantLabel }}</option>
                    @endforeach
                </select>
                @error('product_variant_id')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
            <div class="min-w-0">
                <label for="buffer_stock" class="field-label">{{ __('Safety buffer') }}</label>
                <input id="buffer_stock" name="buffer_stock" type="number" min="0" max="1000000" value="{{ old('buffer_stock', $setting?->buffer_stock ?? 0) }}" class="field-input" required>
                @error('buffer_stock')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-3 sm:col-span-2">
                <input id="paused" name="paused" type="checkbox" value="1" class="h-4 w-4 rounded border-line text-primary focus:ring-primary" @checked(old('paused', $setting?->paused ?? false))>
                <label for="paused" class="text-sm font-medium text-foreground">{{ __('Pause sync for this listing') }}</label>
                @error('paused')
                    <p class="text-xs text-destructive">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">{{ __('Save mapping') }}</button>
            <a href="{{ route('channels.listings.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-app-layout>
