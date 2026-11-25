<x-app-layout :title="__('Inventory movement')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Inventory movement') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Trace quantity changes from the stock ledger.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        <section class="glass-card overflow-hidden">
            <div class="grid gap-3 border-b border-line px-5 py-4 sm:grid-cols-4">
                <div>
                    <p class="eyebrow">{{ __('Stock in') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->stock_in }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Stock out') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->stock_out }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Net change') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->net_change }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Movements') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->movement_count }}</p>
                </div>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
                <label class="flex min-w-48 flex-1 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('SKU, reason, or reference') }}" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                <label class="flex min-w-40 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Warehouse') }}</span>
                    <select name="warehouse_id" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All warehouses') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex min-w-40 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Movement') }}</span>
                    <select name="movement_type" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All types') }}</option>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}" @selected($movementType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex min-w-32 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('From') }}</span>
                    <input type="date" name="from" value="{{ $from }}" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                <label class="flex min-w-32 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('To') }}</span>
                    <input type="date" name="to" value="{{ $to }}" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
                <a href="{{ route('reports.export', array_filter(['report' => 'movements', 'warehouse_id' => $warehouseId, 'movement_type' => $movementType, 'from' => $from, 'to' => $to, 'search' => $search], fn ($value): bool => $value !== null && $value !== '')) }}" class="btn-quiet">{{ __('Export CSV') }}</a>
            </form>

            @if ($movements->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-muted-foreground">{{ __('No ledger movements match these filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Inventory movements') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Recorded') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Movement') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('SKU') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Warehouse') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Change') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('After') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Reason') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Actor') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($movements as $movement)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="whitespace-nowrap px-5 py-3 text-xs text-muted-foreground">{{ $movement->created_at?->diffForHumans() }}</td>
                                    <td class="px-5 py-3 text-xs font-semibold text-foreground">{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</td>
                                    <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $movement->variant?->sku ?? '—' }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $movement->warehouse?->name ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right text-xs font-semibold {{ $movement->quantity_change > 0 ? 'text-success' : 'text-caution' }}">{{ $movement->quantity_change > 0 ? '+' : '' }}{{ $movement->quantity_change }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $movement->quantity_after }}</td>
                                    <td class="max-w-xs px-5 py-3 text-xs text-muted-foreground">{{ $movement->reason }}<span class="mt-0.5 block font-mono text-[10px]">{{ $movement->reference }}</span></td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $movement->actor?->name ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $movements->links() }}
    </div>
</x-app-layout>
