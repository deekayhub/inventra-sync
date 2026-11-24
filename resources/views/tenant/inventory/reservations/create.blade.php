<x-app-layout :title="__('New reservation')">
    <x-slot name="header">
        <div>
            <p class="eyebrow">{{ __('Inventory') }}</p>
            <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('New reservation') }}</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Hold available stock against an external order reference. Reusing a reference is idempotent.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <section class="surface-card sm:p-6">
            <x-form-error-summary :errors="$errors" :id-map="['warehouse_id' => 'warehouse_id', 'product_variant_id' => 'product_variant_id', 'quantity' => 'quantity', 'external_reference' => 'external_reference', 'expires_at' => 'expires_at']" />

            <form method="POST" action="{{ route('inventory.reservations.store') }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @csrf

                <div class="min-w-0">
                    <label for="warehouse_id" class="field-label">{{ __('Warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="field-input @error('warehouse_id') field-input-error @enderror" required>
                        <option value="">{{ __('Select a warehouse') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('warehouse_id')" />
                </div>

                <div class="min-w-0">
                    <label for="product_variant_id" class="field-label">{{ __('Product variant') }}</label>
                    <select id="product_variant_id" name="product_variant_id" class="field-input @error('product_variant_id') field-input-error @enderror" required>
                        <option value="">{{ __('Select a variant') }}</option>
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->id }}" @selected(old('product_variant_id') == $variant->id)>{{ $variant->product?->name }} — {{ $variant->sku }}{{ $variant->title ? ' ('.$variant->title.')' : '' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('product_variant_id')" />
                </div>

                <div class="min-w-0">
                    <label for="quantity" class="field-label">{{ __('Quantity') }}</label>
                    <input id="quantity" name="quantity" type="number" min="1" step="1" value="{{ old('quantity', 1) }}" class="field-input @error('quantity') field-input-error @enderror" required>
                    <x-input-error :messages="$errors->get('quantity')" />
                </div>

                <div class="min-w-0">
                    <label for="expires_at" class="field-label">{{ __('Expires at') }}</label>
                    <input id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="field-input @error('expires_at') field-input-error @enderror">
                    <x-input-error :messages="$errors->get('expires_at')" />
                </div>

                <div class="min-w-0 sm:col-span-2">
                    <label for="external_reference" class="field-label">{{ __('External reference') }}</label>
                    <input id="external_reference" name="external_reference" type="text" value="{{ old('external_reference', $externalReference) }}" class="field-input @error('external_reference') field-input-error @enderror" required>
                    <p class="mt-1.5 text-xs text-muted-foreground">{{ __('Use the external order identifier. Reusing it will not create a duplicate reservation.') }}</p>
                    <x-input-error :messages="$errors->get('external_reference')" />
                </div>

                <div class="col-span-full flex flex-wrap items-center gap-3 border-t border-line pt-4">
                    <button type="submit" class="btn-primary">{{ __('Reserve stock') }}</button>
                    <a href="{{ route('inventory.reservations.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
