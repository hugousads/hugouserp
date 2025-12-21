<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EnhancedPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Dashboard
            ['name' => 'dashboard.view', 'group' => 'Dashboard', 'description' => 'View dashboard'],
            ['name' => 'dashboard.widgets.manage', 'group' => 'Dashboard', 'description' => 'Manage dashboard widgets'],
            
            // Sales - Enhanced
            ['name' => 'sales.view', 'group' => 'Sales', 'description' => 'View sales'],
            ['name' => 'sales.create', 'group' => 'Sales', 'description' => 'Create sales'],
            ['name' => 'sales.edit', 'group' => 'Sales', 'description' => 'Edit sales'],
            ['name' => 'sales.delete', 'group' => 'Sales', 'description' => 'Delete sales'],
            ['name' => 'sales.approve', 'group' => 'Sales', 'description' => 'Approve sales'],
            ['name' => 'sales.export', 'group' => 'Sales', 'description' => 'Export sales'],
            ['name' => 'sales.import', 'group' => 'Sales', 'description' => 'Import sales'],
            ['name' => 'sales.return', 'group' => 'Sales', 'description' => 'Process sales returns'],
            ['name' => 'sales.discount', 'group' => 'Sales', 'description' => 'Apply discounts'],
            
            // Purchases - Enhanced
            ['name' => 'purchases.view', 'group' => 'Purchases', 'description' => 'View purchases'],
            ['name' => 'purchases.create', 'group' => 'Purchases', 'description' => 'Create purchases'],
            ['name' => 'purchases.edit', 'group' => 'Purchases', 'description' => 'Edit purchases'],
            ['name' => 'purchases.delete', 'group' => 'Purchases', 'description' => 'Delete purchases'],
            ['name' => 'purchases.approve', 'group' => 'Purchases', 'description' => 'Approve purchases'],
            ['name' => 'purchases.export', 'group' => 'Purchases', 'description' => 'Export purchases'],
            ['name' => 'purchases.import', 'group' => 'Purchases', 'description' => 'Import purchases'],
            ['name' => 'purchases.return', 'group' => 'Purchases', 'description' => 'Process purchase returns'],
            ['name' => 'purchases.grn', 'group' => 'Purchases', 'description' => 'Manage GRN'],
            
            // Inventory - Enhanced
            ['name' => 'inventory.products.view', 'group' => 'Inventory', 'description' => 'View products'],
            ['name' => 'inventory.products.create', 'group' => 'Inventory', 'description' => 'Create products'],
            ['name' => 'inventory.products.edit', 'group' => 'Inventory', 'description' => 'Edit products'],
            ['name' => 'inventory.products.delete', 'group' => 'Inventory', 'description' => 'Delete products'],
            ['name' => 'inventory.products.export', 'group' => 'Inventory', 'description' => 'Export products'],
            ['name' => 'inventory.products.import', 'group' => 'Inventory', 'description' => 'Import products'],
            ['name' => 'inventory.stock.adjust', 'group' => 'Inventory', 'description' => 'Adjust stock'],
            ['name' => 'inventory.stock.transfer', 'group' => 'Inventory', 'description' => 'Transfer stock'],
            ['name' => 'inventory.stock.count', 'group' => 'Inventory', 'description' => 'Stock count'],
            ['name' => 'inventory.stock.alerts.view', 'group' => 'Inventory', 'description' => 'View stock alerts'],
            
            // Warehouse - New
            ['name' => 'warehouse.view', 'group' => 'Warehouse', 'description' => 'View warehouse'],
            ['name' => 'warehouse.manage', 'group' => 'Warehouse', 'description' => 'Manage warehouse'],
            ['name' => 'warehouse.locations.manage', 'group' => 'Warehouse', 'description' => 'Manage locations'],
            ['name' => 'warehouse.transfers.create', 'group' => 'Warehouse', 'description' => 'Create transfers'],
            ['name' => 'warehouse.transfers.approve', 'group' => 'Warehouse', 'description' => 'Approve transfers'],
            
            // Customers - Enhanced
            ['name' => 'customers.view', 'group' => 'Customers', 'description' => 'View customers'],
            ['name' => 'customers.create', 'group' => 'Customers', 'description' => 'Create customers'],
            ['name' => 'customers.edit', 'group' => 'Customers', 'description' => 'Edit customers'],
            ['name' => 'customers.delete', 'group' => 'Customers', 'description' => 'Delete customers'],
            ['name' => 'customers.export', 'group' => 'Customers', 'description' => 'Export customers'],
            ['name' => 'customers.import', 'group' => 'Customers', 'description' => 'Import customers'],
            ['name' => 'customers.manage.all', 'group' => 'Customers', 'description' => 'Manage customers across all branches'],
            
            // Suppliers - Enhanced
            ['name' => 'suppliers.view', 'group' => 'Suppliers', 'description' => 'View suppliers'],
            ['name' => 'suppliers.create', 'group' => 'Suppliers', 'description' => 'Create suppliers'],
            ['name' => 'suppliers.edit', 'group' => 'Suppliers', 'description' => 'Edit suppliers'],
            ['name' => 'suppliers.delete', 'group' => 'Suppliers', 'description' => 'Delete suppliers'],
            ['name' => 'suppliers.export', 'group' => 'Suppliers', 'description' => 'Export suppliers'],
            ['name' => 'suppliers.import', 'group' => 'Suppliers', 'description' => 'Import suppliers'],
            
            // Expenses - Enhanced
            ['name' => 'expenses.view', 'group' => 'Expenses', 'description' => 'View expenses'],
            ['name' => 'expenses.create', 'group' => 'Expenses', 'description' => 'Create expenses'],
            ['name' => 'expenses.edit', 'group' => 'Expenses', 'description' => 'Edit expenses'],
            ['name' => 'expenses.delete', 'group' => 'Expenses', 'description' => 'Delete expenses'],
            ['name' => 'expenses.approve', 'group' => 'Expenses', 'description' => 'Approve expenses'],
            ['name' => 'expenses.export', 'group' => 'Expenses', 'description' => 'Export expenses'],
            ['name' => 'expenses.manage', 'group' => 'Expenses', 'description' => 'Manage expense categories'],
            
            // Income - Enhanced
            ['name' => 'income.view', 'group' => 'Income', 'description' => 'View income'],
            ['name' => 'income.create', 'group' => 'Income', 'description' => 'Create income'],
            ['name' => 'income.edit', 'group' => 'Income', 'description' => 'Edit income'],
            ['name' => 'income.delete', 'group' => 'Income', 'description' => 'Delete income'],
            ['name' => 'income.export', 'group' => 'Income', 'description' => 'Export income'],
            ['name' => 'income.manage', 'group' => 'Income', 'description' => 'Manage income categories'],
            
            // Reports - Enhanced
            ['name' => 'reports.view', 'group' => 'Reports', 'description' => 'View reports'],
            ['name' => 'reports.export', 'group' => 'Reports', 'description' => 'Export reports'],
            ['name' => 'reports.schedule', 'group' => 'Reports', 'description' => 'Schedule reports'],
            ['name' => 'reports.templates', 'group' => 'Reports', 'description' => 'Manage report templates'],
            ['name' => 'reports.aggregate', 'group' => 'Reports', 'description' => 'View aggregate reports'],
            ['name' => 'reports.pos.view', 'group' => 'Reports', 'description' => 'View POS reports'],
            ['name' => 'reports.pos.export', 'group' => 'Reports', 'description' => 'Export POS reports'],
            ['name' => 'reports.inventory.view', 'group' => 'Reports', 'description' => 'View inventory reports'],
            ['name' => 'reports.inventory.export', 'group' => 'Reports', 'description' => 'Export inventory reports'],
            ['name' => 'sales.view-reports', 'group' => 'Reports', 'description' => 'View sales reports'],
            
            // HRM - Enhanced
            ['name' => 'hrm.employees.view', 'group' => 'HRM', 'description' => 'View employees'],
            ['name' => 'hrm.employees.create', 'group' => 'HRM', 'description' => 'Create employees'],
            ['name' => 'hrm.employees.edit', 'group' => 'HRM', 'description' => 'Edit employees'],
            ['name' => 'hrm.employees.delete', 'group' => 'HRM', 'description' => 'Delete employees'],
            ['name' => 'hrm.employees.export', 'group' => 'HRM', 'description' => 'Export employees'],
            ['name' => 'hrm.employees.import', 'group' => 'HRM', 'description' => 'Import employees'],
            ['name' => 'hrm.attendance.view', 'group' => 'HRM', 'description' => 'View attendance'],
            ['name' => 'hrm.attendance.manage', 'group' => 'HRM', 'description' => 'Manage attendance'],
            ['name' => 'hrm.payroll.view', 'group' => 'HRM', 'description' => 'View payroll'],
            ['name' => 'hrm.payroll.process', 'group' => 'HRM', 'description' => 'Process payroll'],
            ['name' => 'hrm.leaves.view', 'group' => 'HRM', 'description' => 'View leaves'],
            ['name' => 'hrm.leaves.approve', 'group' => 'HRM', 'description' => 'Approve leaves'],
            ['name' => 'hrm.view', 'group' => 'HRM', 'description' => 'View HRM module'],
            ['name' => 'hrm.view-reports', 'group' => 'HRM', 'description' => 'View HRM reports'],
            
            // Rental - Enhanced
            ['name' => 'rental.units.view', 'group' => 'Rental', 'description' => 'View rental units'],
            ['name' => 'rental.units.create', 'group' => 'Rental', 'description' => 'Create rental units'],
            ['name' => 'rental.units.edit', 'group' => 'Rental', 'description' => 'Edit rental units'],
            ['name' => 'rental.units.delete', 'group' => 'Rental', 'description' => 'Delete rental units'],
            ['name' => 'rental.contracts.view', 'group' => 'Rental', 'description' => 'View contracts'],
            ['name' => 'rental.contracts.create', 'group' => 'Rental', 'description' => 'Create contracts'],
            ['name' => 'rental.contracts.edit', 'group' => 'Rental', 'description' => 'Edit contracts'],
            ['name' => 'rental.contracts.delete', 'group' => 'Rental', 'description' => 'Delete contracts'],
            ['name' => 'rentals.view', 'group' => 'Rental', 'description' => 'View rentals'],
            ['name' => 'rental.view-reports', 'group' => 'Rental', 'description' => 'View rental reports'],
            
            // Manufacturing - Enhanced
            ['name' => 'manufacturing.view', 'group' => 'Manufacturing', 'description' => 'View manufacturing'],
            ['name' => 'manufacturing.bom.create', 'group' => 'Manufacturing', 'description' => 'Create BOM'],
            ['name' => 'manufacturing.bom.edit', 'group' => 'Manufacturing', 'description' => 'Edit BOM'],
            ['name' => 'manufacturing.bom.delete', 'group' => 'Manufacturing', 'description' => 'Delete BOM'],
            ['name' => 'manufacturing.orders.create', 'group' => 'Manufacturing', 'description' => 'Create production orders'],
            ['name' => 'manufacturing.orders.process', 'group' => 'Manufacturing', 'description' => 'Process production orders'],
            
            // Admin - Enhanced
            ['name' => 'users.manage', 'group' => 'Admin', 'description' => 'Manage users'],
            ['name' => 'roles.manage', 'group' => 'Admin', 'description' => 'Manage roles'],
            ['name' => 'branches.view', 'group' => 'Admin', 'description' => 'View branches'],
            ['name' => 'branches.manage', 'group' => 'Admin', 'description' => 'Manage branches'],
            ['name' => 'modules.manage', 'group' => 'Admin', 'description' => 'Manage modules'],
            ['name' => 'settings.view', 'group' => 'Admin', 'description' => 'View settings'],
            ['name' => 'settings.edit', 'group' => 'Admin', 'description' => 'Edit settings'],
            ['name' => 'settings.branch', 'group' => 'Admin', 'description' => 'Manage branch settings'],
            ['name' => 'logs.audit.view', 'group' => 'Admin', 'description' => 'View audit logs'],
            
            // POS - Enhanced
            ['name' => 'pos.use', 'group' => 'POS', 'description' => 'Use POS terminal'],
            ['name' => 'pos.daily-report.view', 'group' => 'POS', 'description' => 'View daily POS report'],
            ['name' => 'pos.sessions.manage', 'group' => 'POS', 'description' => 'Manage POS sessions'],
            ['name' => 'pos.view-reports', 'group' => 'POS', 'description' => 'View POS reports'],
            
            // Additional modules
            ['name' => 'accounting.view', 'group' => 'Accounting', 'description' => 'View accounting'],
            ['name' => 'banking.view', 'group' => 'Banking', 'description' => 'View banking'],
            ['name' => 'documents.view', 'group' => 'Documents', 'description' => 'View documents'],
            ['name' => 'documents.manage', 'group' => 'Documents', 'description' => 'Manage documents'],
            ['name' => 'projects.view', 'group' => 'Projects', 'description' => 'View projects'],
            ['name' => 'projects.manage', 'group' => 'Projects', 'description' => 'Manage projects'],
            ['name' => 'helpdesk.view', 'group' => 'Helpdesk', 'description' => 'View helpdesk'],
            ['name' => 'helpdesk.manage', 'group' => 'Helpdesk', 'description' => 'Manage helpdesk tickets'],
            ['name' => 'fixed-assets.view', 'group' => 'Fixed Assets', 'description' => 'View fixed assets'],
            ['name' => 'fixed-assets.manage', 'group' => 'Fixed Assets', 'description' => 'Manage fixed assets'],
            ['name' => 'media.view', 'group' => 'Media', 'description' => 'View media library'],
            ['name' => 'stores.view', 'group' => 'Stores', 'description' => 'View store integrations'],
            ['name' => 'spares.compatibility.manage', 'group' => 'Spares', 'description' => 'Manage spare parts compatibility'],
            ['name' => 'system.view-notifications', 'group' => 'System', 'description' => 'View notifications'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                [
                    'group' => $permission['group'],
                    'description' => $permission['description'],
                ]
            );
        }

        // Assign all permissions to Super Admin role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        $this->command->info('Enhanced permissions seeded successfully!');
    }
}
