<x-form-error-summary
    :errors="$errors"
    :id-map="[
        'name' => 'name',
        'description' => 'description',
        'product_category_id' => 'product_category_id',
        'type' => 'type',
        'status' => 'status',
        'low_stock_threshold' => 'low_stock_threshold',
        'buffer_stock' => 'buffer_stock',
        'variants' => 'variants',
        'variants.*.sku' => 'variants',
    ]"
/>

<form method="POST" action="{{ $action }}" class="form-grid" data-product-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-field">
        <label for="name" class="field-label">{{ __('Product name') }}</label>
        <input id="name" name="name" type="text" value="{{ old('name', $product?->name) }}" class="field-input @error('name') field-input-error @enderror" required>
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="form-field">
        <label for="product_category_id" class="field-label">{{ __('Category') }}</label>
        <select id="product_category_id" name="product_category_id" class="field-input @error('product_category_id') field-input-error @enderror">
            <option value="">{{ __('No category') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('product_category_id', $product?->product_category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('product_category_id')" />
    </div>

    <div class="form-field form-field-full">
        <label for="description" class="field-label">{{ __('Description') }}</label>
        <textarea id="description" name="description" rows="3" class="field-input @error('description') field-input-error @enderror">{{ old('description', $product?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div class="form-field">
        <label for="type" class="field-label">{{ __('Type') }}</label>
        <select id="type" name="type" class="field-input @error('type') field-input-error @enderror">
            @foreach ([\App\Models\Product::TYPE_SIMPLE, \App\Models\Product::TYPE_VARIABLE] as $type)
                <option value="{{ $type }}" @selected(old('type', $product?->type ?? \App\Models\Product::TYPE_SIMPLE) === $type)>{{ __(ucfirst($type)) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" />
    </div>

    <div class="form-field">
        <label for="status" class="field-label">{{ __('Status') }}</label>
        <select id="status" name="status" class="field-input @error('status') field-input-error @enderror">
            @foreach ([\App\Models\Product::STATUS_DRAFT, \App\Models\Product::STATUS_ACTIVE, \App\Models\Product::STATUS_ARCHIVED] as $status)
                <option value="{{ $status }}" @selected(old('status', $product?->status ?? \App\Models\Product::STATUS_DRAFT) === $status)>{{ __(ucfirst($status)) }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" />
    </div>

    <div class="form-field">
        <label for="low_stock_threshold" class="field-label">{{ __('Low stock threshold') }}</label>
        <input id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold', $product?->low_stock_threshold ?? 5) }}" class="field-input @error('low_stock_threshold') field-input-error @enderror">
        <x-input-error :messages="$errors->get('low_stock_threshold')" />
    </div>

    <div class="form-field">
        <label for="buffer_stock" class="field-label">{{ __('Buffer stock') }}</label>
        <input id="buffer_stock" name="buffer_stock" type="number" min="0" value="{{ old('buffer_stock', $product?->buffer_stock ?? 0) }}" class="field-input @error('buffer_stock') field-input-error @enderror">
        <x-input-error :messages="$errors->get('buffer_stock')" />
    </div>

    <div class="form-field-full space-y-3 border-t border-line pt-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="field-label mb-1">{{ __('Variants') }}</p>
                <p class="text-xs text-muted-foreground">{{ __('Each variant needs a unique SKU in this workspace.') }}</p>
            </div>
            <button type="button" class="btn-secondary" data-add-variant>{{ __('Add variant') }}</button>
        </div>

        <div class="space-y-3" data-variants>
            @php
                $variants = old('variants', $product?->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                    'title' => $variant->title,
                    'is_primary' => $variant->is_primary,
                ])->all() ?? [['sku' => '', 'barcode' => '', 'title' => '', 'is_primary' => true]]);
            @endphp
            @foreach ($variants as $index => $variant)
                <div class="grid gap-3 rounded-xl border border-line bg-muted/20 p-4 md:grid-cols-[1.2fr_1fr_1fr_auto]" data-variant-row>
                    @if (filled($variant['id'] ?? null))
                        <input type="hidden" name="variants[{{ $loop->index }}][id]" value="{{ $variant['id'] }}">
                    @endif
                    <input type="hidden" name="variants[{{ $loop->index }}][is_primary]" value="{{ ($variant['is_primary'] ?? false) ? 1 : 0 }}" data-primary-hidden>
                    <div class="form-field">
                        <label for="variant-sku-{{ $loop->index }}" class="field-label">{{ __('SKU') }}</label>
                        <input id="variant-sku-{{ $loop->index }}" name="variants[{{ $loop->index }}][sku]" type="text" value="{{ $variant['sku'] ?? '' }}" class="field-input" required>
                    </div>
                    <div class="form-field">
                        <label for="variant-title-{{ $loop->index }}" class="field-label">{{ __('Title') }}</label>
                        <input id="variant-title-{{ $loop->index }}" name="variants[{{ $loop->index }}][title]" type="text" value="{{ $variant['title'] ?? '' }}" class="field-input">
                    </div>
                    <div class="form-field">
                        <label for="variant-barcode-{{ $loop->index }}" class="field-label">{{ __('Barcode') }}</label>
                        <input id="variant-barcode-{{ $loop->index }}" name="variants[{{ $loop->index }}][barcode]" type="text" value="{{ $variant['barcode'] ?? '' }}" class="field-input">
                    </div>
                    <label class="flex items-end gap-2 pb-2 text-xs font-medium text-foreground">
                        <input type="radio" name="primary_variant" value="{{ $loop->index }}" @checked(($variant['is_primary'] ?? false) || $loop->first) data-primary-radio data-primary-index="{{ $loop->index }}">
                        {{ __('Primary') }}
                    </label>
                </div>
            @endforeach
        </div>

        <template data-variant-template>
            <div class="grid gap-3 rounded-xl border border-line bg-muted/20 p-4 md:grid-cols-[1.2fr_1fr_1fr_auto]" data-variant-row>
                <input type="hidden" name="variants[__INDEX__][is_primary]" value="0" data-primary-hidden>
                <div class="form-field">
                    <label class="field-label">{{ __('SKU') }}</label>
                    <input name="variants[__INDEX__][sku]" type="text" class="field-input" required>
                </div>
                <div class="form-field">
                    <label class="field-label">{{ __('Title') }}</label>
                    <input name="variants[__INDEX__][title]" type="text" class="field-input">
                </div>
                <div class="form-field">
                    <label class="field-label">{{ __('Barcode') }}</label>
                    <input name="variants[__INDEX__][barcode]" type="text" class="field-input">
                </div>
                <label class="flex items-end gap-2 pb-2 text-xs font-medium text-foreground">
                    <input type="radio" name="primary_variant" value="__INDEX__" data-primary-radio data-primary-index="__INDEX__">
                    {{ __('Primary') }}
                </label>
            </div>
        </template>

        <x-input-error :messages="$errors->get('variants')" />
        <x-input-error :messages="$errors->get('variants.*.sku')" />
    </div>

    <div class="form-field-full flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>

@pushOnce('scripts')
    <script>
        (() => {
            const container = document.querySelector('[data-variants]');
            const template = document.querySelector('[data-variant-template]');
            const addButton = document.querySelector('[data-add-variant]');

            if (! container || ! template || ! addButton) {
                return;
            }

            addButton.addEventListener('click', () => {
                const index = container.querySelectorAll('[data-variant-row]').length;
                const markup = template.innerHTML.replaceAll('__INDEX__', String(index));

                container.insertAdjacentHTML('beforeend', markup);
            });

            container.addEventListener('change', (event) => {
                if (! event.target.matches('[data-primary-radio]')) {
                    return;
                }

                container.querySelectorAll('[data-primary-hidden]').forEach((input) => {
                    input.value = '0';
                });

                const selected = container.querySelector(`[data-primary-hidden][name="variants[${event.target.dataset.primaryIndex}][is_primary]"]`);

                if (selected) {
                    selected.value = '1';
                }
            });
        })();
    </script>
@endpushOnce
