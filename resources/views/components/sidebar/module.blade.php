@props(['module' => null])

@php
// Determine module from current route if not provided
if (!$module) {
    $routeName = request()->route()->getName();
    if (str_starts_with($routeName, 'app.')) {
        $parts = explode('.', $routeName);
        $module = $parts[1] ?? null;
    }
}
@endphp

@if($module)
<nav class="bg-gray-50 dark:bg-gray-900 border-r border-gray-200 dark:border-gray-700 w-64 h-full overflow-y-auto">
    <div class="px-3 py-4">
        <h3 class="mb-4 px-4 text-sm font-semibold text-gray-500 uppercase tracking-wider">
            {{ __(ucfirst($module)) }}
        </h3>

        <ul class="space-y-1">
            @if($module === 'sales')
                <x-sidebar.item route="app.sales.index" label="All Sales" />
                @can('sales.manage')
                    <x-sidebar.item route="app.sales.create" label="New Sale" />
                @endcan
                @can('sales.return')
                    <x-sidebar.item route="app.sales.returns.index" label="Returns" />
                @endcan
                @can('sales.view-reports')
                    <x-sidebar.item route="app.sales.analytics" label="Analytics" />
                @endcan

            @elseif($module === 'purchases')
                <x-sidebar.item route="app.purchases.index" label="All Purchases" />
                @can('purchases.manage')
                    <x-sidebar.item route="app.purchases.create" label="New Purchase" />
                @endcan
                @can('purchases.return')
                    <x-sidebar.item route="app.purchases.returns.index" label="Returns" />
                @endcan
                @can('purchases.requisitions.view')
                    <x-sidebar.item route="app.purchases.requisitions.index" label="Requisitions" />
                @endcan
                <x-sidebar.item route="app.purchases.quotations.index" label="Quotations" />
                <x-sidebar.item route="app.purchases.grn.index" label="Goods Received" />

            @elseif($module === 'inventory')
                <x-sidebar.item route="app.inventory.products.index" label="Products" />
                @can('inventory.categories.view')
                    <x-sidebar.item route="app.inventory.categories.index" label="Categories" />
                @endcan
                @can('inventory.units.view')
                    <x-sidebar.item route="app.inventory.units.index" label="Units" />
                @endcan
                <x-sidebar.item route="app.inventory.stock-alerts" label="Stock Alerts" />
                <x-sidebar.item route="app.inventory.batches.index" label="Batches" />
                <x-sidebar.item route="app.inventory.serials.index" label="Serial Numbers" />
                <x-sidebar.item route="app.inventory.barcodes" label="Barcodes" />

            @elseif($module === 'warehouse')
                <x-sidebar.item route="app.warehouse.index" label="Dashboard" />
                <x-sidebar.item route="app.warehouse.locations.index" label="Locations" />
                <x-sidebar.item route="app.warehouse.movements.index" label="Movements" />
                <x-sidebar.item route="app.warehouse.transfers.index" label="Transfers" />
                <x-sidebar.item route="app.warehouse.adjustments.index" label="Adjustments" />

            @elseif($module === 'rental')
                <x-sidebar.item route="app.rental.units.index" label="Units" />
                <x-sidebar.item route="app.rental.properties.index" label="Properties" />
                <x-sidebar.item route="app.rental.tenants.index" label="Tenants" />
                <x-sidebar.item route="app.rental.contracts.index" label="Contracts" />
                <x-sidebar.item route="app.rental.reports" label="Reports" />

            @elseif($module === 'manufacturing')
                <x-sidebar.item route="app.manufacturing.boms.index" label="Bills of Materials" />
                <x-sidebar.item route="app.manufacturing.orders.index" label="Production Orders" />
                <x-sidebar.item route="app.manufacturing.work-centers.index" label="Work Centers" />

            @elseif($module === 'hrm')
                <x-sidebar.item route="app.hrm.employees.index" label="Employees" />
                <x-sidebar.item route="app.hrm.attendance.index" label="Attendance" />
                <x-sidebar.item route="app.hrm.payroll.index" label="Payroll" />
                <x-sidebar.item route="app.hrm.shifts.index" label="Shifts" />
                <x-sidebar.item route="app.hrm.reports" label="Reports" />

            @elseif($module === 'banking')
                <x-sidebar.item route="app.banking.accounts.index" label="Accounts" />
                <x-sidebar.item route="app.banking.transactions.index" label="Transactions" />
                <x-sidebar.item route="app.banking.reconciliation" label="Reconciliation" />

            @elseif($module === 'fixed-assets')
                <x-sidebar.item route="app.fixed-assets.index" label="All Assets" />
                @can('fixed-assets.manage')
                    <x-sidebar.item route="app.fixed-assets.create" label="Add Asset" />
                @endcan
                <x-sidebar.item route="app.fixed-assets.depreciation" label="Depreciation" />

            @elseif($module === 'projects')
                <x-sidebar.item route="app.projects.index" label="All Projects" />
                @can('projects.manage')
                    <x-sidebar.item route="app.projects.create" label="New Project" />
                @endcan

            @elseif($module === 'documents')
                <x-sidebar.item route="app.documents.index" label="All Documents" />
                @can('documents.manage')
                    <x-sidebar.item route="app.documents.create" label="Upload Document" />
                @endcan

            @elseif($module === 'helpdesk')
                <x-sidebar.item route="app.helpdesk.tickets.index" label="Tickets" />
                @can('helpdesk.manage')
                    <x-sidebar.item route="app.helpdesk.tickets.create" label="New Ticket" />
                    <x-sidebar.item route="app.helpdesk.categories.index" label="Categories" />
                @endcan

            @elseif($module === 'expenses')
                <x-sidebar.item route="app.expenses.index" label="All Expenses" />
                @can('expenses.manage')
                    <x-sidebar.item route="app.expenses.create" label="New Expense" />
                    <x-sidebar.item route="app.expenses.categories.index" label="Categories" />
                @endcan

            @elseif($module === 'income')
                <x-sidebar.item route="app.income.index" label="All Income" />
                @can('income.manage')
                    <x-sidebar.item route="app.income.create" label="New Income" />
                    <x-sidebar.item route="app.income.categories.index" label="Categories" />
                @endcan
            @endif
        </ul>
    </div>
</nav>
@endif
