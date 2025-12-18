{{-- Enhanced Sidebar with Collapsible Groups, Independent Scrolling, and Responsive Design --}}
@php
    $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    $currentRoute = request()->route()?->getName() ?? '';
    $user = auth()->user();
    
    $isActive = function($routes) use ($currentRoute) {
        if (is_string($routes)) {
            return str_starts_with($currentRoute, $routes);
        }
        foreach ($routes as $route) {
            if (str_starts_with($currentRoute, $route)) {
                return true;
            }
        }
        return false;
    };
    
    $canAccess = function($permission) use ($user) {
        if (!$user) return false;
        if ($user->hasRole('Super Admin')) return true;
        return $user->can($permission);
    };
    
    // Define menu structure with groups - Comprehensive ERP Navigation
    $menuGroups = [
        [
            'title' => __('Overview'),
            'icon' => '📊',
            'items' => [
                ['route' => 'dashboard', 'icon' => '📊', 'label' => __('Dashboard'), 'permission' => 'dashboard.view', 'gradient' => 'from-red-500 to-red-600'],
            ]
        ],
        [
            'title' => __('Contacts'),
            'icon' => '👥',
            'items' => [
                ['route' => 'customers.index', 'icon' => '👤', 'label' => __('Customers'), 'permission' => 'customers.view', 'gradient' => 'from-cyan-500 to-cyan-600'],
                ['route' => 'suppliers.index', 'icon' => '🏭', 'label' => __('Suppliers'), 'permission' => 'suppliers.view', 'gradient' => 'from-violet-500 to-violet-600'],
            ]
        ],
        [
            'title' => __('Sales & POS'),
            'icon' => '💰',
            'items' => [
                ['route' => 'pos.terminal', 'icon' => '🧾', 'label' => __('POS Terminal'), 'permission' => 'pos.use', 'gradient' => 'from-amber-500 to-amber-600', 'children' => [
                    ['route' => 'pos.daily.report', 'icon' => '📑', 'label' => __('Daily Report'), 'permission' => 'pos.daily-report.view'],
                ]],
                ['route' => 'app.sales.index', 'icon' => '💰', 'label' => __('Sales'), 'permission' => 'sales.view', 'gradient' => 'from-green-500 to-green-600', 'children' => [
                    ['route' => 'app.sales.returns.index', 'icon' => '↩️', 'label' => __('Returns'), 'permission' => 'sales.return'],
                    ['route' => 'app.sales.analytics', 'icon' => '📈', 'label' => __('Analytics'), 'permission' => 'sales.view'],
                ]],
            ]
        ],
        [
            'title' => __('Purchases & Expenses'),
            'icon' => '🛒',
            'items' => [
                ['route' => 'app.purchases.index', 'icon' => '🛒', 'label' => __('Purchases'), 'permission' => 'purchases.view', 'gradient' => 'from-purple-500 to-purple-600', 'children' => [
                    ['route' => 'app.purchases.returns.index', 'icon' => '↩️', 'label' => __('Returns'), 'permission' => 'purchases.return'],
                ]],
                ['route' => 'app.expenses.index', 'icon' => '📋', 'label' => __('Expenses'), 'permission' => 'expenses.view', 'gradient' => 'from-slate-500 to-slate-600', 'children' => [
                    ['route' => 'app.expenses.categories.index', 'icon' => '📂', 'label' => __('Categories'), 'permission' => 'expenses.view'],
                ]],
            ]
        ],
        [
            'title' => __('Inventory & Warehouse'),
            'icon' => '📦',
            'items' => [
                ['route' => 'app.inventory.products.index', 'icon' => '📦', 'label' => __('Products'), 'permission' => 'inventory.products.view', 'gradient' => 'from-teal-500 to-teal-600', 'children' => [
                    ['route' => 'app.inventory.categories.index', 'icon' => '📂', 'label' => __('Categories'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.units.index', 'icon' => '📏', 'label' => __('Units'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.stock-alerts', 'icon' => '⚠️', 'label' => __('Stock Alerts'), 'permission' => 'inventory.stock.alerts.view'],
                    ['route' => 'app.inventory.barcodes', 'icon' => '🏷️', 'label' => __('Barcodes'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.batches.index', 'icon' => '📦', 'label' => __('Batches'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.serials.index', 'icon' => '🔢', 'label' => __('Serials'), 'permission' => 'inventory.products.view'],
                    ['route' => 'app.inventory.vehicle-models', 'icon' => '🚗', 'label' => __('Vehicle Models'), 'permission' => 'spares.compatibility.manage'],
                ]],
                ['route' => 'app.warehouse.index', 'icon' => '🏭', 'label' => __('Warehouse'), 'permission' => 'warehouse.view', 'gradient' => 'from-orange-500 to-orange-600'],
            ]
        ],
        [
            'title' => __('Finance & Banking'),
            'icon' => '💵',
            'items' => [
                ['route' => 'app.accounting.index', 'icon' => '🧮', 'label' => __('Accounting'), 'permission' => 'accounting.view', 'gradient' => 'from-indigo-500 to-indigo-600'],
                ['route' => 'app.income.index', 'icon' => '💵', 'label' => __('Income'), 'permission' => 'income.view', 'gradient' => 'from-emerald-500 to-emerald-600', 'children' => [
                    ['route' => 'app.income.categories.index', 'icon' => '📂', 'label' => __('Categories'), 'permission' => 'income.view'],
                ]],
                ['route' => 'app.banking.accounts.index', 'icon' => '🏦', 'label' => __('Banking'), 'permission' => 'banking.view', 'gradient' => 'from-blue-500 to-blue-600'],
                ['route' => 'admin.branches.index', 'icon' => '🏢', 'label' => __('Branches'), 'permission' => 'branches.view', 'gradient' => 'from-blue-600 to-blue-700'],
            ]
        ],
        [
            'title' => __('Human Resources'),
            'icon' => '👥',
            'items' => [
                ['route' => 'app.hrm.employees.index', 'icon' => '👥', 'label' => __('Employees'), 'permission' => 'hrm.employees.view', 'gradient' => 'from-rose-500 to-rose-600', 'children' => [
                    ['route' => 'app.hrm.attendance.index', 'icon' => '📅', 'label' => __('Attendance'), 'permission' => 'hrm.attendance.view'],
                    ['route' => 'app.hrm.shifts.index', 'icon' => '⏰', 'label' => __('Shifts'), 'permission' => 'hrm.shifts.view'],
                    ['route' => 'app.hrm.payroll.index', 'icon' => '💰', 'label' => __('Payroll'), 'permission' => 'hrm.payroll.view'],
                    ['route' => 'app.hrm.reports', 'icon' => '📊', 'label' => __('HR Reports'), 'permission' => 'hrm.reports.view'],
                ]],
            ]
        ],
        [
            'title' => __('Operations'),
            'icon' => '⚙️',
            'items' => [
                ['route' => 'app.rental.properties.index', 'icon' => '🏠', 'label' => __('Rental'), 'permission' => 'rental.units.view', 'gradient' => 'from-sky-500 to-sky-600', 'children' => [
                    ['route' => 'app.rental.properties.index', 'icon' => '🏢', 'label' => __('Properties'), 'permission' => 'rental.properties.view'],
                    ['route' => 'app.rental.units.index', 'icon' => '🚪', 'label' => __('Units'), 'permission' => 'rental.units.view'],
                    ['route' => 'app.rental.tenants.index', 'icon' => '👤', 'label' => __('Tenants'), 'permission' => 'rental.tenants.view'],
                    ['route' => 'app.rental.contracts.index', 'icon' => '📄', 'label' => __('Contracts'), 'permission' => 'rental.contracts.view'],
                ]],
                ['route' => 'app.manufacturing.boms.index', 'icon' => '🏭', 'label' => __('Manufacturing'), 'permission' => 'manufacturing.view', 'gradient' => 'from-gray-500 to-gray-600', 'children' => [
                    ['route' => 'app.manufacturing.boms.index', 'icon' => '📋', 'label' => __('BOMs'), 'permission' => 'manufacturing.view'],
                    ['route' => 'app.manufacturing.orders.index', 'icon' => '⚙️', 'label' => __('Orders'), 'permission' => 'manufacturing.view'],
                    ['route' => 'app.manufacturing.work-centers.index', 'icon' => '🔧', 'label' => __('Work Centers'), 'permission' => 'manufacturing.view'],
                ]],
                ['route' => 'app.fixed-assets.index', 'icon' => '🏗️', 'label' => __('Fixed Assets'), 'permission' => 'fixed-assets.view', 'gradient' => 'from-stone-500 to-stone-600'],
            ]
        ],
        [
            'title' => __('Reports'),
            'icon' => '📊',
            'items' => [
                ['route' => 'admin.reports.index', 'icon' => '📊', 'label' => __('Reports'), 'permission' => 'reports.view', 'gradient' => 'from-purple-500 to-purple-600', 'children' => [
                    ['route' => 'admin.reports.inventory', 'icon' => '📦', 'label' => __('Inventory'), 'permission' => 'reports.view'],
                    ['route' => 'admin.reports.pos', 'icon' => '🧾', 'label' => __('POS'), 'permission' => 'reports.view'],
                    ['route' => 'admin.reports.scheduled', 'icon' => '📅', 'label' => __('Scheduled'), 'permission' => 'reports.view'],
                ]],
            ]
        ],
        [
            'title' => __('Administration'),
            'icon' => '⚙️',
            'items' => [
                ['route' => 'admin.settings', 'icon' => '⚙️', 'label' => __('Settings'), 'permission' => 'settings.view', 'gradient' => 'from-sky-500 to-sky-600'],
                ['route' => 'admin.users.index', 'icon' => '👥', 'label' => __('Users'), 'permission' => 'users.manage', 'gradient' => 'from-pink-500 to-pink-600'],
                ['route' => 'admin.roles.index', 'icon' => '🔐', 'label' => __('Roles'), 'permission' => 'roles.manage', 'gradient' => 'from-violet-500 to-violet-600'],
                ['route' => 'admin.modules.index', 'icon' => '🧩', 'label' => __('Modules'), 'permission' => 'modules.manage', 'gradient' => 'from-fuchsia-500 to-fuchsia-600'],
                ['route' => 'admin.stores.index', 'icon' => '🔗', 'label' => __('Store Integration'), 'permission' => 'store.manage', 'gradient' => 'from-indigo-500 to-indigo-600', 'children' => [
                    ['route' => 'admin.stores.orders', 'icon' => '📦', 'label' => __('Store Orders'), 'permission' => 'store.manage'],
                    ['route' => 'admin.api-docs', 'icon' => '📖', 'label' => __('API Docs'), 'permission' => 'store.manage'],
                ]],
                ['route' => 'admin.translations.index', 'icon' => '🌍', 'label' => __('Translations'), 'permission' => 'settings.view', 'gradient' => 'from-cyan-500 to-cyan-600'],
                ['route' => 'admin.currencies.index', 'icon' => '💱', 'label' => __('Currencies'), 'permission' => 'settings.view', 'gradient' => 'from-yellow-500 to-yellow-600', 'children' => [
                    ['route' => 'admin.currency-rates.index', 'icon' => '📈', 'label' => __('Exchange Rates'), 'permission' => 'settings.view'],
                ]],
                ['route' => 'admin.media.index', 'icon' => '🖼️', 'label' => __('Media Library'), 'permission' => 'settings.view', 'gradient' => 'from-rose-500 to-rose-600'],
                ['route' => 'admin.logs.audit', 'icon' => '📜', 'label' => __('Audit Logs'), 'permission' => 'logs.audit.view', 'gradient' => 'from-gray-500 to-gray-600', 'children' => [
                    ['route' => 'admin.activity-log', 'icon' => '📋', 'label' => __('Activity Log'), 'permission' => 'logs.audit.view'],
                ]],
            ]
        ],
    ];
@endphp

<aside
    class="sidebar-enhanced fixed md:relative inset-y-0 {{ $dir === 'rtl' ? 'right-0' : 'left-0' }} w-72 lg:w-80 bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 text-slate-100 shadow-2xl z-50 flex flex-col transform transition-transform duration-300 ease-out"
    :class="sidebarOpen ? 'translate-x-0' : '{{ $dir === 'rtl' ? 'translate-x-full' : '-translate-x-full' }} md:translate-x-0'"
    x-cloak
    x-data="{
        groups: {},
        initGroup(key, hasActive) {
            const stored = localStorage.getItem('sidebar_group_' + key);
            this.groups[key] = stored !== null ? stored === 'true' : hasActive;
        },
        toggleGroup(key) {
            this.groups[key] = !this.groups[key];
            localStorage.setItem('sidebar_group_' + key, this.groups[key]);
        }
    }"
>
    {{-- Logo & User Section (Fixed at top) --}}
    <div class="sidebar-header flex-shrink-0 flex items-center justify-between px-4 py-4 border-b border-slate-700 bg-slate-900/50 backdrop-blur">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold text-lg shadow-md group-hover:shadow-emerald-500/50 transition-all duration-300">
                {{ strtoupper(mb_substr(config('app.name', 'G'), 0, 1)) }}
            </span>
            <div class="flex flex-col min-w-0">
                <span class="text-sm font-semibold truncate text-white">{{ $user->name ?? 'User' }}</span>
                <span class="text-xs text-slate-400 truncate">{{ $user?->roles?->first()?->name ?? __('User') }}</span>
            </div>
        </a>
        
        {{-- Mobile Close Button --}}
        <button @click="sidebarOpen = false" class="md:hidden p-2 rounded-lg hover:bg-slate-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Scrollable Navigation (Independent scroll) --}}
    <nav class="sidebar-nav flex-1 overflow-y-auto py-3 px-2 space-y-2 custom-scrollbar">
        @foreach($menuGroups as $groupIndex => $group)
            @php
                $groupKey = 'group_' . $groupIndex;
                // Check if any item in group is active
                $hasActive = false;
                foreach ($group['items'] as $item) {
                    if ($canAccess($item['permission'] ?? 'none') && $isActive($item['route'])) {
                        $hasActive = true;
                        break;
                    }
                }
            @endphp
            
            {{-- Group Header with Collapse/Expand --}}
            <div x-init="initGroup('{{ $groupKey }}', {{ $hasActive ? 'true' : 'false' }})">
                <button 
                    @click="toggleGroup('{{ $groupKey }}')"
                    class="w-full flex items-center gap-2 px-3 py-2 text-xs uppercase tracking-wider text-slate-400 hover:text-white hover:bg-slate-800/50 rounded-lg transition-all duration-200 group"
                >
                    <span class="text-sm">{{ $group['icon'] }}</span>
                    <span class="flex-1 text-start font-semibold">{{ $group['title'] }}</span>
                    <svg 
                        class="w-4 h-4 transition-transform duration-200" 
                        :class="groups['{{ $groupKey }}'] ? 'rotate-0' : '-rotate-90'"
                        fill="none" 
                        stroke="currentColor" 
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                
                {{-- Group Items --}}
                <ul 
                    x-show="groups['{{ $groupKey }}']"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="space-y-1 mt-1"
                >
                    @foreach($group['items'] as $item)
                        @if($canAccess($item['permission'] ?? 'none'))
                            <li x-data="{ childrenOpen: {{ $isActive($item['route']) ? 'true' : 'false' }} }">
                                @if(isset($item['children']) && count($item['children']) > 0)
                                    {{-- Item with children --}}
                                    <button 
                                        @click="childrenOpen = !childrenOpen" 
                                        type="button"
                                        class="w-full sidebar-link bg-gradient-to-r {{ $item['gradient'] ?? 'from-slate-600 to-slate-700' }} {{ $isActive($item['route']) ? 'ring-2 ring-white/30' : '' }}"
                                    >
                                        <span class="text-lg">{{ $item['icon'] }}</span>
                                        <span class="text-sm font-medium flex-1 text-start">{{ $item['label'] }}</span>
                                        <svg 
                                            class="w-4 h-4 transition-transform duration-200" 
                                            :class="childrenOpen ? 'rotate-180' : ''"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                        @if($isActive($item['route']))
                                            <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                                        @endif
                                    </button>
                                    
                                    {{-- Children --}}
                                    <ul 
                                        x-show="childrenOpen"
                                        x-transition:enter="transition ease-out duration-200"
                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                        x-transition:enter-end="opacity-100 translate-y-0"
                                        class="ms-4 mt-1 space-y-0.5"
                                    >
                                        @foreach($item['children'] as $child)
                                            @if($canAccess($child['permission'] ?? 'none'))
                                                <li>
                                                    <a 
                                                        href="{{ route($child['route']) }}"
                                                        @click="sidebarOpen = false"
                                                        class="sidebar-link-secondary {{ $isActive($child['route']) ? 'active bg-slate-800/80' : '' }}"
                                                    >
                                                        <span class="text-base">{{ $child['icon'] }}</span>
                                                        <span class="text-sm">{{ $child['label'] }}</span>
                                                        @if($isActive($child['route']))
                                                            <span class="ms-auto w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                                        @endif
                                                    </a>
                                                </li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    {{-- Simple item without children --}}
                                    <a 
                                        href="{{ route($item['route']) }}"
                                        @click="sidebarOpen = false"
                                        class="sidebar-link bg-gradient-to-r {{ $item['gradient'] ?? 'from-slate-600 to-slate-700' }} {{ $isActive($item['route']) ? 'ring-2 ring-white/30' : '' }}"
                                    >
                                        <span class="text-lg">{{ $item['icon'] }}</span>
                                        <span class="text-sm font-medium">{{ $item['label'] }}</span>
                                        @if($isActive($item['route']))
                                            <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
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
    <div class="sidebar-footer flex-shrink-0 px-4 py-3 border-t border-slate-700 bg-slate-900/50 backdrop-blur">
        <div class="text-xs text-slate-500 text-center">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </div>
</aside>

<style>
/* Enhanced Sidebar Styles */
.sidebar-enhanced {
    height: 100vh;
    height: 100dvh; /* Dynamic viewport height for mobile */
}

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.3);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 3px;
    transition: background 0.2s;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(148, 163, 184, 0.5);
}

