@php
    $links = [
        ['label' => __('Current inventory'), 'route' => 'reports.inventory.current', 'pattern' => 'reports.inventory.current'],
        ['label' => __('Low stock'), 'route' => 'reports.inventory.low-stock', 'pattern' => 'reports.inventory.low-stock'],
        ['label' => __('Out of stock'), 'route' => 'reports.inventory.out-of-stock', 'pattern' => 'reports.inventory.out-of-stock'],
        ['label' => __('Inventory movement'), 'route' => 'reports.inventory.movements', 'pattern' => 'reports.inventory.movements'],
        ['label' => __('Orders by channel'), 'route' => 'reports.orders-by-channel', 'pattern' => 'reports.orders-by-channel'],
        ['label' => __('Channel status'), 'route' => 'reports.channels', 'pattern' => 'reports.channels'],
        ['label' => __('Reconciliation'), 'route' => 'reports.reconciliation.index', 'pattern' => 'reports.reconciliation.*'],
        ['label' => __('Sync failures'), 'route' => 'reports.failures.index', 'pattern' => 'reports.failures.*'],
    ];
@endphp

<nav aria-label="{{ __('Reports') }}" class="flex flex-wrap gap-2">
    @foreach ($links as $link)
        <a
            href="{{ route($link['route']) }}"
            @class([
                'rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                'border-primary/30 bg-primary/10 text-primary' => request()->routeIs($link['pattern']),
                'border-line bg-card text-muted-foreground hover:border-zinc-300 hover:text-foreground' => ! request()->routeIs($link['pattern']),
            ])
        >
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
