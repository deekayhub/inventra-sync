<x-form-error-summary :errors="$errors" :id-map="['name' => 'name', 'slug' => 'slug', 'description' => 'description']" />

<form method="POST" action="{{ $action }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div class="min-w-0">
        <label for="name" class="field-label">{{ __('Category name') }}</label>
        <input id="name" name="name" type="text" value="{{ old('name', $category?->name) }}" class="field-input @error('name') field-input-error @enderror" required>
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div class="min-w-0">
        <label for="slug" class="field-label">{{ __('Slug') }}</label>
        <input id="slug" name="slug" type="text" value="{{ old('slug', $category?->slug) }}" class="field-input @error('slug') field-input-error @enderror">
        <p class="mt-1.5 text-xs text-muted-foreground">{{ __('Leave blank to generate the slug from the name.') }}</p>
        <x-input-error :messages="$errors->get('slug')" />
    </div>

    <div class="min-w-0 sm:col-span-2">
        <label for="description" class="field-label">{{ __('Description') }}</label>
        <textarea id="description" name="description" rows="2" class="field-input @error('description') field-input-error @enderror">{{ old('description', $category?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <label class="col-span-full flex items-center gap-2 text-sm font-medium text-foreground">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category?->is_active ?? true)) class="h-4 w-4 rounded border-line text-primary focus:ring-primary">
        {{ __('Active') }}
    </label>

    <div class="col-span-full flex flex-wrap items-center gap-3 border-t border-line pt-4">
        <button type="submit" class="btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('categories.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
