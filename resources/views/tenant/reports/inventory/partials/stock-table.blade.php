@php
    $filterQuery = array_filter([
        'warehouse_id' => $warehouseId,
        'search' => $search,
    ], fn ($value): bool => $value !== null && $value !== '');
@endphp

<section class="glass-card overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
        <div>
            <h3 class="text-sm font-semibold text-foreground">{{ $title }}</h3>
            <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ $description }}</p>
        </div>
        <a href="{{ route('reports.export', array_merge(['report' => $report], $filterQuery)) }}" class="btn-quiet">
            <x-icon name="clipboard" class="h-4 w-4" />
            {{ __('Export CSV') }}
        </a>
    </div>

    <div class="grid gap-3 border-b border-line px-5 py-4 sm:grid-cols-3">
        <div>
            <p class="eyebrow">{{ __('On hand') }}</p>
            <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->on_hand }}</p>
        </div>
        <div>
            <p class="eyebrow">{{ __('Reserved') }}</p>
            <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->reserved }}</p>
        </div>
        <div>
            <p class="eyebrow">{{ __('Available to promise') }}</p>
            <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->available }}</p>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
        <label class="flex min-w-48 flex-1 flex-col gap-1.5">
            <span class="text-xs font-semibold text-foreground">{{ __('Search') }}</span>
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="{{ __('SKU, variant, or product') }}"
                class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
            >
        </label>
        <label class="flex min-w-44 flex-col gap-1.5">
            <span class="text-xs font-semibold text-foreground">{{ __('Warehouse') }}</span>
            <select name="warehouse_id" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">{{ __('All warehouses') }}</option>
                @foreach ($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
    </form>

    @if ($stocks->isEmpty())
        <div class="px-5 py-16 text-center text-sm text-muted-foreground">{{ __('No stock rows match these filters.') }}</div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <caption class="sr-only">{{ $title }}</caption>
                <thead>
                    <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                        <th scope="col" class="px-5 py-3 font-semibold">{{ __('SKU') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ __('Product') }}</th>
                        <th scope="col" class="px-5 py-3 font-semibold">{{ __('Warehouse') }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('On hand') }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Reserved') }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Available') }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Channel available') }}</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Threshold') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($stocks as $stock)
                        <tr class="transition-colors hover:bg-muted/40">
                            <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $stock->variant?->sku ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="font-semibold text-foreground">{{ $stock->variant?->product?->name ?? $stock->variant?->title ?? '—' }}</span>
                                <span class="mt-0.5 block text-xs text-muted-foreground">{{ $stock->variant?->title }}</span>
                            </td>
                            <td class="px-5 py-3 text-xs text-muted-foreground">{{ $stock->warehouse?->name ?? '—' }}</td>
                            <td class="px-5 py-3 text-right text-xs text-foreground">{{ $stock->on_hand }}</td>
                            <td class="px-5 py-3 text-right text-xs text-foreground">{{ $stock->reserved }}</td>
                            <td class="px-5 py-3 text-right text-xs font-semibold text-foreground">{{ $stock->available_stock }}</td>
                            <td class="px-5 py-3 text-right text-xs text-foreground">{{ $stock->channel_available_stock }}</td>
                            <td class="px-5 py-3 text-right text-xs text-muted-foreground">{{ $stock->low_stock_threshold }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{ $stocks->links() }}
