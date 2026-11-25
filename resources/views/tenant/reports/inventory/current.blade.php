<x-app-layout :title="__('Current inventory')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Current inventory') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Review on-hand, reserved, and available quantities across warehouses.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        @include('tenant.reports.inventory.partials.stock-table', [
            'title' => __('Stock on hand'),
            'description' => __('Every stock row for this workspace, filtered by warehouse or product.'),
            'report' => 'current-inventory',
        ])
    </div>
</x-app-layout>
