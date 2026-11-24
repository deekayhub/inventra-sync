<x-app-layout :title="__('Reservations')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Inventory') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Reservations') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Reserve stock for orders, then release, commit, or expire each reservation exactly once.') }}</p>
            </div>
            @can('inventory.manage')
                <a href="{{ route('inventory.reservations.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New reservation') }}
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
            <form method="GET" action="{{ route('inventory.reservations.index') }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <div class="min-w-0">
                    <label for="status" class="field-label">{{ __('Status') }}</label>
                    <select id="status" name="status" class="field-input">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <button type="submit" class="btn-primary h-11 whitespace-nowrap">{{ __('Search') }}</button>
                    <a href="{{ route('inventory.reservations.index') }}" class="btn-secondary h-11 whitespace-nowrap">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($reservations->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="clock" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No reservations found') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Create a reservation to hold stock for an external order.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Inventory reservations') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Reference') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Variant') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Warehouse') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Expires') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($reservations as $reservation)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $reservation->external_reference }}</td>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $reservation->variant?->product?->name ?? __('Unknown product') }}</span>
                                        <span class="block font-mono text-xs text-muted-foreground">{{ $reservation->variant?->sku }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $reservation->warehouse?->name }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-foreground">{{ $reservation->quantity }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ $statuses[$reservation->status] ?? $reservation->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $reservation->expires_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($reservation->status === $reservedStatus)
                                                <form method="POST" action="{{ route('inventory.reservations.commit', $reservation) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-quiet">{{ __('Commit') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('inventory.reservations.release', $reservation) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-quiet">{{ __('Release') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('inventory.reservations.expire', $reservation) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-quiet text-destructive hover:text-destructive">{{ __('Expire') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $reservations->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
