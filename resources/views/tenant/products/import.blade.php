<x-app-layout :title="__('Import products')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Import products') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Upload a CSV with name and sku columns. Rows are processed in the background.') }}</p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" />
                {{ __('Back to products') }}
            </a>
        </div>
    </x-slot>

    <section class="surface-card sm:p-6">
        <x-form-error-summary :errors="$errors" :id-map="['file' => 'file']" />

        <form method="POST" action="{{ route('products.import.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @csrf
            <div class="min-w-0 sm:col-span-2">
                <label for="file" class="field-label">{{ __('CSV file') }}</label>
                <input id="file" name="file" type="file" accept=".csv,text/csv" class="field-input" required>
                <p class="mt-1.5 text-xs text-muted-foreground">{{ __('Maximum size: 10 MB. Required columns: name, sku.') }}</p>
                <x-input-error :messages="$errors->get('file')" />
            </div>
            <div class="col-span-full flex flex-wrap items-center gap-3 border-t border-line pt-4">
                <button type="submit" class="btn-primary">{{ __('Queue import') }}</button>
                <a href="{{ route('products.index') }}" class="btn-secondary">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</x-app-layout>
