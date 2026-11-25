<x-app-layout :title="__('Sync failures')">
    <x-slot name="header">
        <div class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">{{ __('Reports') }}</p>
                    <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Sync failures') }}</h2>
                    <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Review failed channel pushes, pending retries, and reconciliation discrepancies.') }}</p>
                </div>
            </div>
            @include('tenant.reports.partials.nav')
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-xl border border-success/25 bg-success/10 px-4 py-3 text-sm font-medium text-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="glass-card overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold text-foreground">{{ __('Outbox events') }}</h3>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ __('Failed and retrying inventory events waiting for another delivery attempt.') }}</p>
            </div>

            @if ($events->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="alert" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No failed sync events') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Channel inventory pushes are healthy.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Failed outbox events') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Listing') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Attempts') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Next try') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Error') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($events as $event)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $event->listing?->title ?? __('Unknown listing') }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $event->connection?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $event->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $event->attempts }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $event->next_try_at?->diffForHumans() ?? '—' }}</td>
                                    <td class="max-w-xs px-5 py-3 text-xs text-muted-foreground">{{ $event->last_error ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @can('channels.sync')
                                            <form method="POST" action="{{ route('reports.failures.retry', $event) }}">
                                                @csrf
                                                <button type="submit" class="btn-quiet">{{ __('Retry') }}</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $events->links() }}

        <section class="glass-card overflow-hidden">
            <div class="border-b border-line px-5 py-4">
                <h3 class="text-sm font-semibold text-foreground">{{ __('Sync history') }}</h3>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">{{ __('Recent failed, retrying, skipped, and discrepant channel sync attempts.') }}</p>
            </div>

            @if ($logs->isEmpty())
                <div class="px-5 py-12 text-center text-xs leading-5 text-muted-foreground">{{ __('No sync issues recorded.') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Channel sync history') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Channel') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Listing') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Direction') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Quantity') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Details') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Recorded') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($logs as $log)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $log->connection?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $log->listing?->title ?? __('Unknown listing') }}</span>
                                        <span class="mt-0.5 block font-mono text-xs text-muted-foreground">{{ $log->variant?->sku ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $log->direction }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $log->status }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $log->quantity ?? '—' }}</td>
                                    <td class="max-w-xs px-5 py-3 text-xs text-muted-foreground">{{ $log->error ?? ($log->metadata ? json_encode($log->metadata) : '—') }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $log->created_at?->diffForHumans() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{ $logs->links() }}
    </div>
</x-app-layout>
