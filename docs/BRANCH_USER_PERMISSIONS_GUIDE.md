# Branch, User & Permissions Guide / دليل الفروع والمستخدمين والصلاحيات

**Version:** 1.0  
**Last Updated:** 2026-01-04

---

## Overview / نظرة عامة

HugouERP implements a comprehensive multi-level permissions system that controls:
- **System-level access** (Super Admins)
- **Branch-level access** (Branch Admins and Users)
- **Module-level access** (per branch)
- **Feature-level access** (granular permissions)

---

## User Hierarchy / هرم المستخدمين

```
┌───────────────────────────────────────┐
│           SUPER ADMIN                  │
│  • Full system access                  │
│  • Can manage all branches             │
│  • Can configure system settings       │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│         BRANCH ADMIN                   │
│  • Full access within assigned branch  │
│  • Can manage branch users             │
│  • Can configure branch settings       │
└───────────────┬───────────────────────┘
                │
                ▼
┌───────────────────────────────────────┐
│         BRANCH USER                    │
│  • Access based on assigned roles      │
│  • Limited to current branch data      │
│  • Cannot manage other users           │
└───────────────────────────────────────┘
```

---

## Branch System / نظام الفروع

### Branch Structure

```php
Branch
├── name            // Display name
├── code            // Unique identifier
├── is_active       // Enable/disable branch
├── is_main         // Is this the main branch?
├── parent_id       // For branch hierarchy
├── settings        // JSON configuration
│
├── users()         // Users assigned to this branch
├── admins()        // Branch administrators
├── modules()       // Enabled modules
└── products()      // Products in this branch
```

### Branch-Module Relationship

Each branch can enable/disable modules independently:

```php
// Check if branch has a module enabled
$branch->hasModule('motorcycle');  // true/false

// Get enabled modules for branch
$branch->enabledModules();

// Get module settings for branch
$branch->getModuleSetting($moduleId, 'setting_key');
```

### Branch Configuration (branch_modules)

| Field | Type | Description |
|-------|------|-------------|
| `branch_id` | int | FK to branches |
| `module_id` | int | FK to modules |
| `module_key` | string | Module identifier |
| `enabled` | bool | Is module active? |
| `settings` | json | Module-specific settings |
| `permission_overrides` | json | Custom permission rules |
| `activation_constraints` | json | Conditions for activation |
| `inherit_settings` | bool | Inherit from global? |

---

## Branch Admins / مديرو الفروع

Branch admins have elevated permissions within their assigned branches:

```php
BranchAdmin
├── user_id           // The admin user
├── branch_id         // Assigned branch
├── is_primary        // Main admin for branch?
├── is_active         // Account active?
│
├── can_manage_users     // Add/edit users
├── can_manage_roles     // Assign roles
├── can_view_reports     // Access reports
├── can_export_data      // Export capabilities
└── can_manage_settings  // Configure branch
```

### Branch Admin Permissions

| Permission | Description |
|------------|-------------|
| `can_manage_users` | Add, edit, deactivate users in branch |
| `can_manage_roles` | Assign/remove roles from branch users |
| `can_view_reports` | Access all branch reports |
| `can_export_data` | Export branch data (PDF, Excel, CSV) |
| `can_manage_settings` | Configure branch and module settings |

### Checking Admin Status

```php
// Check if user is admin for current branch
$branch->isAdminUser($user);

// Get primary admin for branch
$branch->getPrimaryAdmin();

// Check specific admin permissions
$branchAdmin->canManageUsersInBranch();
$branchAdmin->canViewReportsInBranch();
```

---

## Permission System / نظام الصلاحيات

### Permission Pattern

Permissions follow the pattern: `{module}.{resource}.{action}`

```
inventory.products.view
inventory.products.create
inventory.products.edit
inventory.products.delete
sales.view
sales.create
manufacturing.boms.create
hrm.employees.view
```

### Permission Categories

| Module | Permissions |
|--------|-------------|
| **Dashboard** | `dashboard.view` |
| **Inventory** | `inventory.products.*`, `inventory.categories.*` |
| **Sales** | `sales.view`, `sales.create`, `sales.edit`, `sales.delete` |
| **Purchases** | `purchases.view`, `purchases.create`, `purchases.approve` |
| **POS** | `pos.access`, `pos.refund`, `pos.discount` |
| **Manufacturing** | `manufacturing.view`, `manufacturing.boms.*`, `manufacturing.orders.*` |
| **HRM** | `hrm.employees.*`, `hrm.attendance.*`, `hrm.payroll.*` |
| **Banking** | `banking.view`, `banking.reconcile` |
| **Accounting** | `accounting.view`, `accounting.journal-entries.*` |
| **Reports** | `reports.view`, `reports.export` |

### Special Permissions

| Permission | Description |
|------------|-------------|
| `*` | Super admin - all permissions |
| `branch.switch` | Can switch between branches |
| `admin.access` | Access admin panel |
| `settings.manage` | Manage system settings |

---

## Role System / نظام الأدوار

### Default Roles

