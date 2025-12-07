{{-- resources/views/layouts/sidebar.blade.php --}}
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
@endphp
<aside
    class="hidden md:flex md:flex-col md:w-64 lg:w-72 bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 text-slate-100 shadow-xl z-20"
    :class="sidebarOpen ? 'block' : ''"
>
    {{-- Logo & User --}}
    <div class="flex items-center justify-between px-4 py-4 border-b border-slate-700">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white font-bold text-lg shadow-md group-hover:shadow-emerald-500/50 transition-all duration-300">
                {{ strtoupper(mb_substr(config('app.name', 'G'), 0, 1)) }}
            </span>
            <div class="flex flex-col">
                <span class="text-sm font-semibold truncate text-white">{{ $user->name ?? 'User' }}</span>
                <span class="text-xs text-slate-400">{{ $user?->roles?->first()?->name ?? __('User') }}</span>
            </div>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1.5">
        {{-- Main ERP Dashboard --}}
        @if($canAccess('dashboard.view'))
        <a href="{{ route('dashboard') }}"
           class="sidebar-link bg-gradient-to-r from-red-500 to-red-600 {{ $isActive('dashboard') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">📊</span>
            <span class="text-sm font-medium">{{ __('ERP Dashboard') }}</span>
            @if($isActive('dashboard'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Customer Info --}}
        @if($canAccess('customers.view'))
        <a href="{{ route('customers.index') }}"
           class="sidebar-link bg-gradient-to-r from-cyan-500 to-cyan-600 {{ $isActive('customers') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">👤</span>
            <span class="text-sm font-medium">{{ __('Customer Info') }}</span>
            @if($isActive('customers'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Suppliers --}}
        @if($canAccess('suppliers.view'))
        <a href="{{ route('suppliers.index') }}"
           class="sidebar-link bg-gradient-to-r from-violet-500 to-violet-600 {{ $isActive('suppliers') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏭</span>
            <span class="text-sm font-medium">{{ __('Suppliers') }}</span>
            @if($isActive('suppliers'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- POS Section --}}
        @if($canAccess('pos.use'))
        <div class="space-y-1">
            <a href="{{ route('pos.terminal') }}"
               class="sidebar-link bg-gradient-to-r from-amber-500 to-amber-600 {{ $isActive('pos.terminal') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">🧾</span>
                <span class="text-sm font-medium">{{ __('POS Terminal') }}</span>
                @if($isActive('pos.terminal'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            
            @if($canAccess('pos.daily-report.view'))
            <a href="{{ route('pos.daily.report') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('pos.daily') ? 'active' : '' }}">
                <span class="text-base">📑</span>
                <span class="text-sm">{{ __('Daily Report') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Sales Management --}}
        @if($canAccess('sales.view'))
        <div class="space-y-1">
            <a href="{{ route('sales.index') }}"
               class="sidebar-link bg-gradient-to-r from-green-500 to-green-600 {{ $isActive('sales.index') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">💰</span>
                <span class="text-sm font-medium">{{ __('Sales Management') }}</span>
                @if($isActive('sales.index'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            @if($canAccess('sales.return'))
            <a href="{{ route('sales.returns') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('sales.returns') ? 'active' : '' }}">
                <span class="text-base">↩️</span>
                <span class="text-sm">{{ __('Sales Returns') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Purchases Management --}}
        @if($canAccess('purchases.view'))
        <div class="space-y-1">
            <a href="{{ route('purchases.index') }}"
               class="sidebar-link bg-gradient-to-r from-purple-500 to-purple-600 {{ $isActive('purchases.index') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">🛒</span>
                <span class="text-sm font-medium">{{ __('Purchases') }}</span>
                @if($isActive('purchases.index'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            @if($canAccess('purchases.return'))
            <a href="{{ route('purchases.returns') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('purchases.returns') ? 'active' : '' }}">
                <span class="text-base">↩️</span>
                <span class="text-sm">{{ __('Purchase Returns') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Expenses Management --}}
        @if($canAccess('expenses.view'))
        <a href="{{ route('expenses.index') }}"
           class="sidebar-link bg-gradient-to-r from-slate-500 to-slate-600 {{ $isActive('expenses') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">📋</span>
            <span class="text-sm font-medium">{{ __('Expenses') }}</span>
            @if($isActive('expenses'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Income Management --}}
        @if($canAccess('income.view'))
        <a href="{{ route('income.index') }}"
           class="sidebar-link bg-gradient-to-r from-emerald-500 to-emerald-600 {{ $isActive('income') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">💵</span>
            <span class="text-sm font-medium">{{ __('Manage Income') }}</span>
            @if($isActive('income'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Branch Management --}}
        @if($canAccess('branches.view'))
        <a href="{{ route('admin.branches.index') }}"
           class="sidebar-link bg-gradient-to-r from-blue-500 to-blue-600 {{ $isActive('admin.branches') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏢</span>
            <span class="text-sm font-medium">{{ __('Branch Management') }}</span>
            @if($isActive('admin.branches'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Item Management --}}
        @if($canAccess('inventory.products.view'))
        <div class="space-y-1">
            <a href="{{ route('inventory.products.index') }}"
               class="sidebar-link bg-gradient-to-r from-teal-500 to-teal-600 {{ $isActive('inventory.products') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">📦</span>
                <span class="text-sm font-medium">{{ __('Item Management') }}</span>
                @if($isActive('inventory.products'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>

            @if($canAccess('spares.compatibility.manage'))
            <a href="{{ route('inventory.vehicle-models') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('inventory.vehicle-models') ? 'active' : '' }}">
                <span class="text-base">🚗</span>
                <span class="text-sm">{{ __('Vehicle Models') }}</span>
            </a>
            @endif

            @if($canAccess('inventory.stock.alerts.view'))
            <a href="{{ route('inventory.stock-alerts') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('inventory.stock-alerts') ? 'active' : '' }}">
                <span class="text-base">⚠️</span>
                <span class="text-sm">{{ __('Low Stock Alerts') }}</span>
            </a>
            @endif

            <a href="{{ route('inventory.categories.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('inventory.categories') ? 'active' : '' }}">
                <span class="text-base">📂</span>
                <span class="text-sm">{{ __('Categories') }}</span>
            </a>

            <a href="{{ route('inventory.units.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('inventory.units') ? 'active' : '' }}">
                <span class="text-base">📏</span>
                <span class="text-sm">{{ __('Units of Measure') }}</span>
            </a>

            <a href="{{ route('inventory.barcode-print') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('inventory.barcode-print') ? 'active' : '' }}">
                <span class="text-base">🏷️</span>
                <span class="text-sm">{{ __('Print Barcodes') }}</span>
            </a>
        </div>
        @endif

        {{-- Accounting Module --}}
        @if($canAccess('accounting.view'))
        <a href="{{ route('accounting.index') }}"
           class="sidebar-link bg-gradient-to-r from-indigo-500 to-indigo-600 {{ $isActive('accounting') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🧮</span>
            <span class="text-sm font-medium">{{ __('Accounting Module') }}</span>
            @if($isActive('accounting'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Warehouse --}}
        @if($canAccess('warehouse.view'))
        <a href="{{ route('warehouse.index') }}"
           class="sidebar-link bg-gradient-to-r from-orange-500 to-orange-600 {{ $isActive('warehouse') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏭</span>
            <span class="text-sm font-medium">{{ __('Manage Warehouse') }}</span>
            @if($isActive('warehouse'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Manufacturing Module --}}
        @if($canAccess('manufacturing.view'))
        <div class="space-y-1">
            <a href="{{ route('manufacturing.boms.index') }}"
               class="sidebar-link bg-gradient-to-r from-gray-500 to-gray-600 {{ $isActive('manufacturing') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">🏭</span>
                <span class="text-sm font-medium">{{ __('Manufacturing') }}</span>
                @if($isActive('manufacturing'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            
            <a href="{{ route('manufacturing.boms.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('manufacturing.boms') ? 'active' : '' }}">
                <span class="text-base">📋</span>
                <span class="text-sm">{{ __('Bills of Materials') }}</span>
            </a>
            
            <a href="{{ route('manufacturing.production-orders.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('manufacturing.production-orders') ? 'active' : '' }}">
                <span class="text-base">⚙️</span>
                <span class="text-sm">{{ __('Production Orders') }}</span>
            </a>
            
            <a href="{{ route('manufacturing.work-centers.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('manufacturing.work-centers') ? 'active' : '' }}">
                <span class="text-base">🔧</span>
                <span class="text-sm">{{ __('Work Centers') }}</span>
            </a>
        </div>
        @endif

        {{-- HR Module --}}
        @if($canAccess('hrm.employees.view'))
        <a href="{{ route('hrm.employees.index') }}"
           class="sidebar-link bg-gradient-to-r from-rose-500 to-rose-600 {{ $isActive('hrm') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">👔</span>
            <span class="text-sm font-medium">{{ __('Human Resources') }}</span>
            @if($isActive('hrm'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Rental Module --}}
        @if($canAccess('rental.units.view') || $canAccess('rentals.view'))
        <div class="space-y-1">
            <a href="{{ route('rental.units.index') }}"
               class="sidebar-link bg-gradient-to-r from-lime-500 to-lime-600 {{ $isActive('rental.units') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">🏠</span>
                <span class="text-sm font-medium">{{ __('Rental Management') }}</span>
                @if($isActive('rental.units'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            
            @if($canAccess('rentals.view'))
            <a href="{{ route('rental.properties.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('rental.properties') ? 'active' : '' }}">
                <span class="text-base">🏢</span>
                <span class="text-sm">{{ __('Properties') }}</span>
            </a>
            <a href="{{ route('rental.tenants.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('rental.tenants') ? 'active' : '' }}">
                <span class="text-base">👥</span>
                <span class="text-sm">{{ __('Tenants') }}</span>
            </a>
            @endif
            
            @if($canAccess('rental.contracts.view'))
            <a href="{{ route('rental.contracts.index') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('rental.contracts') ? 'active' : '' }}">
                <span class="text-base">📄</span>
                <span class="text-sm">{{ __('Contracts') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Admin Section --}}
        @if($canAccess('settings.view') || $canAccess('users.manage') || $canAccess('roles.manage') || $canAccess('modules.manage'))
        <div class="my-3 border-t border-slate-700"></div>
        <p class="px-3 text-xs uppercase tracking-wide text-slate-500 mb-2">{{ __('Administration') }}</p>
        
        {{-- System Settings --}}
        @if($canAccess('settings.view'))
        <a href="{{ route('admin.settings.system') }}"
           class="sidebar-link bg-gradient-to-r from-sky-500 to-sky-600 {{ $isActive('admin.settings.system') || $isActive('admin.settings.branch') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">⚙️</span>
            <span class="text-sm font-medium">{{ __('System Settings') }}</span>
            @if($isActive('admin.settings.system') || $isActive('admin.settings.branch'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- User Management --}}
        @if($canAccess('users.manage'))
        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link bg-gradient-to-r from-pink-500 to-pink-600 {{ $isActive('admin.users') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">👥</span>
            <span class="text-sm font-medium">{{ __('User Management') }}</span>
            @if($isActive('admin.users'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Role Management --}}
        @if($canAccess('roles.manage'))
        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link bg-gradient-to-r from-violet-500 to-violet-600 {{ $isActive('admin.roles') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🔐</span>
            <span class="text-sm font-medium">{{ __('Role Management') }}</span>
            @if($isActive('admin.roles'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Module Management --}}
        @if($canAccess('modules.manage'))
        <a href="{{ route('admin.modules.index') }}"
           class="sidebar-link bg-gradient-to-r from-fuchsia-500 to-fuchsia-600 {{ $isActive('admin.modules') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🧩</span>
            <span class="text-sm font-medium">{{ __('Module Management') }}</span>
            @if($isActive('admin.modules'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Store Integrations --}}
        @if($canAccess('store.manage'))
        <a href="{{ route('admin.stores.index') }}"
           class="sidebar-link bg-gradient-to-r from-indigo-500 to-indigo-600 {{ $isActive('admin.stores') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🔗</span>
            <span class="text-sm font-medium">{{ __('Store Integrations') }}</span>
            @if($isActive('admin.stores'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Translation Manager --}}
        @if($canAccess('settings.translations.manage'))
        <a href="{{ route('admin.settings.translations') }}"
           class="sidebar-link bg-gradient-to-r from-cyan-500 to-cyan-600 {{ $isActive('admin.settings.translations') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🌍</span>
            <span class="text-sm font-medium">{{ __('Translation Manager') }}</span>
            @if($isActive('admin.settings.translations'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Advanced Settings --}}
        @if($canAccess('settings.view'))
        <a href="{{ route('admin.settings.advanced') }}"
           class="sidebar-link bg-gradient-to-r from-rose-500 to-rose-600 {{ $isActive('admin.settings.advanced') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🔒</span>
            <span class="text-sm font-medium">{{ __('Advanced Settings') }}</span>
            @if($isActive('admin.settings.advanced'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endif

        {{-- Currency Management --}}
        @if($canAccess('settings.currency.manage'))
        <div class="space-y-1">
            <a href="{{ route('admin.settings.currencies') }}"
               class="sidebar-link bg-gradient-to-r from-yellow-500 to-yellow-600 {{ $isActive('admin.settings.currencies') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">💰</span>
                <span class="text-sm font-medium">{{ __('Currency Management') }}</span>
                @if($isActive('admin.settings.currencies'))
                    <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
                @endif
            </a>
            <a href="{{ route('admin.settings.currency-rates') }}"
               class="sidebar-link-secondary ms-4 {{ $isActive('admin.settings.currency-rates') ? 'active' : '' }}">
                <span class="text-base">💱</span>
                <span class="text-sm">{{ __('Exchange Rates') }}</span>
            </a>
        </div>
        @endif
        @endif

        {{-- Reports Section --}}
        @if($canAccess('reports.view') || $canAccess('reports.hub.view') || $canAccess('logs.audit.view'))
        <div class="my-3 border-t border-slate-700"></div>
        <p class="px-3 text-xs uppercase tracking-wide text-slate-500 mb-2">{{ __('Reports') }}</p>

        @if($canAccess('reports.hub.view'))
        <a href="{{ route('admin.reports.hub') }}"
           class="sidebar-link-secondary {{ $isActive('admin.reports.hub') ? 'active' : '' }}">
            <span class="text-base">📊</span>
            <span class="text-sm">{{ __('Reports Hub') }}</span>
        </a>
        @endif

        @if($canAccess('reports.pos.charts'))
        <a href="{{ route('admin.reports.pos.charts') }}"
           class="sidebar-link-secondary {{ $isActive('admin.reports.pos') ? 'active' : '' }}">
            <span class="text-base">📈</span>
            <span class="text-sm">{{ __('Sales Report') }}</span>
        </a>
        @endif

        @if($canAccess('reports.inventory.charts'))
        <a href="{{ route('admin.reports.inventory.charts') }}"
           class="sidebar-link-secondary {{ $isActive('admin.reports.inventory') ? 'active' : '' }}">
            <span class="text-base">📦</span>
            <span class="text-sm">{{ __('Inventory Report') }}</span>
        </a>
        @endif

        @if($canAccess('reports.sales.view'))
        <a href="{{ route('reports.sales-analytics') }}"
           class="sidebar-link-secondary {{ $isActive('reports.sales-analytics') ? 'active' : '' }}">
            <span class="text-base">📊</span>
            <span class="text-sm">{{ __('Sales Analytics') }}</span>
        </a>
        @endif

        @if($canAccess('store.reports.dashboard'))
        <a href="{{ route('admin.store.dashboard') }}"
           class="sidebar-link-secondary {{ $isActive('admin.store.dashboard') ? 'active' : '' }}">
            <span class="text-base">🏪</span>
            <span class="text-sm">{{ __('Store Dashboard') }}</span>
        </a>
        @endif

        @if($canAccess('logs.audit.view'))
        <a href="{{ route('admin.logs.audit') }}"
           class="sidebar-link-secondary {{ $isActive('admin.logs') ? 'active' : '' }}">
            <span class="text-base">📋</span>
            <span class="text-sm">{{ __('Audit Logs') }}</span>
        </a>
        @endif

        @if($canAccess('reports.scheduled.manage'))
        <a href="{{ route('admin.reports.schedules') }}"
           class="sidebar-link-secondary {{ $isActive('admin.reports.schedules') ? 'active' : '' }}">
            <span class="text-base">📅</span>
            <span class="text-sm">{{ __('Scheduled Reports') }}</span>
        </a>
        @endif
        @endif
    </nav>

    {{-- Language Switcher --}}
    <div class="border-t border-slate-700 p-3">
        <div class="flex items-center justify-center gap-2">
            <a href="?lang=ar" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 {{ app()->getLocale() === 'ar' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-slate-700 text-slate-300 hover:bg-slate-600 hover:text-white' }}">
                العربية
            </a>
            <a href="?lang=en" class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-300 {{ app()->getLocale() === 'en' ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/30' : 'bg-slate-700 text-slate-300 hover:bg-slate-600 hover:text-white' }}">
                English
            </a>
        </div>
    </div>

    {{-- User Profile Section --}}
    <div class="border-t border-slate-700 p-3 space-y-2">
        <a href="{{ route('profile.edit') }}" class="w-full flex items-center gap-2 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-all duration-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="text-sm font-medium">{{ __('My Profile') }}</span>
        </a>
        <a href="{{ route('preferences') }}" class="w-full flex items-center gap-2 px-4 py-2 rounded-lg text-slate-300 hover:bg-slate-700 hover:text-white transition-all duration-300">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <span class="text-sm font-medium">{{ __('Settings') }}</span>
        </a>
    </div>

    {{-- Logout --}}
    <div class="border-t border-slate-700 p-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-red-500/10 text-red-400 hover:bg-red-500/20 hover:text-red-300 transition-all duration-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                <span class="text-sm font-medium">{{ __('Logout') }}</span>
            </button>
        </form>
    </div>
</aside>
