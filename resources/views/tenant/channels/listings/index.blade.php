<x-app-layout :title="__('Channel listings')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Integrations') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Channel listings') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Map external listings to internal variants before syncing inventory. Unmapped listings are never updated.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('channels.index') }}" class="btn-secondary">{{ __('Channels') }}</a>
                @can('channels.sync')
                    <form method="POST" action="{{ route('channels.listings.sync') }}">
                        @csrf
                        <button type="submit" class="btn-primary">{{ __('Sync inventory') }}</button>
                    </form>
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

        <section class="surface-card p-4 sm:p-5">
            <form method="GET" action="{{ route('channels.listings.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                <div class="min-w-0">
                    <label for="channel_id" class="field-label">{{ __('Channel') }}</label>
                    <select id="channel_id" name="channel_id" class="field-input">
                        <option value="">{{ __('All channels') }}</option>
                        @foreach ($connections as $connection)
                            <option value="{{ $connection->id }}" @selected($selectedChannelId === $connection->id)>{{ $connection->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn-primary h-11">{{ __('Filter') }}</button>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($listings->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="clipboard" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No channel listings yet') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Import listings from a connected channel to map them to internal variants.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Channel listings') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Listing') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Mapped variant') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Sync') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Imported') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($listings as $listing)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $listing->title }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $listing->external_id }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $listing->connection?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $listing->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">
                                        {{ $listing->mapping?->variant?->product?->name ?? __('Unmapped') }}
                                        @if ($listing->mapping?->variant?->sku)
                                            <span class="mt-0.5 block font-mono">{{ $listing->mapping->variant->sku }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">
                                        {{ $listing->inventorySetting?->paused ? __('Paused') : __('Active') }}
                                        @if (! $listing->inventorySetting?->paused)
                                            <span class="mt-0.5 block">{{ __('Buffer: :count', ['count' => $listing->inventorySetting?->buffer_stock ?? 0]) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $listing->last_imported_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @can('channels.manage')
                                            <a href="{{ route('channels.listings.edit', $listing) }}" class="btn-quiet">{{ __($listing->mapping ? 'Edit mapping' : 'Map listing') }}</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $listings->links() }}
    </div>
</x-app-layout>
