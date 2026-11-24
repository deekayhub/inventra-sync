<x-app-layout :title="__('Inventory ledger')">
    <x-slot name="header">
        <div>
            <p class="eyebrow">{{ __('Inventory') }}</p>
            <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Inventory ledger') }}</h2>
            <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Immutable history of every stock and reservation change in this workspace.') }}</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="surface-card p-5">
            <form method="GET" action="{{ route('inventory.ledger.index') }}" class="grid gap-4 md:grid-cols-4">
                <div class="form-field">
                    <label for="warehouse_id" class="field-label">{{ __('Warehouse') }}</label>
                    <select id="warehouse_id" name="warehouse_id" class="field-input">
                        <option value="">{{ __('All warehouses') }}</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="movement_type" class="field-label">{{ __('Movement type') }}</label>
                    <select id="movement_type" name="movement_type" class="field-input">
                        <option value="">{{ __('All movements') }}</option>
                        @foreach ($movementTypes as $value => $label)
                            <option value="{{ $value }}" @selected($movementType === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label for="search" class="field-label">{{ __('Search') }}</label>
                    <input id="search" name="search" type="search" value="{{ $search }}" placeholder="{{ __('SKU, reason, or reference') }}" class="field-input">
                </div>
                <div class="form-field md:self-end">
                    <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
                </div>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($movements->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="chart" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No ledger entries found') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Stock adjustments and reservations will appear here.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Inventory ledger entries') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('When') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Variant') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Type') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Change') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('On hand after') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Reserved after') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Details') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($movements as $movement)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $movement->variant?->product?->name ?? __('Unknown product') }}</span>
                                        <span class="block font-mono text-xs text-muted-foreground">{{ $movement->variant?->sku }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ $movementTypes[$movement->movement_type] ?? $movement->movement_type }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold {{ $movement->quantity_change < 0 ? 'text-destructive' : 'text-foreground' }}">{{ $movement->quantity_change > 0 ? '+'.$movement->quantity_change : $movement->quantity_change }}</td>
                                    <td class="px-5 py-3 text-right text-muted-foreground">{{ $movement->quantity_after }}</td>
                                    <td class="px-5 py-3 text-right text-muted-foreground">{{ $movement->reserved_after }}</td>
                                    <td class="px-5 py-3">
                                        <span class="block text-xs text-foreground">{{ $movement->reason }}</span>
                                        @if ($movement->reference)
                                            <span class="block font-mono text-xs text-muted-foreground">{{ $movement->reference }}</span>
                                        @endif
                                        @if ($movement->actor)
                                            <span class="block text-xs text-muted-foreground">{{ $movement->actor->name }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $movements->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
