<x-app-layout :title="__('New product')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('New product') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Add product details and at least one variant SKU.') }}</p>
            </div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" />
                {{ __('Back to products') }}
            </a>
        </div>
    </x-slot>

    <section class="surface-card sm:p-7">
        @include('tenant.products.partials.form', [
            'action' => route('products.store'),
            'method' => 'POST',
            'product' => null,
            'submitLabel' => __('Create product'),
        ])
    </section>
</x-app-layout>
