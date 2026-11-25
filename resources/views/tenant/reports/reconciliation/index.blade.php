<x-app-layout :title="__('Reconciliation')">
    <x-slot name="header">
        <div class="space-y-4">
            <div>
                <p class="eyebrow">{{ __('Reports') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Reconciliation') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Compare channel quantities, investigate discrepancies, and record ledger corrections.') }}</p>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">{{ session('status') }}</div>
        @endif

        <section class="glass-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div class="grid flex-1 gap-3 sm:grid-cols-4">
                    <div>
                        <p class="eyebrow">{{ __('Items') }}</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->items_count }}</p>
                    </div>
                    <div>
                        <p class="eyebrow">{{ __('Discrepancies') }}</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->discrepancies_count }}</p>
                    </div>
                    <div>
                        <p class="eyebrow">{{ __('Corrected') }}</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->corrected_count }}</p>
                    </div>
                    <div>
                        <p class="eyebrow">{{ __('Failed') }}</p>
                        <p class="mt-1 text-xl font-semibold text-foreground">{{ $summary->failed_count }}</p>
                    </div>
                </div>
                <a href="{{ route('reports.export', ['report' => 'reconciliation']) }}" class="btn-quiet">{{ __('Export CSV') }}</a>
            </div>

            @can('channels.sync')
                <form method="POST" action="{{ route('reports.reconciliation.run') }}" class="flex flex-wrap items-end gap-3 border-b border-line px-5 py-4">
                    @csrf
                    <label class="flex min-w-56 flex-1 flex-col gap-1.5">
                        <span class="text-xs font-semibold text-foreground">{{ __('Channel') }}</span>
                        <select name="channel_connection_id" class="w-full rounded-lg border border-line bg-card px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">{{ __('All active channels') }}</option>
                            @foreach ($connections as $connection)
                                <option value="{{ $connection->id }}">{{ $connection->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn-secondary">
                        <x-icon name="sync" class="h-4 w-4" />
                        {{ __('Run reconciliation') }}
                    </button>
                </form>
            @endcan
        </section>

        <section class="glass-card overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold text-foreground">{{ __('Runs') }}</h3>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ __('Scheduled and manual comparison runs.') }}</p>
            </div>
            @if ($runs->isEmpty())
                <div class="px-5 py-12 text-center text-xs leading-5 text-muted-foreground">{{ __('No reconciliation runs yet.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Reconciliation runs') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Items') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Discrepancies') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Failures') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Started') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Finished') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($runs as $run)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 text-xs font-semibold text-foreground">{{ $run->connection?->name ?? __('All channels') }}</td>
                                    <td class="px-5 py-3"><span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $run->status }}</span></td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $run->items_count }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $run->discrepancies_count }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $run->failures_count }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $run->started_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $run->finished_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $runs->links() }}

        <section class="glass-card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-5 py-4">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">{{ __('Items') }}</h3>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ __('Per-listing comparison results and correction state.') }}</p>
                </div>
                <form method="GET" class="flex items-end gap-2">
                    <label class="flex flex-col gap-1.5">
                        <span class="text-xs font-semibold text-foreground">{{ __('Status') }}</span>
                        <select name="status" class="rounded-lg border border-line bg-card px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn-quiet">{{ __('Filter') }}</button>
                </form>
            </div>

            @if ($items->isEmpty())
                <div class="px-5 py-16 text-center text-sm text-muted-foreground">{{ __('No reconciliation items match these filters.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Reconciliation items') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Listing') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Expected') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Remote') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Difference') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Details') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($items as $item)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $item->listing?->title ?? __('Unknown listing') }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $item->variant?->sku ?? $item->connection?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3"><span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $item->expected_quantity ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right text-xs text-foreground">{{ $item->remote_quantity ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right text-xs font-semibold text-foreground">{{ $item->difference ?? '—' }}</td>
                                    <td class="max-w-xs px-5 py-3 text-xs text-muted-foreground">{{ $item->error ?? ($item->corrected_at?->diffForHumans() ?? '—') }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @if ($item->status === App\Models\ReconciliationItem::STATUS_DISCREPANCY && $item->correction_quantity !== null)
                                            @can('inventory.adjust')
                                                <form method="POST" action="{{ route('reports.reconciliation.correct', $item) }}" class="flex flex-wrap items-center justify-end gap-2">
                                                    @csrf
                                                    <select name="warehouse_id" required aria-label="{{ __('Correction warehouse') }}" class="rounded-lg border border-line bg-card px-2 py-1.5 text-xs text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                                        @foreach ($warehouses as $warehouse)
                                                            <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <input type="text" name="reason" required maxlength="255" placeholder="{{ __('Correction reason') }}" class="w-40 rounded-lg border border-line bg-card px-2 py-1.5 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                                    <button type="submit" class="btn-quiet">{{ __('Correct') }}</button>
                                                </form>
                                            @endcan
                                        @elseif ($item->movement)
                                            <a href="{{ route('tenant.inventory.ledger.index', ['variant_id' => $item->product_variant_id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-foreground hover:text-primary">
                                                {{ __('View ledger') }}
                                                <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $items->links() }}
    </div>
</x-app-layout>
