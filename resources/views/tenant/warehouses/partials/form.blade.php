<x-form-error-summary :errors="$errors" :id-map="['name' => 'name', 'code' => 'code', 'address' => 'address']" />

<form method="POST" action="{{ $action }}" class="form-grid">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="form-field">
        <label for="name" class="field-label">{{ __('Warehouse name') }}</label>
        <input id="name" name="name" type="text" value="{{ old('name', $warehouse?->name) }}" class="field-input @error('name') field-input-error @enderror" required>
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="form-field">
        <label for="code" class="field-label">{{ __('Code') }}</label>
        <input id="code" name="code" type="text" value="{{ old('code', $warehouse?->code) }}" class="field-input @error('code') field-input-error @enderror" required>
        <p class="mt-1.5 text-xs text-muted-foreground">{{ __('Must be unique in this workspace.') }}</p>
        <x-input-error :messages="$errors->get('code')" />
    </div>

    <div class="form-field form-field-full">
        <label for="address" class="field-label">{{ __('Address') }}</label>
        <textarea id="address" name="address" rows="3" class="field-input @error('address') field-input-error @enderror">{{ old('address', $warehouse?->address) }}</textarea>
        <x-input-error :messages="$errors->get('address')" />
    </div>

    <div class="form-field-full flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-6">
        @if ($warehouse?->is_default)
            <input type="hidden" name="is_default" value="1">
            <label class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <input type="checkbox" checked disabled class="h-4 w-4 rounded border-line text-primary">
                {{ __('Default warehouse') }}
            </label>
        @else
            <label class="flex items-center gap-2 text-sm font-medium text-foreground">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', false)) class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
                {{ __('Set as default warehouse') }}
            </label>
        @endif

        <label class="flex items-center gap-2 text-sm font-medium text-foreground">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse?->is_active ?? true)) class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
            {{ __('Active') }}
        </label>
    </div>

    <div class="form-field-full flex flex-wrap items-center gap-3 border-t border-line pt-5">
        <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('warehouses.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
