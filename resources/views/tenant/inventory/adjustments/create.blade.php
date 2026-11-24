<x-app-layout :title="__('Adjust stock')">
    <x-slot name="header">
        <div>
            <p class="eyebrow">{{ __('Inventory') }}</p>
            <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Adjust stock') }}</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Apply a signed quantity change. Every adjustment is recorded in the inventory ledger.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="surface-card p-5">
            <x-form-error-summary :errors="$errors" :id-map="['warehouse_id' => 'warehouse_id', 'product_variant_id' => 'product_variant_id', 'quantity' => 'quantity', 'reason' => 'reason', 'reference' => 'reference']" />

            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="form-grid">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

                <div class="form-field">
                    <label for="warehouse_id" class="field-label">{{ __('Warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="field-input @error('warehouse_id') field-input-error @enderror" required>
                        <option value="">{{ __('Select a warehouse') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('warehouse_id')" />
                </div>

                <div class="form-field">
                    <label for="product_variant_id" class="field-label">{{ __('Product variant') }}</label>
                    <select id="product_variant_id" name="product_variant_id" class="field-input @error('product_variant_id') field-input-error @enderror" required>
                        <option value="">{{ __('Select a variant') }}</option>
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->id }}" @selected(old('product_variant_id') == $variant->id)>{{ $variant->product?->name }} — {{ $variant->sku }}{{ $variant->title ? ' ('.$variant->title.')' : '' }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('product_variant_id')" />
                </div>

                <div class="form-field">
                    <label for="quantity" class="field-label">{{ __('Quantity change') }}</label>
                    <input id="quantity" name="quantity" type="number" step="1" value="{{ old('quantity') }}" class="field-input @error('quantity') field-input-error @enderror" placeholder="{{ __('Use a negative number for deductions') }}" required>
                    <p class="mt-1.5 text-xs text-muted-foreground">{{ __('Example: 25 to receive stock, -3 for damaged or lost stock.') }}</p>
                    <x-input-error :messages="$errors->get('quantity')" />
                </div>

                <div class="form-field">
                    <label for="reference" class="field-label">{{ __('Reference') }}</label>
                    <input id="reference" name="reference" type="text" value="{{ old('reference') }}" class="field-input @error('reference') field-input-error @enderror" placeholder="{{ __('PO, adjustment batch, or note') }}">
                    <x-input-error :messages="$errors->get('reference')" />
                </div>

                <div class="form-field form-field-full">
                    <label for="reason" class="field-label">{{ __('Reason') }}</label>
                    <input id="reason" name="reason" type="text" value="{{ old('reason') }}" class="field-input @error('reason') field-input-error @enderror" required>
                    <x-input-error :messages="$errors->get('reason')" />
                </div>

                <div class="form-field-full flex flex-wrap items-center gap-3 border-t border-line pt-5">
                    <button type="submit" class="btn-primary">{{ __('Record adjustment') }}</button>
                    <a href="{{ route('inventory.stocks.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
