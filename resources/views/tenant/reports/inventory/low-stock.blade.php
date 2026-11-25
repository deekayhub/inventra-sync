<x-app-layout :title="__('Low stock')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Low stock') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Rows at or below their threshold but still available to promise.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        @include('tenant.reports.inventory.partials.stock-table', [
            'title' => __('Low stock rows'),
            'description' => __('Reorder these variants before they run out.'),
            'report' => 'low-stock',
        ])
    </div>
</x-app-layout>
