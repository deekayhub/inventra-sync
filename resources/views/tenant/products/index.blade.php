<x-app-layout :title="__('Products')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Products') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Manage products, variants, and SKUs for this workspace.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('products.view')
                    <a href="{{ route('products.export') }}" class="btn-secondary">
                        <x-icon name="clipboard" class="h-4 w-4" />
                        {{ __('Export CSV') }}
                    </a>
                    @if (session('status') && str_starts_with((string) session('status'), __('Product export queued:')))
                        <span class="text-xs font-medium text-success">{{ __('Export queued') }}</span>
                    @endif
                @endcan
                @can('products.create')
                    <a href="{{ route('products.import.create') }}" class="btn-secondary">
                        <x-icon name="clipboard" class="h-4 w-4" />
                        {{ __('Import CSV') }}
                    </a>
                    <a href="{{ route('products.create') }}" class="btn-primary">
                        <x-icon name="plus" class="h-4 w-4" />
                        {{ __('New product') }}
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

        <section class="surface-card p-4 sm:p-5">
            <form method="GET" action="{{ route('products.index') }}" class="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                <div class="min-w-0">
                    <label for="search" class="field-label">{{ __('Search') }}</label>
                    <input id="search" name="search" type="search" value="{{ $filters['search'] }}" placeholder="{{ __('Name, SKU, or barcode') }}" class="field-input">
                </div>
                <div class="min-w-0">
                    <label for="category" class="field-label">{{ __('Category') }}</label>
                    <select id="category" name="category" class="field-input">
                        <option value="">{{ __('All') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label for="status" class="field-label">{{ __('Status') }}</label>
                    <select id="status" name="status" class="field-input">
                        <option value="">{{ __('All') }}</option>
                        @foreach ([\App\Models\Product::STATUS_DRAFT, \App\Models\Product::STATUS_ACTIVE, \App\Models\Product::STATUS_ARCHIVED] as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(ucfirst($status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-1">
                    <button type="submit" class="btn-primary h-11 whitespace-nowrap">{{ __('Search') }}</button>
                    <a href="{{ route('products.index') }}" class="btn-secondary h-11 whitespace-nowrap">{{ __('Reset') }}</a>
                </div>
            </form>
        </section>

        <section class="glass-card overflow-hidden">
            @if ($products->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="box" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No products yet') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Create a product or import a CSV to start your catalog.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Workspace products') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Product') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('SKU') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Category') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($products as $product)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <span class="font-semibold text-foreground">{{ $product->name }}</span>
                                        <span class="mt-0.5 block text-xs text-muted-foreground">{{ trans_choice(':count variant|:count variants', $product->variants->count()) }}</span>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs text-muted-foreground">
                                        {{ $product->variants->first()?->sku ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $product->category?->name ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-md border border-line bg-muted/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-foreground">{{ $product->status }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('products.update')
                                                <a href="{{ route('products.edit', $product) }}" class="btn-quiet">{{ __('Edit') }}</a>
                                                @if (! $product->isArchived())
                                                    <form method="POST" action="{{ route('products.archive', $product) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-quiet">{{ __('Archive') }}</button>
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
                <div class="border-t border-line px-5 py-4">
                    {{ $products->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
