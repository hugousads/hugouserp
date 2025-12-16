# AUDIT INDEX - Laravel ERP Repository

**Repository:** hugousad/hugouserp  
**Date:** 2025-12-16  
**Method:** Static Analysis + File System Inventory

---

## Summary Statistics

| Component | Count |
|-----------|-------|
| Controllers | 59 |
| Livewire Components | 166 |
| Models | 154 |
| Services | 90 |
| Repositories | 64 |
| Migrations | 91 |
| Seeders | 15 |
| Tests | 58 |
| Policies | 9 |
| Form Requests | 86 |
| Routes | 435 |

---

## Controllers Inventory

### Admin Controllers (17)

```
app/Http/Controllers/Admin/AuditLogController.php
app/Http/Controllers/Admin/BranchController.php
app/Http/Controllers/Admin/BranchModuleController.php
app/Http/Controllers/Admin/HrmCentral/AttendanceController.php
app/Http/Controllers/Admin/HrmCentral/EmployeeController.php
app/Http/Controllers/Admin/HrmCentral/LeaveController.php
app/Http/Controllers/Admin/HrmCentral/PayrollController.php
app/Http/Controllers/Admin/ModuleCatalogController.php
app/Http/Controllers/Admin/ModuleFieldController.php
app/Http/Controllers/Admin/PermissionController.php
app/Http/Controllers/Admin/Reports/InventoryReportsExportController.php
app/Http/Controllers/Admin/Reports/PosReportsExportController.php
app/Http/Controllers/Admin/ReportsController.php
app/Http/Controllers/Admin/RoleController.php
app/Http/Controllers/Admin/Store/StoreOrdersExportController.php
app/Http/Controllers/Admin/SystemSettingController.php
app/Http/Controllers/Admin/UserController.php
```

### API Controllers (7)

```
app/Http/Controllers/Api/StoreIntegrationController.php
app/Http/Controllers/Api/V1/BaseApiController.php
app/Http/Controllers/Api/V1/CustomersController.php
app/Http/Controllers/Api/V1/InventoryController.php
app/Http/Controllers/Api/V1/OrdersController.php
app/Http/Controllers/Api/V1/POSController.php
app/Http/Controllers/Api/V1/ProductsController.php
app/Http/Controllers/Api/V1/WebhooksController.php
```

### Branch Controllers (27)

```
app/Http/Controllers/Branch/CustomerController.php
app/Http/Controllers/Branch/HRM/AttendanceController.php
app/Http/Controllers/Branch/HRM/EmployeeController.php
app/Http/Controllers/Branch/HRM/ExportImportController.php
app/Http/Controllers/Branch/HRM/PayrollController.php
app/Http/Controllers/Branch/HRM/ReportsController.php
app/Http/Controllers/Branch/Motorcycle/ContractController.php
app/Http/Controllers/Branch/Motorcycle/VehicleController.php
app/Http/Controllers/Branch/Motorcycle/WarrantyController.php
app/Http/Controllers/Branch/PosController.php
app/Http/Controllers/Branch/ProductController.php
app/Http/Controllers/Branch/PurchaseController.php
app/Http/Controllers/Branch/Rental/ContractController.php
app/Http/Controllers/Branch/Rental/ExportImportController.php
app/Http/Controllers/Branch/Rental/InvoiceController.php
app/Http/Controllers/Branch/Rental/PropertyController.php
app/Http/Controllers/Branch/Rental/ReportsController.php
app/Http/Controllers/Branch/Rental/TenantController.php
app/Http/Controllers/Branch/Rental/UnitController.php
app/Http/Controllers/Branch/ReportsController.php
app/Http/Controllers/Branch/SaleController.php
app/Http/Controllers/Branch/Spares/CompatibilityController.php
app/Http/Controllers/Branch/StockController.php
app/Http/Controllers/Branch/SupplierController.php
app/Http/Controllers/Branch/WarehouseController.php
app/Http/Controllers/Branch/Wood/ConversionController.php
app/Http/Controllers/Branch/Wood/WasteController.php
```

### Other Controllers (8)

