{{-- resources/views/layouts/sidebar-new.blade.php --}}
@php
    $dir = app()->getLocale() === 'ar' ? 'rtl' : 'ltr';
    $user = auth()->user();
@endphp
<aside
    class="hidden md:flex md:flex-col md:w-64 lg:w-72 bg-gradient-to-b from-slate-800 via-slate-900 to-slate-950 text-slate-100 shadow-xl z-20 fixed top-0 bottom-0 {{ $dir === 'rtl' ? 'right-0' : 'left-0' }}"
    :class="sidebarOpen ? 'block' : ''"
>
    {{-- Logo & User --}}
    <div class="flex items-center justify-between px-4 py-4 border-b border-slate-700 flex-shrink-0">
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

    {{-- Navigation - Scrollable Area --}}
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-1.5 scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-slate-900">
        
        {{-- Dashboard --}}
        @can('dashboard.view')
        <a href="{{ route('dashboard') }}"
           class="sidebar-link bg-gradient-to-r from-red-500 to-red-600 {{ request()->routeIs('dashboard') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">📊</span>
            <span class="text-sm font-medium">{{ __('ERP Dashboard') }}</span>
            @if(request()->routeIs('dashboard'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        {{-- POS Section --}}
        @can('pos.use')
        <x-sidebar.section 
            title="Point of Sale" 
            title-ar="نقطة البيع"
            icon="🧾" 
            :routes="['pos.terminal', 'pos.daily', 'pos.offline']"
            permission="pos.use"
            gradient="from-amber-500 to-amber-600"
            section-key="pos_section"
        >
            <x-sidebar.link route="pos.terminal" label="POS Terminal" label-ar="شاشة البيع" icon="🏪" permission="pos.use" />
            <x-sidebar.link route="pos.daily.report" label="Daily Report" label-ar="تقرير يومي" icon="📑" permission="pos.daily-report.view" />
            <x-sidebar.link route="pos.offline.report" label="Offline Sales" label-ar="المبيعات الغير متصلة" icon="📴" permission="pos.offline.report.view" />
        </x-sidebar.section>
        @endcan

        {{-- Sales Management --}}
        @can('sales.view')
        <x-sidebar.section 
            title="Sales Management" 
            title-ar="إدارة المبيعات"
            icon="💰" 
            :routes="['app.sales']"
            permission="sales.view"
            gradient="from-green-500 to-green-600"
            section-key="sales_section"
        >
            <x-sidebar.link route="app.sales.index" label="All Sales" label-ar="كل المبيعات" icon="📋" permission="sales.view" />
            <x-sidebar.link route="app.sales.create" label="Create Sale" label-ar="بيع جديد" icon="➕" permission="sales.manage" />
            <x-sidebar.link route="app.sales.returns.index" label="Sales Returns" label-ar="مرتجعات المبيعات" icon="↩️" permission="sales.return" />
            <x-sidebar.link route="app.sales.analytics" label="Sales Analytics" label-ar="تحليلات المبيعات" icon="📊" permission="sales.view-reports" />
        </x-sidebar.section>
        @endcan

        {{-- Purchases Management --}}
        @can('purchases.view')
        <x-sidebar.section 
            title="Purchases" 
            title-ar="المشتريات"
            icon="🛒" 
            :routes="['app.purchases']"
            permission="purchases.view"
            gradient="from-purple-500 to-purple-600"
            section-key="purchases_section"
        >
            <x-sidebar.link route="app.purchases.index" label="All Purchases" label-ar="كل المشتريات" icon="📋" permission="purchases.view" />
            <x-sidebar.link route="app.purchases.create" label="Create Purchase" label-ar="شراء جديد" icon="➕" permission="purchases.manage" />
            <x-sidebar.link route="app.purchases.returns.index" label="Purchase Returns" label-ar="مرتجعات المشتريات" icon="↩️" permission="purchases.return" />
            <x-sidebar.link route="app.purchases.requisitions.index" label="Requisitions" label-ar="طلبات الشراء" icon="📝" permission="purchases.requisitions.view" />
            <x-sidebar.link route="app.purchases.quotations.index" label="Quotations" label-ar="عروض الأسعار" icon="💼" permission="purchases.view" />
            <x-sidebar.link route="app.purchases.grn.index" label="Goods Received" label-ar="استلام البضائع" icon="📦" permission="purchases.view" />
        </x-sidebar.section>
        @endcan

        {{-- Customers & Suppliers --}}
        @can('customers.view')
        <a href="{{ route('customers.index') }}"
           class="sidebar-link bg-gradient-to-r from-cyan-500 to-cyan-600 {{ request()->routeIs('customers*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">👤</span>
            <span class="text-sm font-medium">{{ __('Customer Info') }}</span>
            @if(request()->routeIs('customers*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('suppliers.view')
        <a href="{{ route('suppliers.index') }}"
           class="sidebar-link bg-gradient-to-r from-violet-500 to-violet-600 {{ request()->routeIs('suppliers*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏭</span>
            <span class="text-sm font-medium">{{ __('Suppliers') }}</span>
            @if(request()->routeIs('suppliers*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        {{-- Inventory Management --}}
        @can('inventory.products.view')
        <x-sidebar.section 
            title="Inventory Management" 
            title-ar="إدارة المخزون"
            icon="📦" 
            :routes="['app.inventory']"
            permission="inventory.products.view"
            gradient="from-teal-500 to-teal-600"
            section-key="inventory_section"
        >
            <x-sidebar.link route="app.inventory.products.index" label="Products" label-ar="المنتجات" icon="📦" permission="inventory.products.view" />
            <x-sidebar.link route="app.inventory.categories.index" label="Categories" label-ar="التصنيفات" icon="📂" />
            <x-sidebar.link route="app.inventory.units.index" label="Units of Measure" label-ar="وحدات القياس" icon="📏" />
            <x-sidebar.link route="app.inventory.stock-alerts" label="Low Stock Alerts" label-ar="تنبيهات المخزون" icon="⚠️" permission="inventory.stock.alerts.view" />
            <x-sidebar.link route="app.inventory.batches.index" label="Batch Tracking" label-ar="تتبع الدفعات" icon="📦" />
            <x-sidebar.link route="app.inventory.serials.index" label="Serial Tracking" label-ar="تتبع الأرقام التسلسلية" icon="🔢" />
            <x-sidebar.link route="app.inventory.barcodes" label="Print Barcodes" label-ar="طباعة الباركود" icon="🏷️" />
            <x-sidebar.link route="app.inventory.vehicle-models" label="Vehicle Models" label-ar="موديلات المركبات" icon="🚗" permission="spares.compatibility.manage" />
        </x-sidebar.section>
        @endcan

        {{-- Warehouse Management --}}
        @can('warehouse.view')
        <x-sidebar.section 
            title="Warehouse" 
            title-ar="المستودع"
            icon="🏭" 
            :routes="['app.warehouse']"
            permission="warehouse.view"
            gradient="from-orange-500 to-orange-600"
            section-key="warehouse_section"
        >
            <x-sidebar.link route="app.warehouse.index" label="Overview" label-ar="نظرة عامة" icon="📊" permission="warehouse.view" />
            <x-sidebar.link route="app.warehouse.locations.index" label="Locations" label-ar="المواقع" icon="📍" permission="warehouse.view" />
            <x-sidebar.link route="app.warehouse.movements.index" label="Movements" label-ar="الحركات" icon="🔄" permission="warehouse.view" />
            <x-sidebar.link route="app.warehouse.transfers.index" label="Transfers" label-ar="التحويلات" icon="🚚" permission="warehouse.view" />
            <x-sidebar.link route="app.warehouse.adjustments.index" label="Adjustments" label-ar="التعديلات" icon="⚖️" permission="warehouse.view" />
        </x-sidebar.section>
        @endcan

        {{-- Manufacturing --}}
        @can('manufacturing.view')
        <x-sidebar.section 
            title="Manufacturing" 
            title-ar="التصنيع"
            icon="🏭" 
            :routes="['app.manufacturing']"
            permission="manufacturing.view"
            gradient="from-gray-500 to-gray-600"
            section-key="manufacturing_section"
        >
            <x-sidebar.link route="app.manufacturing.boms.index" label="Bills of Materials" label-ar="قوائم المواد" icon="📋" permission="manufacturing.view" />
            <x-sidebar.link route="app.manufacturing.orders.index" label="Production Orders" label-ar="أوامر الإنتاج" icon="⚙️" permission="manufacturing.view" />
            <x-sidebar.link route="app.manufacturing.work-centers.index" label="Work Centers" label-ar="مراكز العمل" icon="🔧" permission="manufacturing.view" />
        </x-sidebar.section>
        @endcan

        {{-- Finance Section --}}
        <div class="my-3 border-t border-slate-700"></div>
        <p class="px-3 text-xs uppercase tracking-wide text-slate-500 mb-2">{{ __('Finance') }}</p>

        @can('expenses.view')
        <a href="{{ route('app.expenses.index') }}"
           class="sidebar-link bg-gradient-to-r from-slate-500 to-slate-600 {{ request()->routeIs('app.expenses*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">📋</span>
            <span class="text-sm font-medium">{{ __('Expenses') }}</span>
            @if(request()->routeIs('app.expenses*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('income.view')
        <a href="{{ route('app.income.index') }}"
           class="sidebar-link bg-gradient-to-r from-emerald-500 to-emerald-600 {{ request()->routeIs('app.income*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">💵</span>
            <span class="text-sm font-medium">{{ __('Income') }}</span>
            @if(request()->routeIs('app.income*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('accounting.view')
        <a href="{{ route('app.accounting.index') }}"
           class="sidebar-link bg-gradient-to-r from-indigo-500 to-indigo-600 {{ request()->routeIs('app.accounting*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🧮</span>
            <span class="text-sm font-medium">{{ __('Accounting') }}</span>
            @if(request()->routeIs('app.accounting*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('banking.view')
        <a href="{{ route('app.banking.accounts.index') }}"
           class="sidebar-link bg-gradient-to-r from-sky-500 to-sky-600 {{ request()->routeIs('app.banking*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏦</span>
            <span class="text-sm font-medium">{{ __('Banking') }}</span>
            @if(request()->routeIs('app.banking*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('fixed-assets.view')
        <a href="{{ route('app.fixed-assets.index') }}"
           class="sidebar-link bg-gradient-to-r from-stone-500 to-stone-600 {{ request()->routeIs('app.fixed-assets*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏢</span>
            <span class="text-sm font-medium">{{ __('Fixed Assets') }}</span>
            @if(request()->routeIs('app.fixed-assets*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        {{-- HR & Rental --}}
        @can('hrm.employees.view')
        <x-sidebar.section 
            title="Human Resources" 
            title-ar="الموارد البشرية"
            icon="👔" 
            :routes="['app.hrm']"
            permission="hrm.employees.view"
            gradient="from-rose-500 to-rose-600"
            section-key="hrm_section"
        >
            <x-sidebar.link route="app.hrm.employees.index" label="Employees" label-ar="الموظفون" icon="👥" permission="hrm.employees.view" />
            <x-sidebar.link route="app.hrm.attendance.index" label="Attendance" label-ar="الحضور" icon="📅" permission="hrm.attendance.view" />
            <x-sidebar.link route="app.hrm.payroll.index" label="Payroll" label-ar="الرواتب" icon="💰" permission="hrm.payroll.view" />
            <x-sidebar.link route="app.hrm.shifts.index" label="Shifts" label-ar="الورديات" icon="🕐" permission="hrm.view" />
            <x-sidebar.link route="app.hrm.reports" label="Reports" label-ar="التقارير" icon="📊" permission="hrm.view-reports" />
        </x-sidebar.section>
        @endcan

        @if(auth()->user()?->can('rental.units.view') || auth()->user()?->can('rentals.view'))
        <x-sidebar.section 
            title="Rental Management" 
            title-ar="إدارة التأجير"
            icon="🏠" 
            :routes="['app.rental']"
            gradient="from-lime-500 to-lime-600"
            section-key="rental_section"
        >
            <x-sidebar.link route="app.rental.units.index" label="Rental Units" label-ar="وحدات التأجير" icon="🏠" permission="rental.units.view" />
            <x-sidebar.link route="app.rental.properties.index" label="Properties" label-ar="العقارات" icon="🏢" permission="rentals.view" />
            <x-sidebar.link route="app.rental.tenants.index" label="Tenants" label-ar="المستأجرين" icon="👥" permission="rentals.view" />
            <x-sidebar.link route="app.rental.contracts.index" label="Contracts" label-ar="العقود" icon="📄" permission="rental.contracts.view" />
            <x-sidebar.link route="app.rental.reports" label="Reports" label-ar="التقارير" icon="📊" permission="rental.view-reports" />
        </x-sidebar.section>
        @endif

        {{-- Administration Section --}}
        @if(auth()->user()?->can('settings.view') || auth()->user()?->can('users.manage') || auth()->user()?->can('roles.manage') || auth()->user()?->can('modules.manage'))
        <div class="my-3 border-t border-slate-700"></div>
        <p class="px-3 text-xs uppercase tracking-wide text-slate-500 mb-2">{{ __('Administration') }}</p>
        
        @can('branches.view')
        <a href="{{ route('admin.branches.index') }}"
           class="sidebar-link bg-gradient-to-r from-blue-500 to-blue-600 {{ request()->routeIs('admin.branches*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🏢</span>
            <span class="text-sm font-medium">{{ __('Branch Management') }}</span>
            @if(request()->routeIs('admin.branches*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('users.manage')
        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link bg-gradient-to-r from-pink-500 to-pink-600 {{ request()->routeIs('admin.users*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">👥</span>
            <span class="text-sm font-medium">{{ __('User Management') }}</span>
            @if(request()->routeIs('admin.users*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('roles.manage')
        <a href="{{ route('admin.roles.index') }}"
           class="sidebar-link bg-gradient-to-r from-violet-500 to-violet-600 {{ request()->routeIs('admin.roles*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🔐</span>
            <span class="text-sm font-medium">{{ __('Role Management') }}</span>
            @if(request()->routeIs('admin.roles*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('modules.manage')
        <a href="{{ route('admin.modules.index') }}"
           class="sidebar-link bg-gradient-to-r from-fuchsia-500 to-fuchsia-600 {{ request()->routeIs('admin.modules*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🧩</span>
            <span class="text-sm font-medium">{{ __('Module Management') }}</span>
            @if(request()->routeIs('admin.modules*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('stores.view')
        <a href="{{ route('admin.stores.index') }}"
           class="sidebar-link bg-gradient-to-r from-amber-500 to-amber-600 {{ request()->routeIs('admin.stores*') ? 'active ring-2 ring-white/30' : '' }}">
            <span class="text-lg">🔗</span>
            <span class="text-sm font-medium">{{ __('Store Integrations') }}</span>
            @if(request()->routeIs('admin.stores*'))
                <span class="ms-auto w-2 h-2 rounded-full bg-white animate-pulse"></span>
            @endif
        </a>
        @endcan

        @can('settings.view')
        <x-sidebar.section 
            title="System Settings" 
            title-ar="إعدادات النظام"
            icon="⚙️" 
            :routes="['admin.settings', 'admin.currencies', 'admin.currency-rates']"
            permission="settings.view"
            gradient="from-sky-500 to-sky-600"
            section-key="settings_section"
        >
            <x-sidebar.link route="admin.settings" label="General Settings" label-ar="الإعدادات العامة" icon="⚙️" permission="settings.view" />
            <x-sidebar.link route="admin.currencies.index" label="Currency Management" label-ar="إدارة العملات" icon="💰" permission="settings.view" />
            <x-sidebar.link route="admin.currency-rates.index" label="Exchange Rates" label-ar="أسعار الصرف" icon="💱" permission="settings.view" />
        </x-sidebar.section>
        @endcan
        @endif

        {{-- Reports Section --}}
        @can('reports.view')
        <div class="my-3 border-t border-slate-700"></div>
        <p class="px-3 text-xs uppercase tracking-wide text-slate-500 mb-2">{{ __('Reports & Analytics') }}</p>

        <x-sidebar.section 
            title="Reports Hub" 
            title-ar="مركز التقارير"
            icon="📊" 
            :routes="['admin.reports']"
            permission="reports.view"
            gradient="from-emerald-500 to-emerald-600"
            section-key="reports_section"
        >
            <x-sidebar.link route="admin.reports.index" label="Reports Hub" label-ar="مركز التقارير" icon="📊" permission="reports.view" />
            <x-sidebar.link route="admin.reports.pos" label="Sales Report" label-ar="تقرير المبيعات" icon="📈" permission="pos.view-reports" />
            <x-sidebar.link route="admin.reports.inventory" label="Inventory Report" label-ar="تقرير المخزون" icon="📦" permission="inventory.view-reports" />
            <x-sidebar.link route="admin.stores.orders" label="Store Dashboard" label-ar="لوحة المتجر" icon="🏪" permission="stores.view" />
            <x-sidebar.link route="admin.logs.audit" label="Audit Logs" label-ar="سجلات المراجعة" icon="📋" permission="logs.audit.view" />
            <x-sidebar.link route="admin.reports.scheduled" label="Scheduled Reports" label-ar="التقارير المجدولة" icon="📅" permission="reports.schedule" />
        </x-sidebar.section>
        @endcan
        
        {{-- Spacing at bottom for better scroll --}}
        <div class="h-4"></div>
    </nav>
</aside>

<style>
.sidebar-link {
    @apply flex items-center gap-3 px-3 py-2.5 rounded-lg text-white transition-all duration-200 hover:bg-white/10 hover:shadow-lg;
}

.sidebar-link.active {
    @apply shadow-lg;
}

.sidebar-link-secondary {
    @apply flex items-center gap-2 px-3 py-2 rounded-lg text-slate-300 transition-all duration-200 hover:bg-white/5 hover:text-white;
}

.sidebar-link-secondary.active {
    @apply bg-white/10 text-white border-l-2 border-emerald-400;
}

/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.5);
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(71, 85, 105, 0.8);
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(100, 116, 139, 1);
}
</style>
