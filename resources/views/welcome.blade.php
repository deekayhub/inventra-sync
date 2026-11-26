@php
    $primaryCtaUrl = auth()->check() ? route('dashboard') : route('register');
    $primaryCtaLabel = auth()->check() ? __('Open dashboard') : __('Start a workspace');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth" style="color-scheme: light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#3B3B58">

        <title>{{ config('app.name') }} — {{ __('Inventory, orders and channels in sync') }}</title>
        <meta name="description" content="{{ __('Centralize products, stock and orders, then keep every connected sales channel aligned with the inventory you actually have.') }}">
        <meta property="og:title" content="{{ config('app.name') }} — {{ __('Inventory, orders and channels in sync') }}">
        <meta property="og:description" content="{{ __('A multi-tenant inventory operations platform for products, reservations, order webhooks and channel synchronization.') }}">
        <meta property="og:type" content="website">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            summary::-webkit-details-marker { display: none; }
        </style>
    </head>
    <body class="relative overflow-x-hidden bg-canvas text-foreground">
        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-lg focus:border focus:border-line focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-foreground focus:shadow-glass">
            {{ __('Skip to content') }}
        </a>

        <header x-data="{ mobileOpen: false }" class="sticky top-0 z-50 border-b border-white/10 bg-primary text-white">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-6 px-5 sm:px-6 lg:px-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
                    <x-brand-mark class="h-9 w-9" />
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-8 lg:flex" aria-label="Primary">
                    <a href="#platform" class="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-300 transition-colors hover:text-white">{{ __('Platform') }}</a>
                    <a href="#workflow" class="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-300 transition-colors hover:text-white">{{ __('Workflow') }}</a>
                    <a href="#channels" class="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-300 transition-colors hover:text-white">{{ __('Channels') }}</a>
                    <a href="#assurance" class="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-300 transition-colors hover:text-white">{{ __('Assurance') }}</a>
                    <a href="#faq" class="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-300 transition-colors hover:text-white">FAQ</a>
                </nav>

                <div class="flex items-center gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="hidden px-3 py-2 text-sm font-semibold text-zinc-300 transition-colors hover:text-white sm:inline-flex">{{ __('Dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="hidden px-3 py-2 text-sm font-semibold text-zinc-300 transition-colors hover:text-white sm:inline-flex">{{ __('Sign in') }}</a>
                    @endauth
                    <a href="{{ $primaryCtaUrl }}" class="hidden items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-primary transition-colors hover:bg-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary sm:inline-flex">
                        {{ $primaryCtaLabel }}
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <button type="button" @click="mobileOpen = !mobileOpen" :aria-expanded="mobileOpen" aria-controls="mobile-menu" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-white/20 bg-white/5 text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary lg:hidden">
                        <span class="sr-only">{{ __('Toggle navigation') }}</span>
                        <x-icon name="menu" x-show="!mobileOpen" class="h-5 w-5" />
                        <x-icon name="x" x-show="mobileOpen" x-cloak class="h-5 w-5" />
                    </button>
                </div>
            </div>

            <div id="mobile-menu" x-show="mobileOpen" x-cloak x-transition.opacity.duration.150ms class="absolute inset-x-0 top-full border-b border-line bg-white text-foreground shadow-glass-lg lg:hidden">
                <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-5 py-4 sm:px-6" aria-label="Mobile">
                    <a href="#platform" @click="mobileOpen = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">{{ __('Platform') }}</a>
                    <a href="#workflow" @click="mobileOpen = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">{{ __('Workflow') }}</a>
                    <a href="#channels" @click="mobileOpen = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">{{ __('Channels') }}</a>
                    <a href="#assurance" @click="mobileOpen = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">{{ __('Assurance') }}</a>
                    <a href="#faq" @click="mobileOpen = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-muted-foreground hover:bg-muted hover:text-foreground">FAQ</a>
                    <div class="mt-2 flex flex-col gap-2 border-t border-line pt-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="px-3 py-2.5 text-sm font-semibold text-muted-foreground">{{ __('Dashboard') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="px-3 py-2.5 text-sm font-semibold text-muted-foreground">{{ __('Sign in') }}</a>
                        @endauth
                        <a href="{{ $primaryCtaUrl }}" class="btn-primary">{{ $primaryCtaLabel }}</a>
                    </div>
                </nav>
            </div>
        </header>

        <main id="main">
            <section class="bg-brand-gradient text-white">
                <div class="mx-auto grid max-w-7xl gap-12 px-5 py-16 sm:px-6 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:items-center lg:gap-16 lg:px-8 lg:py-24">
                    <div>
                        <p class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-zinc-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-white" aria-hidden="true"></span>
                            {{ __('Multi-channel inventory operations') }}
                        </p>
                        <h1 class="mt-6 max-w-2xl text-4xl font-semibold leading-[1.1] tracking-tight sm:text-5xl lg:text-6xl">
                            {{ __('One source of truth for stock across every channel.') }}
                        </h1>
                        <p class="mt-6 max-w-xl text-base leading-7 text-zinc-300">
                            {{ __('Keep products, on-hand stock, reservations, orders and channel inventory aligned. Every order is checked, every stock change is recorded, and every sync is traceable.') }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ $primaryCtaUrl }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-primary shadow-sm transition-colors hover:bg-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                                {{ $primaryCtaLabel }}
                                <x-icon name="arrow-right" class="h-4 w-4" />
                            </a>
                            <a href="#workflow" class="inline-flex items-center justify-center rounded-lg border border-white/25 bg-white/5 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                                {{ __('See how it works') }}
                            </a>
                        </div>

                        <ul class="mt-8 flex flex-wrap gap-2">
                            @foreach ([__('Product catalog'), __('Inventory ledger'), __('Order webhooks'), __('Channel sync')] as $pill)
                                <li class="rounded-full border border-white/15 bg-white/5 px-3 py-1 text-[11px] font-medium text-zinc-300">{{ $pill }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="relative" aria-hidden="true">
                        <div class="rounded-2xl border border-white/10 bg-zinc-950/40 p-2 shadow-glass-lg">
                            <div class="overflow-hidden rounded-xl bg-white text-foreground">
                                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <x-brand-mark class="h-6 w-6" />
                                        <span class="text-xs font-semibold">{{ config('app.name') }}</span>
                                    </div>
                                    <span class="rounded-md border border-line bg-muted px-2 py-1 text-[10px] font-medium text-muted-foreground">{{ __('Illustrative workspace') }}</span>
                                </div>

                                <div class="flex">
                                    <div class="hidden w-40 shrink-0 border-r border-line bg-canvas p-3 sm:block">
                                        <p class="px-2 text-[9px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">{{ __('Operations') }}</p>
                                        <div class="mt-2 space-y-1 text-[11px] font-medium">
                                            <p class="rounded-md bg-primary px-2 py-1.5 font-semibold text-white">{{ __('Orders') }}</p>
                                            <p class="px-2 py-1.5 text-muted-foreground">{{ __('Products') }}</p>
                                            <p class="px-2 py-1.5 text-muted-foreground">{{ __('Inventory') }}</p>
                                            <p class="px-2 py-1.5 text-muted-foreground">{{ __('Channels') }}</p>
                                            <p class="px-2 py-1.5 text-muted-foreground">{{ __('Reports') }}</p>
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1 p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Order operations') }}</p>
                                                <p class="mt-1 text-lg font-semibold tracking-tight">{{ __('Nothing gets lost between channels.') }}</p>
                                            </div>
                                            <span class="rounded-md border border-line px-2 py-1 text-[10px] font-semibold text-muted-foreground">{{ __('Live view') }}</span>
                                        </div>

                                        <div class="mt-4 grid grid-cols-3 gap-2">
                                            @foreach ([__('Review'), __('Reserved'), __('Sync')] as $label)
                                                <div class="rounded-lg border border-line bg-canvas p-2.5">
                                                    <p class="text-[9px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">{{ $label }}</p>
                                                    <p class="mt-1 text-xs font-semibold">{{ __('Tracked') }}</p>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="mt-3 overflow-hidden rounded-lg border border-line">
                                            <div class="grid grid-cols-[1fr_auto] gap-2 border-b border-line bg-canvas px-3 py-2 text-[9px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                                                <span>{{ __('Recent order event') }}</span>
                                                <span>{{ __('Status') }}</span>
                                            </div>
                                            @foreach ([__('Order accepted'), __('Stock reserved'), __('Inventory queued'), __('Ledger entry created')] as $event)
                                                <div class="grid grid-cols-[1fr_auto] items-center gap-2 border-t border-line px-3 py-2 first:border-t-0">
                                                    <span class="truncate text-[11px] font-medium">{{ $event }}</span>
                                                    <span class="rounded-full bg-accent px-2 py-0.5 text-[9px] font-semibold text-foreground">{{ __('Recorded') }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="platform" class="scroll-mt-16 border-b border-line bg-white">
                <div class="mx-auto max-w-7xl px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="eyebrow">{{ __('The platform') }}</p>
                        <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ __('Built for the work behind every order.') }}</h2>
                        <p class="mt-4 text-sm leading-7 text-muted-foreground sm:text-base">
                            {{ __('Inventra Sync brings the operational pieces together: a clean catalog, trustworthy stock quantities, idempotent order intake, channel mappings and a history you can audit.') }}
                        </p>
                    </div>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['box', __('Product catalog'), __('Manage simple and variable products, SKUs, barcodes, variants, categories and product status in one place.')],
                            ['clipboard', __('Inventory control'), __('Track on-hand, reserved and available stock with adjustments, opening stock and an immutable ledger.')],
                            ['check', __('Order reservations'), __('Reserve stock when an accepted order arrives, release it on cancellation and commit it on fulfilment.')],
                            ['plug', __('Channel synchronization'), __('Queue inventory updates, process webhooks, retry temporary failures and keep per-listing history.')],
                            ['chart', __('Reports and reconciliation'), __('Review stock, low-stock, movement, orders by channel and failed synchronization without guessed numbers.')],
                            ['users', __('Tenant-aware teamwork'), __('Keep roles, permissions and activity scoped to the workspace so teams only see their own operations.')],
                        ] as $feature)
                            <article class="glass-card p-5 transition-colors hover:border-zinc-300">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-line bg-canvas text-foreground">
                                    <x-icon name="{{ $feature[0] }}" class="h-5 w-5" />
                                </span>
                                <h3 class="mt-4 text-lg font-semibold">{{ $feature[1] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ $feature[2] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="workflow" class="scroll-mt-16 border-b border-line bg-canvas/60">
                <div class="mx-auto max-w-7xl px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div class="max-w-2xl">
                            <p class="eyebrow">{{ __('How it works') }}</p>
                            <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ __('From webhook to warehouse, without the guesswork.') }}</h2>
                        </div>
                        <p class="max-w-md text-sm leading-7 text-muted-foreground">
                            {{ __('The MVP follows one reliable path: receive an order, protect stock, publish the change and preserve the evidence.') }}
                        </p>
                    </div>

                    <ol class="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['01', 'plug', __('Connect a channel'), __('Authorize the store, validate credentials and keep provider secrets encrypted.')],
                            ['02', 'box', __('Map your catalog'), __('Match external listings to internal variants and control channel-specific buffers.')],
                            ['03', 'sync', __('Process the order'), __('Validate the webhook, reject duplicates, reserve stock and queue inventory updates.')],
                            ['04', 'chart', __('Trace the result'), __('Record the ledger entry, sync status and activity event for every change.')],
                        ] as $step)
                            <li class="glass-card flex flex-col p-5">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono text-xs font-semibold text-muted-foreground">{{ $step[0] }}</span>
                                    <x-icon name="{{ $step[1] }}" class="h-5 w-5 text-foreground" />
                                </div>
                                <h3 class="mt-5 text-base font-semibold">{{ $step[2] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ $step[3] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            <section id="channels" class="scroll-mt-16 bg-white">
                <div class="mx-auto grid max-w-7xl gap-10 px-5 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8">
                    <div>
                        <p class="eyebrow">{{ __('Channels') }}</p>
                        <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ __('Your storefronts change. Your inventory story stays consistent.') }}</h2>
                        <p class="mt-4 text-sm leading-7 text-muted-foreground sm:text-base">
                            {{ __('Connect sales channels without scattering stock logic across dashboards. Channel connections, listing mappings and synchronization status stay visible in one workspace.') }}
                        </p>

                        <ul class="mt-8 space-y-4">
                            @foreach ([
                                __('Shopify-first integration path with WooCommerce next in priority.'),
                                __('Encrypted credentials, connection status and last successful sync per channel.'),
                                __('Channel buffers so reserved stock is not exposed as sellable inventory.'),
                                __('Retry, failure and reconciliation reporting when providers do not respond.'),
                            ] as $item)
                                <li class="flex gap-3 text-sm leading-6 text-foreground">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-accent text-foreground">
                                        <x-icon name="check" class="h-3.5 w-3.5" />
                                    </span>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="glass-panel p-5 sm:p-6">
                        <div class="flex items-center justify-between gap-4 border-b border-line pb-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">{{ __('Connection overview') }}</p>
                                <p class="mt-1 text-lg font-semibold">{{ __('Provider adapters stay separate') }}</p>
                            </div>
                            <x-icon name="storefront" class="h-6 w-6 text-muted-foreground" />
                        </div>

                        <div class="mt-4 space-y-3">
                            @foreach ([
                                ['Shopify', __('Initial priority'), __('Orders · Listings · Inventory sync')],
                                ['WooCommerce', __('Next priority'), __('Orders · Listings · Inventory sync')],
                            ] as $channel)
                                <div class="rounded-lg border border-line bg-canvas p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <h3 class="text-sm font-semibold">{{ $channel[0] }}</h3>
                                        <span class="rounded-full border border-line bg-white px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ $channel[1] }}</span>
                                    </div>
                                    <p class="mt-2 text-xs text-muted-foreground">{{ $channel[2] }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 flex items-start gap-3 rounded-lg border border-line bg-muted/50 p-4">
                            <x-icon name="alert" class="mt-0.5 h-5 w-5 shrink-0 text-foreground" />
                            <p class="text-xs leading-6 text-muted-foreground">
                                {{ __('Future adapters can be added without rewriting the inventory engine. Amazon, eBay, POS systems and custom APIs remain outside the first release.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="assurance" class="scroll-mt-16 border-y border-line bg-canvas/60">
                <div class="mx-auto max-w-7xl px-5 py-16 sm:px-6 sm:py-20 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="eyebrow">{{ __('Assurance') }}</p>
                        <h2 class="mt-4 text-3xl font-semibold leading-tight tracking-tight sm:text-4xl lg:text-5xl">{{ __('Controls that make inventory changes defensible.') }}</h2>
                        <p class="mt-4 text-sm leading-7 text-muted-foreground sm:text-base">
                            {{ __('Operational trust comes from constraints you can verify: tenant scoping, idempotent intake, locked-down stock operations and records that survive retries.') }}
                        </p>
                    </div>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ([
                            ['users', __('Tenant isolation'), __('Business records carry a tenant key and authorization is enforced before access.')],
                            ['cog', __('Scoped permissions'), __('Roles and permissions belong to the workspace, with protected admin safeguards.')],
                            ['plug', __('Validated webhooks'), __('Provider events are verified, deduplicated and rate limited before processing.')],
                            ['clipboard', __('Audit trail'), __('Activity logs and the stock ledger record who changed what, and when.')],
                        ] as $assurance)
                            <article class="glass-card p-5">
                                <x-icon name="{{ $assurance[0] }}" class="h-5 w-5 text-foreground" />
                                <h3 class="mt-4 text-base font-semibold">{{ $assurance[1] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-muted-foreground">{{ $assurance[2] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="faq" class="scroll-mt-16 bg-white">
                <div class="mx-auto max-w-3xl px-5 py-16 sm:px-6 sm:py-20">
                    <p class="eyebrow">FAQ</p>
                    <h2 class="mt-4 text-3xl font-semibold tracking-tight sm:text-4xl lg:text-5xl">{{ __('Common questions.') }}</h2>

                    <div class="mt-8 divide-y divide-line border-y border-line">
                        @foreach ([
                            [__('Is this a generic dashboard starter?'), __('No. Authentication, tenancy, workspaces, roles and activity logging are reused as the foundation, but the product screens and workflows are built around inventory, reservations, orders and channel synchronization.')],
                            [__('How does it reduce overselling?'), __('Available stock is calculated from on-hand stock minus reserved stock. Channel availability also subtracts a per-listing buffer. Orders are deduplicated and reservations are processed idempotently.')],
                            [__('Which integrations are in the first release?'), __('The initial priority is Shopify, followed by WooCommerce. Provider logic is separated from the inventory engine so later adapters can be added without redesigning stock operations.')],
                            [__('Can every stock change be traced?'), __('Yes. Stock movements are recorded in an immutable ledger, while user actions and business changes are captured in the activity log. Synchronization status is tracked by channel and listing.')],
                            [__('Are workspaces separated from each other?'), __('Yes. Tenant-owned records are scoped by tenant, roles and permissions are scoped to the workspace, and authorization is enforced on business operations.')],
                        ] as $faq)
                            <details class="group py-5">
                                <summary class="flex cursor-pointer items-center justify-between gap-4 text-sm font-semibold text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">{{ $faq[0] }}<span class="text-lg font-normal leading-none text-muted-foreground transition-transform duration-150 group-open:rotate-45" aria-hidden="true">+</span></summary>
                                <p class="mt-3 max-w-2xl pr-8 text-sm leading-6 text-muted-foreground">{{ $faq[1] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-brand-gradient text-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-8 px-5 py-16 sm:px-6 sm:py-20 lg:flex-row lg:items-end lg:justify-between lg:px-8">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-zinc-300">{{ __('Ready to sync') }}</p>
                        <h2 class="mt-4 max-w-xl text-3xl font-semibold leading-tight sm:text-4xl lg:text-5xl">{{ __('Put every channel on the same inventory page.') }}</h2>
                    </div>
                    <a href="{{ $primaryCtaUrl }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-primary shadow-sm transition-colors hover:bg-zinc-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary">
                        {{ $primaryCtaLabel }}
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </section>
        </main>

        <footer class="border-t border-line bg-canvas">
            <div class="mx-auto max-w-7xl px-5 py-10 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2" aria-label="{{ __(':app home', ['app' => config('app.name')]) }}">
                            <x-brand-mark class="h-9 w-9" />
                            <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                        </a>
                        <p class="mt-4 max-w-sm text-sm leading-6 text-muted-foreground">{{ __('A multi-tenant inventory platform for products, orders, reservations and channel synchronization.') }}</p>
                    </div>
                    <nav class="flex flex-wrap gap-6 text-xs font-semibold uppercase tracking-[0.1em] text-muted-foreground sm:gap-8" aria-label="Footer">
                        <a href="#platform" class="hover:text-foreground">{{ __('Platform') }}</a>
                        <a href="#workflow" class="hover:text-foreground">{{ __('Workflow') }}</a>
                        <a href="#channels" class="hover:text-foreground">{{ __('Channels') }}</a>
                        <a href="#assurance" class="hover:text-foreground">{{ __('Assurance') }}</a>
                        <a href="#faq" class="hover:text-foreground">FAQ</a>
                    </nav>
                </div>
                <p class="mt-10 border-t border-line pt-6 text-xs text-muted-foreground">&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Inventory, orders and channels in sync.') }}</p>
            </div>
        </footer>
    </body>
</html>
