<x-app-layout :title="__('Channels')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Integrations') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Channels') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Connect sales channels and control which mapped listings receive stock updates.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('channels.listings.index') }}" class="btn-secondary">
                    <x-icon name="clipboard" class="h-4 w-4" />
                    {{ __('Listings') }}
                </a>
                @can('channels.manage')
                    <a href="{{ route('channels.create') }}" class="btn-primary">
                        <x-icon name="plus" class="h-4 w-4" />
                        {{ __('Connect channel') }}
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="glass-card overflow-hidden">
            @if ($connections->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="storefront" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No channels connected') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Connect a Shopify or WooCommerce store to start importing listings.') }}</p>
                    @can('channels.manage')
                        <a href="{{ route('channels.create') }}" class="btn-primary mt-2">{{ __('Connect channel') }}</a>
                    @endcan
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Connected channels') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Account') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Listings') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Last synced') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($connections as $connection)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $connection->name }}</span>
                                        <span class="mt-0.5 block text-xs capitalize text-muted-foreground">{{ $connection->provider }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $connection->account_identifier }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $connection->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">
                                        {{ trans_choice(':count listing|:count listings', $connection->listings_count) }}
                                        <span class="mt-0.5 block">{{ trans_choice(':count mapping|:count mappings', $connection->mappings_count) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $connection->last_synced_at?->diffForHumans() ?? __('Never') }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            <a href="{{ route('channels.listings.index', ['channel_id' => $connection->id]) }}" class="btn-quiet">{{ __('View listings') }}</a>
                                            @can('channels.manage')
                                                <form method="POST" action="{{ route('channels.import', $connection) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-quiet">{{ __('Import') }}</button>
                                                </form>
                                                @if ($connection->isActive())
                                                    <form method="POST" action="{{ route('channels.deactivate', $connection) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-quiet">{{ __('Deactivate') }}</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
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
