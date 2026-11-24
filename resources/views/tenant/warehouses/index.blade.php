<x-app-layout :title="__('Warehouses')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Inventory') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Warehouses') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Track stock locations for this workspace.') }}</p>
            </div>
            @can('warehouses.manage')
                <a href="{{ route('warehouses.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New warehouse') }}
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

        <section class="glass-card overflow-hidden">
            @if ($warehouses->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="storefront" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No warehouses yet') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Create a warehouse to hold inventory.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Workspace warehouses') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Warehouse') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Stock records') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($warehouses as $warehouse)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-foreground">{{ $warehouse->name }}</span>
                                            @if ($warehouse->is_default)
                                                <span class="rounded-md border border-primary/25 bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-primary">{{ __('Default') }}</span>
                                            @endif
                                            @if (! $warehouse->is_active)
                                                <span class="rounded-md border border-line bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ __('Inactive') }}</span>
                                            @endif
                                            @if ($warehouse->address)
                                                <span class="block w-full text-xs text-muted-foreground">{{ $warehouse->address }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs text-muted-foreground">{{ $warehouse->code }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $warehouse->stocks_count }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('warehouses.manage')
                                                <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn-quiet">{{ __('Edit') }}</a>
                                                <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn-quiet text-destructive hover:text-destructive">{{ __('Delete') }}</button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-5 py-4">
                    {{ $warehouses->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