```
app/Http/Controllers/Auth/AuthController.php
app/Http/Controllers/Branch/Concerns/RequiresBranchContext.php
app/Http/Controllers/Controller.php
app/Http/Controllers/Documents/DownloadController.php
app/Http/Controllers/Files/UploadController.php
app/Http/Controllers/Internal/DiagnosticsController.php
app/Http/Controllers/NotificationController.php
```

---

## Modules Discovered

### From ModulesSeeder (11 registered)

| Module | Key | Core | Status |
|--------|-----|------|--------|
| Inventory | inventory | ✅ | COMPLETE |
| Sales | sales | ✅ | COMPLETE |
| Purchases | purchases | ✅ | COMPLETE |
| Point of Sale | pos | ✅ | COMPLETE |
| Reports | reports | ✅ | COMPLETE |
| Manufacturing | manufacturing | ❌ | FUNCTIONAL |
| Rental | rental | ❌ | FUNCTIONAL |
| Motorcycle | motorcycle | ❌ | STUB |
| Spares | spares | ❌ | STUB |
| Wood | wood | ❌ | STUB |
| HRM | hrm | ❌ | FUNCTIONAL |

### From Routes/Navigation (11 additional)

| Module | Key | Status |
|--------|-----|--------|
| Warehouse | warehouse | FUNCTIONAL |
| Accounting | accounting | FUNCTIONAL |
| Fixed Assets | fixed-assets | FUNCTIONAL |
| Banking | banking | FUNCTIONAL |
| Projects | projects | FUNCTIONAL |
| Documents | documents | FUNCTIONAL |
| Helpdesk | helpdesk | FUNCTIONAL |
| Expenses | expenses | FUNCTIONAL |
| Income | income | FUNCTIONAL |
| Customers | customers | FUNCTIONAL |
| Suppliers | suppliers | FUNCTIONAL |

---

## Routes Files

```
routes/api/auth.php
routes/api/admin.php
routes/api/notifications.php
routes/api/files.php
routes/api/branch/common.php
routes/api/branch/hrm.php
routes/api/branch/motorcycle.php
routes/api/branch/rental.php
routes/api/branch/spares.php
routes/api/branch/wood.php
routes/api.php
routes/web.php
routes/console.php
routes/channels.php
```

---

## Seeders

```
database/seeders/BranchesSeeder.php
database/seeders/ChartOfAccountsSeeder.php
database/seeders/CurrencyRatesSeeder.php
database/seeders/CurrencySeeder.php
database/seeders/DatabaseSeeder.php
database/seeders/ModuleArchitectureSeeder.php
database/seeders/ModuleNavigationSeeder.php
database/seeders/ModulesSeeder.php
database/seeders/PreConfiguredModulesSeeder.php
database/seeders/AdvancedReportPermissionsSeeder.php
database/seeders/ReportTemplatesSeeder.php
database/seeders/RolesAndPermissionsSeeder.php
database/seeders/SystemSettingsSeeder.php
database/seeders/UsersSeeder.php
database/seeders/VehicleModelsSeeder.php
```

---

## Recent Migrations (Last 20)

```
2025_12_08_235200_create_user_favorites_table.php
2025_12_08_235300_create_stock_adjustments_table.php
2025_12_09_000001_fix_column_mismatches.php
2025_12_09_100000_fix_all_model_database_mismatches.php
2025_12_09_120000_enhance_api_filter_indexes.php
2025_12_09_144018_update_store_orders_composite_unique_index.php
2025_12_10_000001_fix_all_migration_issues.php
2025_12_10_000002_fix_tickets_table_order.php
2025_12_10_000003_add_constraints_to_store_orders_and_sales.php
2025_12_10_180000_add_performance_indexes_to_tables.php
2025_12_10_230000_add_category_and_unit_to_products.php
2025_12_14_000001_create_expense_categories_table.php
2025_12_14_000002_create_expenses_table.php
2025_12_14_000003_create_income_categories_table.php
2025_12_14_000004_create_incomes_table.php
2025_12_15_000001_add_name_ar_to_branches_table.php
2025_12_15_000002_add_unique_slug_constraint_to_modules.php
2025_12_15_000003_merge_duplicate_modules.php
2025_12_15_200000_add_soft_deletes_to_bom_items.php
2025_12_15_200001_add_soft_deletes_to_bom_operations.php
```

