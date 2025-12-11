{{-- resources/views/layouts/sidebar-organized.blade.php --}}
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
    
    // Check if module is enabled (honors settings overrides, branch-specific config, and config fallbacks)
    // Returns true if no branch context exists (backward compatible default behavior)
    $moduleEnabled = function($moduleName) {
        $branchId = current_branch_id();
        
        // 1. Check branch-specific setting override (highest priority)
        if ($branchId) {
            $branchSetting = setting("modules.{$moduleName}.branch.{$branchId}.enabled");
            if ($branchSetting !== null) {
                return (bool) $branchSetting;
            }
        }
        
        // 2. Check global setting override (medium priority)
        $globalSetting = setting("modules.{$moduleName}.enabled");
        if ($globalSetting !== null) {
            return (bool) $globalSetting;
        }
        
        // 3. Fallback to config file (lowest priority)
        return (bool) config("modules.{$moduleName}.enabled", true);
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
                {{ strtoupper(mb_substr(setting('general.company_name', config('app.name', 'H')), 0, 1)) }}
            </span>
            <div class="flex flex-col">
                <span class="text-sm font-semibold truncate text-white">{{ $user->name ?? 'User' }}</span>
                <span class="text-xs text-slate-400">{{ $user?->roles?->first()?->name ?? __('User') }}</span>
            </div>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1">
        {{-- Dashboard Section --}}
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Dashboard') }}</span>
            </div>
            @if($canAccess('dashboard.view'))
            <a href="{{ route('dashboard') }}"
               class="sidebar-link bg-gradient-to-r from-red-500 to-red-600 {{ $isActive('dashboard') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">📊</span>
                <span class="text-sm font-medium">{{ __('Home') }}</span>
            </a>
            @endif
        </div>

        {{-- Sales Section --}}
        @if($moduleEnabled('sales') && ($canAccess('pos.use') || $canAccess('sales.view') || $canAccess('customers.view')))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Sales') }}</span>
            </div>
            
            @if($canAccess('pos.use'))
            <a href="{{ route('pos.terminal') }}"
               class="sidebar-link bg-gradient-to-r from-amber-500 to-amber-600 {{ $isActive('pos.terminal') ? 'active ring-2 ring-white/30' : '' }}">
                <span class="text-lg">🧾</span>
                <span class="text-sm font-medium">{{ __('POS') }}</span>
            </a>
            @endif
            
            @if($canAccess('sales.view'))
            <a href="{{ route('app.sales.index') }}"
               class="sidebar-link-secondary {{ $isActive('sales.index') ? 'active' : '' }}">
                <span class="text-base">💰</span>
                <span class="text-sm">{{ __('Sales Orders') }}</span>
            </a>
            @endif
            
            @if($canAccess('sales.return'))
            <a href="{{ route('app.sales.returns.index') }}"
               class="sidebar-link-secondary {{ $isActive('sales.returns') ? 'active' : '' }}">
                <span class="text-base">↩️</span>
                <span class="text-sm">{{ __('Returns') }}</span>
            </a>
            @endif
            
            @if($canAccess('customers.view'))
            <a href="{{ route('customers.index') }}"
               class="sidebar-link-secondary {{ $isActive('customers') ? 'active' : '' }}">
                <span class="text-base">👤</span>
                <span class="text-sm">{{ __('Customers') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Purchases Section --}}
        @if($moduleEnabled('purchases') && ($canAccess('purchases.view') || $canAccess('suppliers.view')))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Purchases') }}</span>
            </div>
            
            @if($canAccess('purchases.view'))
            <a href="{{ route('app.purchases.index') }}"
               class="sidebar-link-secondary {{ $isActive('purchases.index') ? 'active' : '' }}">
                <span class="text-base">🛒</span>
                <span class="text-sm">{{ __('Purchase Orders') }}</span>
            </a>
            @endif
            
            @if($canAccess('suppliers.view'))
            <a href="{{ route('suppliers.index') }}"
               class="sidebar-link-secondary {{ $isActive('suppliers') ? 'active' : '' }}">
                <span class="text-base">🏭</span>
                <span class="text-sm">{{ __('Suppliers') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Inventory Section --}}
        @if($moduleEnabled('inventory') && $canAccess('inventory.products.view'))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Inventory') }}</span>
            </div>
            
            <a href="{{ route('app.inventory.products.index') }}"
               class="sidebar-link-secondary {{ $isActive('inventory.products') ? 'active' : '' }}">
                <span class="text-base">📦</span>
                <span class="text-sm">{{ __('Products') }}</span>
            </a>
            
            <a href="{{ route('app.inventory.categories.index') }}"
               class="sidebar-link-secondary {{ $isActive('inventory.categories') ? 'active' : '' }}">
                <span class="text-base">📂</span>
                <span class="text-sm">{{ __('Categories') }}</span>
            </a>
            
            @if($canAccess('warehouse.view'))
            <a href="{{ route('app.warehouse.index') }}"
               class="sidebar-link-secondary {{ $isActive('warehouse') ? 'active' : '' }}">
                <span class="text-base">🏭</span>
                <span class="text-sm">{{ __('Warehouses') }}</span>
            </a>
            @endif
            
            @if($canAccess('inventory.stock.alerts.view'))
            <a href="{{ route('app.inventory.stock-alerts') }}"
               class="sidebar-link-secondary {{ $isActive('inventory.stock-alerts') ? 'active' : '' }}">
                <span class="text-base">⚠️</span>
                <span class="text-sm">{{ __('Low Stock Alerts') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Rental Section --}}
        @if($moduleEnabled('rental') && ($canAccess('rental.units.view') || $canAccess('rentals.view')))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Rental') }}</span>
            </div>
            
            @if($canAccess('rentals.view'))
            <a href="{{ route('app.rental.properties.index') }}"
               class="sidebar-link-secondary {{ $isActive('app.rental.properties') ? 'active' : '' }}">
                <span class="text-base">🏢</span>
                <span class="text-sm">{{ __('Properties') }}</span>
            </a>
            
            <a href="{{ route('app.rental.units.index') }}"
               class="sidebar-link-secondary {{ $isActive('app.rental.units') ? 'active' : '' }}">
                <span class="text-base">🏠</span>
                <span class="text-sm">{{ __('Units') }}</span>
            </a>
            
            <a href="{{ route('app.rental.tenants.index') }}"
               class="sidebar-link-secondary {{ $isActive('app.rental.tenants') ? 'active' : '' }}">
                <span class="text-base">👥</span>
                <span class="text-sm">{{ __('Tenants') }}</span>
            </a>
            @endif
            
            @if($canAccess('rental.contracts.view'))
            <a href="{{ route('app.rental.contracts.index') }}"
               class="sidebar-link-secondary {{ $isActive('rental.contracts') ? 'active' : '' }}">
                <span class="text-base">📄</span>
                <span class="text-sm">{{ __('Contracts') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Accounting & Banking Section --}}
        @if($moduleEnabled('accounting') && ($canAccess('accounting.view') || $canAccess('banking.view')))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Accounting & Banking') }}</span>
            </div>
            
            @if($canAccess('accounting.view'))
            <a href="{{ route('app.accounting.index') }}"
               class="sidebar-link-secondary {{ $isActive('app.accounting') ? 'active' : '' }}">
                <span class="text-base">🧮</span>
                <span class="text-sm">{{ __('Chart of Accounts') }}</span>
            </a>
            @endif
            
            @if($canAccess('banking.view'))
            <a href="{{ route('app.banking.accounts.index') }}"
               class="sidebar-link-secondary {{ $isActive('banking') ? 'active' : '' }}">
                <span class="text-base">🏦</span>
                <span class="text-sm">{{ __('Banks') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- HRM Section --}}
        @if($moduleEnabled('hrm') && $canAccess('hrm.employees.view'))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('HRM') }}</span>
            </div>
            
            <a href="{{ route('app.hrm.employees.index') }}"
               class="sidebar-link-secondary {{ $isActive('app.hrm.employees') ? 'active' : '' }}">
                <span class="text-base">👔</span>
                <span class="text-sm">{{ __('Employees') }}</span>
            </a>
        </div>
        @endif

        {{-- Manufacturing Section --}}
        @if($moduleEnabled('manufacturing') && $canAccess('manufacturing.view'))
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Manufacturing') }}</span>
            </div>
            
            <a href="{{ route('app.manufacturing.boms.index') }}"
               class="sidebar-link-secondary {{ $isActive('manufacturing.boms') ? 'active' : '' }}">
                <span class="text-base">📋</span>
                <span class="text-sm">{{ __('Bills of Materials') }}</span>
            </a>
            
            <a href="{{ route('app.manufacturing.orders.index') }}"
               class="sidebar-link-secondary {{ $isActive('manufacturing.production-orders') ? 'active' : '' }}">
                <span class="text-base">⚙️</span>
                <span class="text-sm">{{ __('Production Orders') }}</span>
            </a>
        </div>
        @endif

        {{-- Reports Section --}}
        @if($canAccess('reports.view') || $canAccess('reports.hub.view'))
        <div class="my-3 border-t border-slate-700"></div>
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Reports') }}</span>
            </div>
            
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
                <span class="text-sm">{{ __('Sales Reports') }}</span>
            </a>
            @endif
            
            @if($canAccess('reports.inventory.charts'))
            <a href="{{ route('admin.reports.inventory.charts') }}"
               class="sidebar-link-secondary {{ $isActive('admin.reports.inventory') ? 'active' : '' }}">
                <span class="text-base">📦</span>
                <span class="text-sm">{{ __('Inventory Reports') }}</span>
            </a>
            @endif
            
            @if($canAccess('logs.audit.view'))
            <a href="{{ route('admin.logs.audit') }}"
               class="sidebar-link-secondary {{ $isActive('admin.logs') ? 'active' : '' }}">
                <span class="text-base">📋</span>
                <span class="text-sm">{{ __('Audit Logs') }}</span>
            </a>
            @endif
        </div>
        @endif

        {{-- Settings Section --}}
        @if($canAccess('settings.view') || $canAccess('users.manage') || $canAccess('roles.manage') || $canAccess('modules.manage'))
        <div class="my-3 border-t border-slate-700"></div>
        <div class="sidebar-section mb-4">
            <div class="px-3 mb-2">
                <span class="text-xs uppercase tracking-wide text-slate-500 font-semibold">{{ __('Settings') }}</span>
            </div>
            
            @if($canAccess('settings.view'))
            <a href="{{ route('admin.settings.system') }}"
               class="sidebar-link-secondary {{ $isActive('admin.settings.system') || $isActive('admin.settings.branch') ? 'active' : '' }}">
                <span class="text-base">⚙️</span>
                <span class="text-sm">{{ __('General') }}</span>
            </a>
            @endif
            
            @if($canAccess('modules.manage'))
            <a href="{{ route('admin.modules.index') }}"
               class="sidebar-link-secondary {{ $isActive('admin.modules') ? 'active' : '' }}">
                <span class="text-base">🧩</span>
                <span class="text-sm">{{ __('Modules') }}</span>
            </a>
            @endif
            
            @if($canAccess('roles.manage'))
            <a href="{{ route('admin.roles.index') }}"
               class="sidebar-link-secondary {{ $isActive('admin.roles') ? 'active' : '' }}">
                <span class="text-base">🔐</span>
                <span class="text-sm">{{ __('Roles & Permissions') }}</span>
            </a>
            @endif
            
            @if($canAccess('users.manage'))
            <a href="{{ route('admin.users.index') }}"
               class="sidebar-link-secondary {{ $isActive('admin.users') ? 'active' : '' }}">
                <span class="text-base">👥</span>
                <span class="text-sm">{{ __('Users') }}</span>
            </a>
            @endif
            
            @if($canAccess('store.manage'))
            <a href="{{ route('admin.stores.index') }}"
               class="sidebar-link-secondary {{ $isActive('admin.stores') ? 'active' : '' }}">
                <span class="text-base">🔗</span>
                <span class="text-sm">{{ __('Integrations') }}</span>
            </a>
            @endif
        </div>
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
            <span class="text-sm font-medium">{{ __('Preferences') }}</span>
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
