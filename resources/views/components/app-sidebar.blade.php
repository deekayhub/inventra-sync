@props(['closable' => false, 'collapsible' => false])

@php
    $isTenant = tenant() !== null;
    $user = auth()->user();
    $dashboardRoute = $isTenant ? route('tenant.dashboard') : route('dashboard');

    $workspaceItems = [
        ['label' => __('Overview'), 'icon' => 'grid', 'href' => $dashboardRoute, 'active' => request()->routeIs('dashboard', 'tenant.dashboard')],
    ];

    if (! $isTenant || $user?->can('activity-logs.view')) {
        $workspaceItems[] = [
            'label' => __('Activity'),
            'icon' => 'chart',
            'href' => $isTenant ? route('tenant.activity.index') : route('activity.index'),
            'active' => request()->routeIs('activity.index', 'tenant.activity.index'),
        ];
    }

    $catalogItems = [];

    if ($isTenant && $user?->can('products.view')) {
        $catalogItems[] = [
            'label' => __('Products'),
            'icon' => 'box',
            'href' => route('products.index'),
            'active' => request()->routeIs('products.*'),
        ];
        $catalogItems[] = [
            'label' => __('Categories'),
            'icon' => 'grid',
            'href' => route('categories.index'),
            'active' => request()->routeIs('categories.*'),
        ];
    }

    if ($isTenant && $user?->can('warehouses.view')) {
        $catalogItems[] = [
            'label' => __('Warehouses'),
            'icon' => 'storefront',
            'href' => route('warehouses.index'),
            'active' => request()->routeIs('warehouses.*'),
        ];
    }

    if ($isTenant && $user?->can('inventory.view_ledger')) {
        $catalogItems[] = [
            'label' => __('Stock levels'),
            'icon' => 'box',
            'href' => route('inventory.stocks.index'),
            'active' => request()->routeIs('inventory.stocks.*'),
        ];
        $catalogItems[] = [
            'label' => __('Inventory ledger'),
            'icon' => 'chart',
            'href' => route('inventory.ledger.index'),
            'active' => request()->routeIs('inventory.ledger.*'),
        ];
    }

    if ($isTenant && $user?->can('inventory.adjust')) {
        $catalogItems[] = [
            'label' => __('Adjust stock'),
            'icon' => 'plus',
            'href' => route('inventory.adjustments.create'),
            'active' => request()->routeIs('inventory.adjustments.*'),
        ];
    }

    if ($isTenant && $user?->can('inventory.manage')) {
        $catalogItems[] = [
            'label' => __('Reservations'),
            'icon' => 'clock',
            'href' => route('inventory.reservations.index'),
            'active' => request()->routeIs('inventory.reservations.*'),
        ];
    }

    $channelItems = [];

    if ($isTenant && $user?->can('channels.view')) {
        $channelItems[] = [
            'label' => __('Connections'),
            'icon' => 'storefront',
            'href' => route('channels.index'),
            'active' => request()->routeIs('channels.index', 'channels.create', 'channels.import', 'channels.deactivate'),
        ];
        $channelItems[] = [
            'label' => __('Listings'),
            'icon' => 'clipboard',
            'href' => route('channels.listings.index'),
            'active' => request()->routeIs('channels.listings.*'),
        ];
    }

    $orderItems = [];

    if ($isTenant && $user?->can('orders.view')) {
        $orderItems[] = [
            'label' => __('Orders'),
            'icon' => 'clipboard',
            'href' => route('orders.index'),
            'active' => request()->routeIs('orders.*'),
        ];
    }

    $reportItems = [];

    if ($isTenant && $user?->can('reports.view')) {
        $reportItems[] = [
            'label' => __('Current Inventory'),
            'icon' => 'box',
            'href' => route('reports.inventory.current'),
            'active' => request()->routeIs('reports.inventory.current'),
        ];
        $reportItems[] = [
            'label' => __('Low Stock'),
            'icon' => 'alert',
            'href' => route('reports.inventory.low-stock'),
            'active' => request()->routeIs('reports.inventory.low-stock'),
        ];
        $reportItems[] = [
            'label' => __('Out of Stock'),
            'icon' => 'alert',
            'href' => route('reports.inventory.out-of-stock'),
            'active' => request()->routeIs('reports.inventory.out-of-stock'),
        ];
        $reportItems[] = [
            'label' => __('Inventory Movement'),
            'icon' => 'chart',
            'href' => route('reports.inventory.movements'),
            'active' => request()->routeIs('reports.inventory.movements'),
        ];
        $reportItems[] = [
            'label' => __('Orders by Channel'),
            'icon' => 'clipboard',
            'href' => route('reports.orders-by-channel'),
            'active' => request()->routeIs('reports.orders-by-channel'),
        ];
        $reportItems[] = [
            'label' => __('Channel Status'),
            'icon' => 'sync',
            'href' => route('reports.channels'),
            'active' => request()->routeIs('reports.channels'),
        ];
        $reportItems[] = [
            'label' => __('Reconciliation'),
            'icon' => 'sync',
            'href' => route('reports.reconciliation.index'),
            'active' => request()->routeIs('reports.reconciliation.*'),
        ];
        $reportItems[] = [
            'label' => __('Sync Failures'),
            'icon' => 'alert',
            'href' => route('reports.failures.index'),
            'active' => request()->routeIs('reports.failures.*'),
        ];
    }

    $settingsItems = [
        ['label' => __('Profile'), 'icon' => 'user', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.*')],
    ];

    if ($isTenant && $user?->can('workspace.view')) {
        $settingsItems[] = [
            'label' => __('Workspace'),
            'icon' => 'storefront',
            'href' => route('settings.workspace.edit'),
            'active' => request()->routeIs('settings.workspace.*'),
        ];
    }

    if ($isTenant && $user?->can('users.view')) {
        $settingsItems[] = [
            'label' => __('Team Members'),
            'icon' => 'users',
            'href' => route('settings.members.index'),
            'active' => request()->routeIs('settings.members.*'),
        ];
    }

    if ($isTenant && $user?->can('roles.view')) {
        $settingsItems[] = [
            'label' => __('Roles & Permissions'),
            'icon' => 'cog',
            'href' => route('settings.roles.index'),
            'active' => request()->routeIs('settings.roles.*'),
        ];
    }

    $groups = [
        [
            'label' => __('Workspace'),
            'items' => $workspaceItems,
        ],
    ];

    if ($catalogItems !== []) {
        $groups[] = [
            'label' => __('Inventory'),
            'items' => $catalogItems,
        ];
    }

    if ($channelItems !== []) {
        $groups[] = [
            'label' => __('Channels'),
            'items' => $channelItems,
        ];
    }

    if ($orderItems !== []) {
        $groups[] = [
            'label' => __('Sales'),
            'items' => $orderItems,
        ];
    }

    if ($reportItems !== []) {
        $groups[] = [
            'label' => __('Reports'),
            'items' => $reportItems,
        ];
    }

    $groups[] = [
        'label' => __('Account'),
        'items' => [
            [
                'label' => __('Settings'),
                'icon' => 'cog',
                'active' => request()->routeIs('settings.*', 'profile.*'),
                'children' => $settingsItems,
            ],
        ],
    ];
@endphp

<div class="app-sidebar-shell relative flex h-full flex-col overflow-hidden text-zinc-100">
    <div class="app-sidebar-glow" aria-hidden="true"></div>

    <div
        class="relative flex h-16 shrink-0 items-center gap-3 px-4"
        @if ($collapsible) x-bind:class="sidebarCollapsed ? 'justify-center px-0' : ''" @endif
    >
        <a href="{{ $dashboardRoute }}" class="inline-flex items-center gap-2.5 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60" aria-label="{{ __(':app dashboard', ['app' => config('app.name')]) }}">
            <x-brand-mark class="h-9 w-9" />
            <span class="text-lg font-extrabold tracking-tight" @if ($collapsible) x-show="! sidebarCollapsed" @endif>{{ config('app.name') }}</span>
        </a>

        @if ($closable)
            <button
                type="button"
                @click="$dispatch('close-sidebar')"
                class="ml-auto inline-flex h-9 w-9 items-center justify-center rounded-lg text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60"
                aria-label="{{ __('Close menu') }}"
            >
                <x-icon name="x" class="h-4 w-4" />
            </button>
        @endif
    </div>

    <nav
        class="sidebar-scroll relative flex-1 overflow-y-auto px-3 py-6"
        aria-label="{{ __('Main navigation') }}"
    >
        <div class="flex flex-col gap-6">
            @foreach ($groups as $group)
                <div>
                    <p
                        class="px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-white/75"
                        @if ($collapsible) x-show="! sidebarCollapsed" @endif
                    >{{ $group['label'] }}</p>
                    <ul class="mt-3 flex flex-col gap-1">
                        @foreach ($group['items'] as $item)
                            @if (isset($item['children']))
                                <x-sidebar-submenu
                                    :label="$item['label']"
                                    :icon="$item['icon']"
                                    :children="$item['children']"
                                    :active="$item['active']"
                                    :collapsible="$collapsible"
                                    :id="$collapsible ? 'settings-submenu-rail' : 'settings-submenu-panel'"
                                />
                            @else
                                <x-sidebar-menu-item
                                    :label="$item['label']"
                                    :icon="$item['icon']"
                                    :href="$item['href']"
                                    :active="$item['active']"
                                    :collapsible="$collapsible"
                                />
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </nav>
</div>
