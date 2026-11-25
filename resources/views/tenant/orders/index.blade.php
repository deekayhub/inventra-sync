<x-app-layout :title="__('Orders')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Sales') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Orders') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Review imported orders, fulfilments, cancellations, and returns across connected channels.') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="surface-card p-4 sm:p-5">
            <form method="GET" action="{{ route('orders.index') }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
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
                    <a href="{{ route('orders.index') }}" class="btn-secondary h-11 whitespace-nowrap">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($orders->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="clipboard" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No orders found') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Orders appear here after connected channels send webhooks.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Imported orders') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Order') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Customer') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Total') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Ordered') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($orders as $order)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $order->external_order_id }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $order->connection?->name ?? __('Unknown channel') }}</td>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $order->customer_name ?? __('Guest') }}</span>
                                        <span class="mt-0.5 block text-xs text-muted-foreground">{{ $order->customer_email }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-foreground">{{ $order->currency }} {{ $order->total_amount }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ $statuses[$order->status] ?? $order->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $order->ordered_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('orders.show', $order) }}" class="btn-quiet">{{ __('View') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
