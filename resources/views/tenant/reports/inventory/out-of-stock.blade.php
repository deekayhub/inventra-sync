<x-app-layout :title="__('Out of stock')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Out of stock') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Rows with no available quantity to promise.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        @include('tenant.reports.inventory.partials.stock-table', [
            'title' => __('Out of stock rows'),
            'description' => __('These variants cannot fulfill new reservations.'),
            'report' => 'out-of-stock',
        ])
    </div>
</x-app-layout>