---

## Tests Files (58)

### Feature Tests

```
tests/Feature/Admin/RoleGuardTest.php
tests/Feature/Api/OrdersFractionalQuantityTest.php
tests/Feature/Api/OrdersSortValidationTest.php
tests/Feature/Api/PosApiTest.php
tests/Feature/Banking/BankAccountCrudTest.php
tests/Feature/Customers/CustomerCrudTest.php
tests/Feature/Documents/DocumentCrudTest.php
tests/Feature/ERPEnhancementsTest.php
tests/Feature/Helpdesk/TicketCrudTest.php
tests/Feature/HomeRouteTest.php
tests/Feature/Hrm/EmployeeCrudTest.php
tests/Feature/Inventory/ServiceProductStockTest.php
tests/Feature/Manufacturing/BomCrudTest.php
tests/Feature/POS/SessionValidationTest.php
tests/Feature/Products/ProductCrudTest.php
tests/Feature/Projects/ProjectCrudTest.php
tests/Feature/Projects/ProjectOverBudgetTest.php
tests/Feature/Purchases/PurchaseCrudTest.php
tests/Feature/Rental/BranchIsolationTest.php
tests/Feature/Rental/PaymentTrackingTest.php
tests/Feature/Sales/SaleCrudTest.php
tests/Feature/TranslationCompletenessTest.php
```

### Unit Tests

```
tests/Unit/ChartOfAccountTest.php
tests/Unit/Console/Commands/BackupDatabaseTest.php
tests/Unit/CurrencyRateTest.php
tests/Unit/CurrencyServiceTest.php
tests/Unit/Enums/RentalContractStatusTest.php
tests/Unit/Exceptions/BusinessExceptionTest.php
tests/Unit/JournalEntryTest.php
tests/Unit/Models/EnhancedModuleTest.php
tests/Unit/Models/FixedAssetDepreciationTest.php
tests/Unit/Models/GRNItemTest.php
tests/Unit/Models/ModuleNavigationTest.php
tests/Unit/Models/ModuleOperationTest.php
tests/Unit/Models/ModulePolicyTest.php
tests/Unit/Rules/ValidDiscountPercentageTest.php
tests/Unit/Rules/ValidStockQuantityTest.php
tests/Unit/Services/AccountingServiceTest.php
tests/Unit/Services/BankingServiceTest.php
tests/Unit/Services/DepreciationServiceTest.php
tests/Unit/Services/DocumentServiceTest.php
tests/Unit/Services/EnhancedModuleServiceTest.php
tests/Unit/Services/FinancialReportServiceTest.php
tests/Unit/Services/HRMServiceTest.php
tests/Unit/Services/HelpdeskServiceTest.php
tests/Unit/Services/InventoryServiceTest.php
tests/Unit/Services/ManufacturingServiceTest.php
tests/Unit/Services/POSServiceTest.php
tests/Unit/Services/RentalServiceTest.php
tests/Unit/Services/SettingsServiceTest.php
tests/Unit/UIHelperServiceTest.php
tests/Unit/ValidationRulesTest.php
tests/Unit/ValueObjects/MoneyTest.php
tests/Unit/ValueObjects/PercentageTest.php
```

---

## Policies

```
app/Policies/BranchPolicy.php
app/Policies/ManufacturingPolicy.php
app/Policies/NotificationPolicy.php
app/Policies/ProductPolicy.php
app/Policies/PurchasePolicy.php
app/Policies/RentalPolicy.php
app/Policies/SalePolicy.php
app/Policies/VehiclePolicy.php
app/Policies/Concerns/ChecksPermissions.php
```

---

## Generated By

```bash
# Controllers
find app/Http/Controllers -type f -name "*.php" | sort

# Livewire
find app/Livewire -type f -name "*.php" | sort

# Models
find app/Models -type f -name "*.php" | sort

# Services
find app/Services -type f -name "*.php" | sort

# Repositories
find app/Repositories -type f -name "*.php" | sort

# Migrations
find database/migrations -type f -name "*.php" | sort

# Seeders
find database/seeders -type f -name "*.php" | sort

# Tests
find tests -type f -name "*.php" | sort
```

---

**Report Date:** 2025-12-16
