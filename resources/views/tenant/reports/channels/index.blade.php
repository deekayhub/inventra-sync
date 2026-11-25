<x-app-layout :title="__('Channel status')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Channel status') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Check connection health, mapped listings, and the last sync attempt.') }}</p>
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
                    <p class="eyebrow">{{ __('Connections') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->connection_count }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Active') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->active_count }}</p>
                </div>
                <div>
                    <p class="eyebrow">{{ __('Never synced') }}</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->never_synced_count }}</p>
                </div>
            </div>

            <form method="GET" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
                <label class="flex min-w-48 flex-1 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Search') }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="{{ __('Channel name or account') }}" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                </label>
                <label class="flex min-w-40 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Status') }}</span>
                    <select name="status" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex min-w-40 flex-col gap-1.5">
                    <span class="text-xs font-semibold text-foreground">{{ __('Provider') }}</span>
                    <select name="provider" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">{{ __('All providers') }}</option>
                        @foreach ($providers as $value => $label)
                            <option value="{{ $value }}" @selected($provider === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="btn-secondary">{{ __('Filter') }}</button>
                <a href="{{ route('reports.export', array_filter(['report' => 'channels', 'status' => $status, 'provider' => $provider, 'search' => $search], fn ($value): bool => $value !== null && $value !== '')) }}" class="btn-quiet">{{ __('Export CSV') }}</a>
            </form>

            @if ($connections->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-muted-foreground">{{ __('No connections match these filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Channel connections') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Provider') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Listings') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Mapped variants') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Last sync') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($connections as $connection)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $connection->name }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $connection->account_identifier }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $providers[$connection->provider] ?? $connection->provider }}</td>
                                    <td class="px-5 py-3">
                                        <span @class([
                                            'rounded-md border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em]',
                                            'border-success/25 bg-success/10 text-success' => $connection->status === App\Models\ChannelConnection::STATUS_ACTIVE,
                                            'border-line bg-muted/60 text-muted-foreground' => $connection->status !== App\Models\ChannelConnection::STATUS_ACTIVE,
                                        ])>{{ $statuses[$connection->status] ?? $connection->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $connection->listings_count }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $connection->mappings_count }}</td>
                                    <td class="px-5 py-3">
                                        <span class="text-xs font-medium text-foreground">{{ $connection->last_synced_at?->diffForHumans() ?? __('Never') }}</span>
                                        <span class="mt-0.5 block text-xs text-muted-foreground">{{ $connection->latestSyncLog?->status ?? '—' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $connections->links() }}
    </div>
</x-app-layout>
