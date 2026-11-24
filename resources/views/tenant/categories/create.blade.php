<x-app-layout :title="__('New category')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('New category') }}</h2>
            </div>
            <a href="{{ route('categories.index') }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary">
                <x-icon name="chevron-right" class="h-3.5 w-3.5 rotate-180" />
                {{ __('Back to categories') }}
            </a>
        </div>
    </x-slot>

    <section class="surface-card sm:p-7">
        @include('tenant.categories.partials.form', [
            'action' => route('categories.store'),
            'method' => 'POST',
            'category' => null,
            'submitLabel' => __('Create category'),
        ])
    </section>
</x-app-layout>
