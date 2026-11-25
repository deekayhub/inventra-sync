<x-app-layout :title="__('Order :order', ['order' => $order->external_order_id])">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Orders') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ $order->external_order_id }}</h2>
                <p class="mt-1.5 text-sm text-muted-foreground">{{ $order->connection?->name ?? __('Unknown channel') }} · {{ $statuses[$order->status] ?? $order->status }}</p>
            </div>
            <a href="{{ route('orders.index') }}" class="btn-secondary">{{ __('Back to orders') }}</a>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-destructive/25 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="surface-card grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Customer') }}</p>
                <p class="mt-1.5 font-semibold text-foreground">{{ $order->customer_name ?? __('Guest') }}</p>
                <p class="text-xs text-muted-foreground">{{ $order->customer_email }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Total') }}</p>
                <p class="mt-1.5 font-semibold text-foreground">{{ $order->currency }} {{ $order->total_amount }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Ordered at') }}</p>
                <p class="mt-1.5 text-sm text-foreground">{{ $order->ordered_at?->format('Y-m-d H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Imported at') }}</p>
                <p class="mt-1.5 text-sm text-foreground">{{ $order->imported_at?->format('Y-m-d H:i') ?? '—' }}</p>
            </div>
        </section>

        <section class="glass-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold text-foreground">{{ __('Order items') }}</h3>
                @can('orders.fulfill')
                    <form method="POST" action="{{ route('orders.fulfil', $order) }}">
                        @csrf
                        <button type="submit" class="btn-primary">{{ __('Fulfil all') }}</button>
                    </form>
                @endcan
            </div>

            @if ($order->items->isEmpty())
                <p class="px-5 py-10 text-center text-sm text-muted-foreground">{{ __('This order has no items.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Order items') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Item') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Qty') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Fulfilled') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Cancelled') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Returned') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Price') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($order->items as $item)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $item->title_snapshot }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $item->sku_snapshot ?? $item->external_item_id }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-foreground">{{ $item->quantity }}</td>
                                    <td class="px-5 py-3 text-right text-foreground">{{ $item->fulfilled_quantity }}</td>
                                    <td class="px-5 py-3 text-right text-foreground">{{ $item->cancelled_quantity }}</td>
                                    <td class="px-5 py-3 text-right text-foreground">{{ $item->returned_quantity }}</td>
                                    <td class="px-5 py-3 text-right text-foreground">{{ $order->currency }} {{ $item->price_snapshot }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @can('orders.manage')
            <section class="surface-card grid grid-cols-1 gap-5 p-5 lg:grid-cols-2">
                <form method="POST" action="{{ route('orders.cancel', $order) }}" class="space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-foreground">{{ __('Cancel order') }}</h3>
                    <input type="text" name="reason" maxlength="500" placeholder="{{ __('Cancellation reason') }}" class="field-input" />
                    <button type="submit" class="btn-secondary">{{ __('Cancel order') }}</button>
                </form>

                <form method="POST" action="{{ route('orders.returns.store', $order) }}" class="space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-foreground">{{ __('Record return') }}</h3>
                    <select name="order_item_id" class="field-input" required>
                        @foreach ($order->items as $item)
                            <option value="{{ $item->id }}">{{ $item->title_snapshot }}</option>
                        @endforeach
                    </select>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="quantity" min="1" value="1" class="field-input" required />
                        <select name="condition" class="field-input" required>
                            @foreach ($conditions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="text" name="reason" maxlength="500" placeholder="{{ __('Return reason') }}" class="field-input" />
                    <button type="submit" class="btn-primary">{{ __('Record return') }}</button>
                </form>
            </section>
        @endcan

        <section class="surface-card grid grid-cols-1 gap-5 p-5 lg:grid-cols-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ __('Fulfilments') }}</h3>
                <ul class="mt-3 space-y-2 text-xs text-muted-foreground">
                    @forelse ($order->fulfilments as $fulfilment)
                        <li>#{{ $fulfilment->id }} · {{ $fulfilment->quantity }} · {{ $fulfilment->status }}</li>
                    @empty
                        <li>{{ __('None') }}</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ __('Shipments') }}</h3>
                <ul class="mt-3 space-y-2 text-xs text-muted-foreground">
                    @forelse ($order->shipments as $shipment)
                        <li>{{ $shipment->carrier ?: __('Carrier pending') }} · {{ $shipment->tracking_number ?: __('No tracking') }}</li>
                    @empty
                        <li>{{ __('None') }}</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-foreground">{{ __('Cancellations and returns') }}</h3>
                <ul class="mt-3 space-y-2 text-xs text-muted-foreground">
                    @forelse ($order->cancellations as $cancellation)
                        <li>{{ __('Cancelled') }} · {{ $cancellation->cancelled_at?->format('Y-m-d H:i') }}</li>
                    @empty
                        @forelse ($order->returns as $return)
                            <li>{{ __('Return') }} #{{ $return->id }} · {{ $return->condition }} · {{ $return->status }}</li>
                        @empty
                            <li>{{ __('None') }}</li>
                        @endforelse
                    @endforelse
                </ul>
            </div>
        </section>
    </div>
</x-app-layout>