/* Firefox */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.3) rgba(15, 23, 42, 0.3);
}

/* Smooth scroll behavior */
.sidebar-nav {
    scroll-behavior: smooth;
    overscroll-behavior: contain;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    .sidebar-enhanced {
        position: fixed;
        height: 100vh;
        height: 100dvh;
        width: 85vw;
        max-width: 320px;
    }
    
    /* Touch-friendly sizing */
    .sidebar-link,
    .sidebar-link-secondary {
        min-height: 44px;
        touch-action: manipulation;
    }
    
    /* Prevent overscroll on mobile */
    .sidebar-nav {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-y: contain;
    }
}

/* RTL Support */
html[dir="rtl"] .sidebar-enhanced {
    border-left: 1px solid rgb(51, 65, 85);
    border-right: none;
}

html[dir="rtl"] .ms-4 {
    margin-right: 1rem;
    margin-left: 0;
}

/* Enhanced link styles */
.sidebar-link {
    @apply flex items-center gap-2 px-3 py-2.5 rounded-xl text-white shadow-md hover:shadow-lg transition-all duration-200 hover:scale-[1.02];
}

.sidebar-link-secondary {
    @apply flex items-center gap-2 px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800 hover:text-white transition-all duration-200;
}

.sidebar-link-secondary.active {
    @apply bg-slate-800/80 text-white;
}

/* Animation for active indicators */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>
