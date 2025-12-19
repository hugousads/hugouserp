{{-- Dynamic Sidebar Navigation --}}
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;
    use App\Models\Module;
    use App\Services\ModuleNavigationService;

    $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    $currentRoute = request()->route()?->getName() ?? '';
    $user = auth()->user();

    $isActive = function ($routes) use ($currentRoute) {
        $routes = (array) $routes;

        foreach ($routes as $route) {
            if ($route && str_starts_with($currentRoute, $route)) {
                return true;
            }
        }

        return false;
    };

    $safeRoute = function (?string $route) {
        return $route && Route::has($route) ? route($route) : '#';
    };

    $canAccess = function ($permission) use ($user) {
        if (! $permission) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can($permission);
    };

    $mapNavItem = null;
    $mapNavItem = function (array $item) use (&$mapNavItem) {
        return [
            'route' => $item['route'] ?? null,
            'icon' => $item['icon'] ?? '🧭',
            'label' => $item['label'] ?? __('Navigation'),
            'permission' => null,
            'children' => collect($item['children'] ?? [])->map($mapNavItem)->values()->all(),
        ];
    };

    $navigationService = app(ModuleNavigationService::class);
    $dynamicNavigation = $user ? $navigationService->getNavigationForUser($user, $user->branch_id) : [];

    $dynamicSections = collect($dynamicNavigation)
        ->groupBy('module_id')
        ->map(function ($items, $moduleId) use ($mapNavItem) {
            $first = $items->first();
            $moduleName = optional(Module::find($moduleId))->name
                ?? Str::headline($first['module_key'] ?? 'Module');

            return [
                'title' => $moduleName,
                'icon' => '🧩',
                'items' => $items->map($mapNavItem)->values()->all(),
                'is_dynamic' => true,
            ];
        })
        ->values()
        ->all();

    $baseSections = [
        [
            'title' => __('Workspace'),
            'icon' => '🌐',
            'items' => [
                ['route' => 'dashboard', 'icon' => '📊', 'label' => __('Dashboard'), 'permission' => 'dashboard.view'],
                ['route' => 'pos.terminal', 'icon' => '🧾', 'label' => __('POS Terminal'), 'permission' => 'pos.use', 'children' => [
                    ['route' => 'pos.daily.report', 'icon' => '📑', 'label' => __('Daily Report'), 'permission' => 'pos.daily-report.view'],
                ]],
                ['route' => 'admin.reports.index', 'icon' => '📈', 'label' => __('Reports Hub'), 'permission' => 'reports.view'],
            ],
        ],
        [
            'title' => __('Sales & Purchases'),
            'icon' => '💼',
            'items' => [
                ['route' => 'app.sales.index', 'icon' => '💰', 'label' => __('Sales'), 'permission' => 'sales.view', 'children' => [
                    ['route' => 'app.sales.returns.index', 'icon' => '↩️', 'label' => __('Returns'), 'permission' => 'sales.return'],
                    ['route' => 'app.sales.analytics', 'icon' => '📈', 'label' => __('Analytics'), 'permission' => 'sales.view'],
                ]],
                ['route' => 'app.purchases.index', 'icon' => '🛒', 'label' => __('Purchases'), 'permission' => 'purchases.view', 'children' => [
                    ['route' => 'app.purchases.returns.index', 'icon' => '↩️', 'label' => __('Returns'), 'permission' => 'purchases.return'],
                ]],
                ['route' => 'customers.index', 'icon' => '👤', 'label' => __('Customers'), 'permission' => 'customers.view'],
                ['route' => 'suppliers.index', 'icon' => '🏭', 'label' => __('Suppliers'), 'permission' => 'suppliers.view'],
            ],
        ],
        [
            'title' => __('Inventory & Warehouse'),
            'icon' => '📦',
            'items' => [
                ['route' => 'app.inventory.products.index', 'icon' => '📦', 'label' => __('Products'), 'permission' => 'inventory.products.view', 'children' => [
                    ['route' => 'app.inventory.categories.index', 'icon' => '📂', 'label' => __('Categories'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.units.index', 'icon' => '📏', 'label' => __('Units'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.stock-alerts', 'icon' => '⚠️', 'label' => __('Stock Alerts'), 'permission' => 'inventory.stock.alerts.view'],
                    ['route' => 'app.inventory.barcodes', 'icon' => '🏷️', 'label' => __('Barcodes'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.batches.index', 'icon' => '📦', 'label' => __('Batches'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.serials.index', 'icon' => '🔢', 'label' => __('Serial Numbers'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.vehicle-models', 'icon' => '🚗', 'label' => __('Vehicle Models'), 'permission' => 'spares.compatibility.manage'],
                ]],
                ['route' => 'app.inventory.index', 'icon' => '📊', 'label' => __('Inventory Overview'), 'permission' => 'inventory.products.view'],
                ['route' => 'app.warehouse.index', 'icon' => '🏭', 'label' => __('Warehouse'), 'permission' => 'warehouse.view'],
            ],
        ],
        [
            'title' => __('Finance & Banking'),
            'icon' => '💵',
            'items' => [
                ['route' => 'app.accounting.index', 'icon' => '🧮', 'label' => __('Accounting'), 'permission' => 'accounting.view'],
                ['route' => 'app.expenses.index', 'icon' => '💳', 'label' => __('Expenses'), 'permission' => 'expenses.view', 'children' => [
                    ['route' => 'app.expenses.categories.index', 'icon' => '📂', 'label' => __('Categories'), 'permission' => 'expenses.manage'],
                ]],
                ['route' => 'app.income.index', 'icon' => '💰', 'label' => __('Income'), 'permission' => 'income.view', 'children' => [
                    ['route' => 'app.income.categories.index', 'icon' => '🗂️', 'label' => __('Categories'), 'permission' => 'income.manage'],
                ]],
                ['route' => 'app.banking.accounts.index', 'icon' => '🏦', 'label' => __('Banking'), 'permission' => 'banking.view'],
                ['route' => 'admin.branches.index', 'icon' => '🏢', 'label' => __('Branches'), 'permission' => 'branches.view'],
            ],
        ],
        [
            'title' => __('People & HR'),
            'icon' => '👥',
            'items' => [
                ['route' => 'app.hrm.index', 'icon' => '🧑‍💼', 'label' => __('Human Resources'), 'permission' => 'hrm.employees.view', 'children' => [
                    ['route' => 'app.hrm.employees.index', 'icon' => '🧑‍💻', 'label' => __('Employees'), 'permission' => 'hrm.employees.view'],
                    ['route' => 'app.hrm.attendance.index', 'icon' => '🕒', 'label' => __('Attendance'), 'permission' => 'hrm.attendance.manage'],
                    ['route' => 'app.hrm.payroll.index', 'icon' => '💵', 'label' => __('Payroll'), 'permission' => 'hrm.payroll.manage'],
                    ['route' => 'app.hrm.shifts.index', 'icon' => '📅', 'label' => __('Shifts'), 'permission' => 'hrm.shifts.manage'],
                    ['route' => 'app.hrm.reports', 'icon' => '📊', 'label' => __('Reports'), 'permission' => 'hr.view-reports'],
                ]],
            ],
        ],
        [
            'title' => __('Operations'),
            'icon' => '⚙️',
            'items' => [
                ['route' => 'app.rental.index', 'icon' => '🏠', 'label' => __('Rental'), 'permission' => 'rental.units.view', 'children' => [
                    ['route' => 'app.rental.units.index', 'icon' => '📦', 'label' => __('Units'), 'permission' => 'rental.units.view'],
                    ['route' => 'app.rental.properties.index', 'icon' => '🏢', 'label' => __('Properties'), 'permission' => 'rental.properties.view'],
                    ['route' => 'app.rental.tenants.index', 'icon' => '🧑‍🤝‍🧑', 'label' => __('Tenants'), 'permission' => 'rental.tenants.view'],
                    ['route' => 'app.rental.contracts.index', 'icon' => '📝', 'label' => __('Contracts'), 'permission' => 'rental.contracts.view'],
                    ['route' => 'app.rental.reports', 'icon' => '📈', 'label' => __('Reports'), 'permission' => 'rental.view-reports'],
                ]],
                ['route' => 'app.manufacturing.index', 'icon' => '🏭', 'label' => __('Manufacturing'), 'permission' => 'manufacturing.view', 'children' => [
                    ['route' => 'app.manufacturing.boms.index', 'icon' => '🧾', 'label' => __('BOMs'), 'permission' => 'manufacturing.view'],
                    ['route' => 'app.manufacturing.orders.index', 'icon' => '🛠️', 'label' => __('Production Orders'), 'permission' => 'manufacturing.view'],
                    ['route' => 'app.manufacturing.work-centers.index', 'icon' => '🏗️', 'label' => __('Work Centers'), 'permission' => 'manufacturing.view'],
                ]],
                ['route' => 'app.fixed-assets.index', 'icon' => '🏛️', 'label' => __('Fixed Assets'), 'permission' => 'fixed-assets.view', 'children' => [
                    ['route' => 'app.fixed-assets.create', 'icon' => '➕', 'label' => __('Add Asset'), 'permission' => 'fixed-assets.view'],
                    ['route' => 'app.fixed-assets.depreciation', 'icon' => '📉', 'label' => __('Depreciation'), 'permission' => 'fixed-assets.view'],
                ]],
                ['route' => 'app.projects.index', 'icon' => '📂', 'label' => __('Projects'), 'permission' => 'projects.view', 'children' => [
                    ['route' => 'app.projects.create', 'icon' => '➕', 'label' => __('New Project'), 'permission' => 'projects.view'],
                ]],
                ['route' => 'app.documents.index', 'icon' => '📄', 'label' => __('Documents'), 'permission' => 'documents.view', 'children' => [
                    ['route' => 'app.documents.create', 'icon' => '⬆️', 'label' => __('Upload Document'), 'permission' => 'documents.view'],
                ]],
                ['route' => 'app.helpdesk.index', 'icon' => '🎫', 'label' => __('Helpdesk'), 'permission' => 'helpdesk.view', 'children' => [
                    ['route' => 'app.helpdesk.tickets.index', 'icon' => '🎟️', 'label' => __('Tickets'), 'permission' => 'helpdesk.view'],
                    ['route' => 'app.helpdesk.tickets.create', 'icon' => '➕', 'label' => __('New Ticket'), 'permission' => 'helpdesk.view'],
                    ['route' => 'app.helpdesk.categories.index', 'icon' => '📚', 'label' => __('Categories'), 'permission' => 'helpdesk.view'],
                ]],
            ],
        ],
        [
            'title' => __('Reporting'),
            'icon' => '📑',
            'items' => [
                ['route' => 'admin.reports.index', 'icon' => '📊', 'label' => __('Reports Hub'), 'permission' => 'reports.view', 'children' => [
                    ['route' => 'admin.reports.sales', 'icon' => '💰', 'label' => __('Sales'), 'permission' => 'sales.view-reports'],
                    ['route' => 'admin.reports.inventory', 'icon' => '📦', 'label' => __('Inventory'), 'permission' => 'inventory.view-reports'],
                    ['route' => 'admin.reports.pos', 'icon' => '🧾', 'label' => __('POS'), 'permission' => 'pos.view-reports'],
                    ['route' => 'admin.reports.aggregate', 'icon' => '🧮', 'label' => __('Aggregate'), 'permission' => 'reports.aggregate'],
                    ['route' => 'admin.reports.scheduled', 'icon' => '📅', 'label' => __('Scheduled'), 'permission' => 'reports.schedule'],
                    ['route' => 'admin.reports.templates', 'icon' => '📋', 'label' => __('Templates'), 'permission' => 'reports.templates'],
                ]],
            ],
        ],
        [
            'title' => __('Administration'),
            'icon' => '🛠️',
            'items' => [
                ['route' => 'admin.settings', 'icon' => '⚙️', 'label' => __('Settings'), 'permission' => 'settings.view'],
                ['route' => 'admin.users.index', 'icon' => '👥', 'label' => __('Users'), 'permission' => 'users.manage'],
                ['route' => 'admin.roles.index', 'icon' => '🔐', 'label' => __('Roles'), 'permission' => 'roles.manage'],
                ['route' => 'admin.branches.index', 'icon' => '🏢', 'label' => __('Branches'), 'permission' => 'branches.view'],
                ['route' => 'admin.modules.index', 'icon' => '🧩', 'label' => __('Modules'), 'permission' => 'modules.manage', 'children' => [
                    ['route' => 'admin.modules.create', 'icon' => '➕', 'label' => __('Add Module'), 'permission' => 'modules.manage'],
                    ['route' => 'admin.modules.product-fields', 'icon' => '🧬', 'label' => __('Product Fields'), 'permission' => 'modules.manage'],
                ]],
                ['route' => 'admin.stores.index', 'icon' => '🛍️', 'label' => __('Store Integrations'), 'permission' => 'stores.view', 'children' => [
                    ['route' => 'admin.stores.orders', 'icon' => '📦', 'label' => __('Store Orders'), 'permission' => 'stores.view'],
                    ['route' => 'admin.api-docs', 'icon' => '📖', 'label' => __('API Docs'), 'permission' => 'stores.view'],
                ]],
                ['route' => 'admin.translations.index', 'icon' => '🌍', 'label' => __('Translations'), 'permission' => 'settings.view'],
                ['route' => 'admin.currencies.index', 'icon' => '💱', 'label' => __('Currencies'), 'permission' => 'settings.view', 'children' => [
                    ['route' => 'admin.currency-rates.index', 'icon' => '📈', 'label' => __('Exchange Rates'), 'permission' => 'settings.view'],
                ]],
                ['route' => 'admin.media.index', 'icon' => '🖼️', 'label' => __('Media Library'), 'permission' => 'media.view'],
                ['route' => 'admin.logs.audit', 'icon' => '📜', 'label' => __('Audit Logs'), 'permission' => 'logs.audit.view', 'children' => [
                    ['route' => 'admin.activity-log', 'icon' => '🗒️', 'label' => __('Activity Log'), 'permission' => 'logs.audit.view'],
                ]],
            ],
        ],
    ];

    $menuSections = array_values(array_merge($baseSections, $dynamicSections));
@endphp

<aside
    class="sidebar-enhanced fixed md:relative inset-y-0 {{ $dir === 'rtl' ? 'right-0' : 'left-0' }} w-72 lg:w-80 bg-slate-950/95 text-slate-100 shadow-2xl z-50 flex flex-col transform transition-transform duration-300 ease-out"
    :class="sidebarOpen ? 'translate-x-0' : '{{ $dir === 'rtl' ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'"
    x-cloak
    x-data="{
        groups: {},
        init() {
            Object.keys(localStorage)
                .filter(key => key.startsWith('sidebar_section_'))
                .forEach(key => this.groups[key.replace('sidebar_section_', '')] = localStorage.getItem(key) === 'true');
        },
        toggle(key) {
            this.groups[key] = !this.groups[key];
            localStorage.setItem('sidebar_section_' + key, this.groups[key]);
        }
    }"
>
    {{-- Logo & User Section (Fixed at top) --}}
    <div class="sidebar-header flex-shrink-0 flex items-center justify-between px-4 py-4 border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold text-lg shadow-md group-hover:shadow-emerald-500/40 transition-all duration-300">
                {{ strtoupper(mb_substr(config('app.name', 'G'), 0, 1)) }}
            </span>
            <div class="flex flex-col min-w-0">
                <span class="text-sm font-semibold truncate text-white">{{ $user->name ?? 'User' }}</span>
                <span class="text-xs text-emerald-200 truncate">{{ $user?->roles?->first()?->name ?? __('User') }}</span>
            </div>
        </a>

        {{-- Mobile Close Button --}}
        <button @click="sidebarOpen = false" class="md:hidden p-2 rounded-lg hover:bg-slate-800 transition-colors" aria-label="Close sidebar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Scrollable Navigation (Independent scroll) --}}
    <nav class="sidebar-nav flex-1 overflow-y-auto py-4 px-3 space-y-3 custom-scrollbar">
        @foreach($menuSections as $sectionIndex => $section)
            @php
                $sectionKey = 'section_' . $sectionIndex;
                $hasActive = false;
                foreach ($section['items'] as $item) {
                    if ($isActive($item['route'])) {
                        $hasActive = true;
                        break;
                    }
                    foreach ($item['children'] ?? [] as $child) {
                        if ($isActive($child['route'])) {
                            $hasActive = true;
                            break 2;
                        }
                    }
                }
            @endphp

            <div class="sidebar-section" x-init="groups['{{ $sectionKey }}'] = groups['{{ $sectionKey }}'] ?? {{ $hasActive ? 'true' : 'false' }}">
                <button
                    @click="toggle('{{ $sectionKey }}')"
                    class="sidebar-section__header"
                    type="button"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-lg">{{ $section['icon'] }}</span>
                        <span class="font-semibold text-sm">{{ $section['title'] }}</span>
                    </div>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="groups['{{ $sectionKey }}'] ? 'rotate-180' : 'rotate-0'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <ul
                    x-show="groups['{{ $sectionKey }}']"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="space-y-1 mt-2"
                >
                    @foreach($section['items'] as $item)
                        @if($canAccess($item['permission'] ?? null) && $safeRoute($item['route']) !== '#')
                            <li x-data="{ open: {{ $isActive($item['route']) ? 'true' : 'false' }} }" class="sidebar-item">
                                @if(!empty($item['children']))
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        class="sidebar-link"
                                    >
                                        <span class="text-lg">{{ $item['icon'] }}</span>
                                        <div class="flex-1 flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium">{{ $item['label'] }}</span>
                                            <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                        @if($isActive($item['route']))
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        @endif
                                    </button>

                                    <ul x-show="open" x-transition class="sidebar-children mt-1 space-y-0.5">
                                        @foreach($item['children'] as $child)
                                            @if($canAccess($child['permission'] ?? null) && $safeRoute($child['route']) !== '#')
                                                <li>
                                                    <a
                                                        href="{{ $safeRoute($child['route']) }}"
                                                        @click="sidebarOpen = false"
                                                        class="sidebar-link-secondary {{ $isActive($child['route']) ? 'active' : '' }}"
                                                    >
                                                        <span class="text-base">{{ $child['icon'] }}</span>
                                                        <span class="text-sm">{{ $child['label'] }}</span>
                                                        @if($isActive($child['route']))
                                                            <span class="ml-auto rtl:mr-auto rtl:ml-0 w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                        @endif
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <a
                                        href="{{ $safeRoute($item['route']) }}"
                                        @click="sidebarOpen = false"
                                        class="sidebar-link {{ $isActive($item['route']) ? 'active' : '' }}"
                                    >
                                        <span class="text-lg">{{ $item['icon'] }}</span>
                                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                                        @if($isActive($item['route']))
                                            <span class="ml-auto rtl:mr-auto rtl:ml-0 w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                        @endif
                                    </a>
                                @endif
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Footer Section (Fixed at bottom) --}}
    <div class="sidebar-footer flex-shrink-0 border-t border-slate-800 bg-slate-900/70 backdrop-blur-xl">
        <div class="px-3 py-3 space-y-2">
            <div class="flex items-center justify-between text-[13px] text-slate-300">
                <span class="inline-flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    {{ __('Ready to work') }}
                </span>
                <span class="text-slate-400">LTR/RTL</span>
            </div>

            <div class="grid grid-cols-2 gap-2">
                @if($canAccess('modules.manage') && Route::has('admin.modules.create'))
                    <a href="{{ route('admin.modules.create') }}" @click="sidebarOpen = false" class="sidebar-chip">
                        <span>🧩</span>
                        <span class="text-xs font-semibold">{{ __('Add Module') }}</span>
                    </a>
                @endif
                @if($canAccess('profile.update') && Route::has('profile.edit'))
                    <a href="{{ route('profile.edit') }}" @click="sidebarOpen = false" class="sidebar-chip">
                        <span>👤</span>
                        <span class="text-xs font-semibold">{{ __('My Profile') }}</span>
                    </a>
                @endif
                @if(Route::has('notifications.center'))
                    <a href="{{ route('notifications.center') }}" @click="sidebarOpen = false" class="sidebar-chip">
                        <span>🔔</span>
                        <span class="text-xs font-semibold">{{ __('Alerts') }}</span>
                    </a>
                @endif
                @if(Route::has('support.center'))
                    <a href="{{ route('support.center') }}" @click="sidebarOpen = false" class="sidebar-chip">
                        <span>💬</span>
                        <span class="text-xs font-semibold">{{ __('Support') }}</span>
                    </a>
                @endif
            </div>
        </div>
    </div>
</aside>

<style>
    .sidebar-enhanced {
        height: 100vh;
        height: 100dvh;
    }

    .sidebar-section {
        @apply rounded-2xl bg-slate-900/60 border border-white/5 p-3 shadow-lg shadow-black/20;
    }

    .sidebar-section__header {
        @apply w-full flex items-center justify-between gap-2 px-2 py-1.5 text-slate-200 hover:text-white rounded-xl transition-all duration-200;
    }

    .sidebar-link {
        @apply flex items-center gap-3 px-3 py-2.5 rounded-xl text-white bg-gradient-to-r from-slate-800/80 via-slate-800/60 to-slate-900/60 border border-white/5 shadow-sm transition-all duration-300 ease-out;
    }

    .sidebar-link:hover {
        @apply shadow-lg -translate-y-0.5 ring-1 ring-emerald-500/30;
    }

    .sidebar-link.active {
        @apply ring-2 ring-emerald-400/40 shadow-lg scale-[1.01] bg-emerald-500/20 text-emerald-100;
    }

    .sidebar-link-secondary {
        @apply flex items-center gap-2 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800/80 hover:text-white transition-all duration-200;
    }

    .sidebar-link-secondary.active {
        @apply bg-emerald-500/20 text-emerald-100 border border-emerald-400/30;
    }

    .sidebar-chip {
        @apply flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-800/60 text-slate-100 border border-white/5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200;
    }

    .sidebar-children {
        border-inline-start: 1px solid rgba(51, 65, 85, 0.7);
        padding-inline-start: 0.75rem;
        margin-inline-start: 0.75rem;
    }

    .custom-scrollbar::-webkit-scrollbar { width: 8px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: rgba(15, 23, 42, 0.35); border-radius: 9999px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.35); border-radius: 9999px; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(148, 163, 184, 0.6); }
    .custom-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.35) rgba(15,23,42,0.35); }

    html[dir="rtl"] .sidebar-enhanced { border-left: 1px solid rgb(30 41 59); border-right: none; }
    html[dir="rtl"] .sidebar-section__header { justify-content: space-between; }

    @media (max-width: 768px) {
        .sidebar-enhanced { position: fixed; width: 85vw; max-width: 340px; }
        .sidebar-link, .sidebar-link-secondary { min-height: 44px; touch-action: manipulation; }
        .sidebar-nav { -webkit-overflow-scrolling: touch; }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            const activeItem = document.querySelector('.sidebar-link.active') || document.querySelector('.sidebar-link-secondary.active');
            if (activeItem) {
                const sidebarNav = document.querySelector('.sidebar-nav');
                if (sidebarNav) {
                    const navRect = sidebarNav.getBoundingClientRect();
                    const itemRect = activeItem.getBoundingClientRect();
                    const offset = itemRect.top - navRect.top - (navRect.height / 2) + (itemRect.height / 2);
                    sidebarNav.scrollBy({ top: offset, behavior: 'smooth' });
                }
            }
        }, 200);
    });
</script>