| Role | Description | Key Permissions |
|------|-------------|-----------------|
| **Super Admin** | Full system access | All (`*`) |
| **Branch Manager** | Full branch access | All branch operations |
| **Sales Manager** | Sales team lead | Sales, Customers, Reports |
| **Salesperson** | Front-line sales | Sales, POS, Customers |
| **Warehouse Staff** | Stock management | Inventory, Stock movements |
| **Accountant** | Financial operations | Accounting, Banking, Reports |
| **HR Manager** | Human resources | HRM module |
| **Viewer** | Read-only access | View permissions only |

### Creating Custom Roles

```php
// Via admin panel
Admin → Roles → Add Role

// Define permissions
$role->givePermissionTo([
    'inventory.products.view',
    'inventory.products.create',
    'sales.view',
]);
```

---

## User-Branch Assignment / تعيين المستخدمين للفروع

### Assigning Users to Branches

```php
// Assign user to branch
$branch->users()->attach($userId);

// Assign with specific settings
$branch->users()->attach($userId, [
    'is_default' => true,
]);

// Remove from branch
$branch->users()->detach($userId);
```

### User's Current Branch

```php
// Get user's current branch
$user->current_branch_id

// Switch branch (session-based)
session(['current_branch_id' => $branchId]);

// Get user's default branch
$user->branch_id
```

### Branch Scoping

All queries are automatically scoped to the current branch:

```php
// Only returns sales for current branch
Sale::all();

// Explicitly scope to branch
Sale::where('branch_id', current_branch_id())->get();

// Cross-branch query (requires permission)
Sale::withoutGlobalScope('branch')->get();
```

---

## Permission Checking / التحقق من الصلاحيات

### In Controllers/Components

```php
// Using authorize
$this->authorize('inventory.products.create');

// Using can
if ($user->can('sales.view')) {
    // Show sales data
}

// Using Gate
if (Gate::allows('sales.refund', $sale)) {
    // Process refund
}
```

### In Blade Templates

```blade
@can('inventory.products.create')
    <button>Add Product</button>
@endcan

@cannot('sales.delete')
    <span class="text-gray-400">Cannot delete</span>
@endcannot

@canany(['sales.view', 'purchases.view'])
    <a href="#">View Orders</a>
@endcanany
```

### In Routes

```php
Route::get('/products', ProductIndex::class)
    ->middleware('can:inventory.products.view');

Route::post('/sales', SaleCreate::class)
    ->middleware(['can:sales.create', 'branch.module:sales']);
```

---

## Module-Based Access Control / التحكم في الوصول حسب المديول

### Checking Module Access

```php
// Check if user's branch has module enabled
if ($branch->hasModule('manufacturing')) {
    // Show manufacturing menu
}

// Middleware check
Route::group(['middleware' => 'branch.module:manufacturing'], function () {
    // Manufacturing routes
});
```

### Permission Overrides per Branch

Branches can override default module permissions:

```php
// In BranchModule pivot
$branchModule->permission_overrides = [
    'manufacturing.boms.delete' => false,  // Disable BOM deletion
    'inventory.products.export' => true,   // Enable export
];
```

---

## Workflows / سير العمل

### Approval Workflows

Complex operations can require approvals:

```php
// Purchase order approval
Purchase::where('status', 'pending_approval')
    ->whereHas('approvals', function ($q) {
        $q->where('stage', 'manager')
          ->where('approved', true);
    });
```

### Workflow Permissions

| Permission | Description |
|------------|-------------|
| `purchases.approve` | Approve purchase orders |
| `sales.approve` | Approve sales (for high-value) |
| `hrm.leaves.approve` | Approve leave requests |

---

## Best Practices / أفضل الممارسات

### 1. Least Privilege Principle

Assign the minimum permissions needed:

```php
// Good: Specific permissions
$role->givePermissionTo('sales.view', 'sales.create');

// Bad: Overly broad
$role->givePermissionTo('sales.*');
```

### 2. Use Roles, Not Direct Permissions

```php
// Good: Assign role
$user->assignRole('salesperson');

// Avoid: Direct permissions (harder to manage)
$user->givePermissionTo('sales.view');
```

### 3. Regular Permission Audits

- Review user permissions quarterly
- Remove unused permissions
- Check for permission creep

### 4. Document Custom Permissions

Keep a record of custom permissions in `config/screen_permissions.php`:

```php
return [
    'custom_report_xyz' => [
        'permission' => 'reports.custom.xyz',
        'description' => 'Access XYZ custom report',
    ],
];
```

---

## Troubleshooting / استكشاف الأخطاء

### Common Issues

1. **User can't access module**
   - Check if module is enabled for their branch
   - Verify user has the required permission
   - Confirm user is assigned to the branch

2. **Permission not working**
   - Clear permission cache: `php artisan permission:cache-reset`
   - Verify permission name spelling
   - Check role assignment

3. **Branch data leaking**
   - Verify GlobalScope is applied
   - Check for missing `branch_id` WHERE clause
   - Test with different branch users

### Debug Permissions

```php
// List user's permissions
$user->getAllPermissions()->pluck('name');

// List user's roles
$user->getRoleNames();

// Check specific permission
$user->can('inventory.products.view'); // true/false
```

---

**Document Maintained By:** Development Team  
**Related Documents:**
- `ARCHITECTURE.md` - System architecture
- `docs/STORE_INTEGRATION_GUIDE.md` - Store integration
