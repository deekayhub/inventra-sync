<x-app-layout :title="__('Categories')">
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Catalog') }}</p>
                <h2 class="mt-2 text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">{{ __('Categories') }}</h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-muted-foreground">{{ __('Group products into categories for filtering and reporting.') }}</p>
            </div>
            @can('products.create')
                <a href="{{ route('categories.create') }}" class="btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    {{ __('New category') }}
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
            @if ($categories->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-16 text-center">
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground">
                        <x-icon name="grid" class="h-7 w-7" />
                    </span>
                    <p class="text-sm font-semibold text-foreground">{{ __('No categories yet') }}</p>
                    <p class="max-w-sm text-xs leading-5 text-muted-foreground">{{ __('Create a category to organize your product catalog.') }}</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <caption class="sr-only">{{ __('Product categories') }}</caption>
                        <thead>
                            <tr class="border-b border-line bg-muted/50 text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Category') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Slug') }}</th>
                                <th scope="col" class="px-5 py-3 font-semibold">{{ __('Products') }}</th>
                                <th scope="col" class="px-5 py-3 text-right font-semibold">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($categories as $category)
                                <tr class="transition-colors hover:bg-muted/40">
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold text-foreground">{{ $category->name }}</span>
                                            @if (! $category->is_active)
                                                <span class="rounded-md border border-line bg-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ __('Inactive') }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono text-xs text-muted-foreground">{{ $category->slug }}</td>
                                    <td class="px-5 py-3 text-xs text-muted-foreground">{{ $category->products_count }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @can('products.update')
                                                <a href="{{ route('categories.edit', $category) }}" class="btn-quiet">{{ __('Edit') }}</a>
                                            @endcan
                                            @can('products.delete')
                                                <form method="POST" action="{{ route('categories.destroy', $category) }}">
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
                    {{ $categories->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
