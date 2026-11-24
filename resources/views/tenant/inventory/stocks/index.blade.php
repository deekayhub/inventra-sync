<x-app-layout :title="__('Stock levels')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Inventory') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Stock levels') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Review on-hand, reserved, and channel-available quantities by warehouse.') }}</p>
            </div>
            @can('inventory.adjust')
                <a href="{{ route('inventory.adjustments.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('Adjust stock') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="surface-card p-4 sm:p-5">
            <form method="GET" action="{{ route('inventory.stocks.index') }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_auto] lg:items-end">
                <div class="min-w-0">
                    <label for="warehouse_id" class="field-label">{{ __('Warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="field-input">
                        <option value="">{{ __('All warehouses') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="search" class="field-label">{{ __('Variant') }}</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="{{ __('SKU or title') }}" class="field-input">
                </div>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn-primary h-11 whitespace-nowrap">{{ __('Search') }}</button>
                    <a href="{{ route('inventory.stocks.index') }}" class="btn-secondary h-11 whitespace-nowrap">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($stocks->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="box" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No stock records found') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Adjust a variant to create its first stock record.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Inventory stock levels') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Product') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Warehouse') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('On hand') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Reserved') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Available') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Channel available') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($stocks as $stock)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $stock->variant?->product?->name ?? __('Unknown product') }}</span>
                                        <span class="block font-mono text-xs text-muted-foreground">{{ $stock->variant?->sku }}</span>
                                        @if ($stock->variant?->title)
                                            <span class="block text-xs text-muted-foreground">{{ $stock->variant->title }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $stock->warehouse?->name }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-foreground">{{ $stock->on_hand }}</td>
                                    <td class="px-5 py-3 text-right text-muted-foreground">{{ $stock->reserved }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-foreground">
                                        {{ $stock->available_stock }}
                                        @if ($stock->available_stock <= $stock->low_stock_threshold)
                                            <span class="ml-1 rounded-md border border-warning/25 bg-warning/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-warning">{{ __('Low') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right text-muted-foreground">{{ $stock->channel_available_stock }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $stocks->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
