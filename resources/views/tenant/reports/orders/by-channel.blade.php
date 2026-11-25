<x-app-layout :title="__('Orders by channel')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Orders by channel') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Compare order volume, fulfillment, and revenue across connected channels.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        <section class="glass-card overflow-hidden">
            <div class="grid gap-3 border-b border-line px-5 py-4 sm:grid-cols-3">
                <div>
                    <p class="eyebrow">{{ __('Orders') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->orders_count }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Fulfilled') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->fulfilled_count }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Revenue') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->revenue }}</p>
                </div>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
                <label class="flex min-w-40 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Status') }}</span>
                    <select name="status" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
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
                <a href="{{ route('reports.export', array_filter(['report' => 'orders-by-channel', 'status' => $status, 'from' => $from, 'to' => $to], fn ($value): bool => $value !== null && $value !== '')) }}" class="btn-quiet">{{ __('Export CSV') }}</a>
            </form>

            @if ($channels->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-muted-foreground">{{ __('No orders match these filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Orders by channel') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Provider') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Orders') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Fulfilled') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Revenue') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($channels as $channel)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 font-semibold text-foreground">{{ $channel->connection?->name ?? __('Unlinked channel') }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $channel->connection?->provider ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right text-xs font-semibold text-foreground">{{ $channel->orders_count }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $channel->fulfilled_count }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $channel->revenue }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $channels->links() }}
    </div>
</x-app-layout>
