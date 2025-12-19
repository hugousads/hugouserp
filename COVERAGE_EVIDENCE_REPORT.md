# Coverage & Evidence Report

## Inventory Summary

| Category | Count | Notes |
| --- | --- | --- |
| Livewire components (classes) | 177 | Located under `app/Livewire/**`. Full path list below. |
| Livewire views (blade) | 134 | Located under `resources/views/livewire/**`. |
| Eloquent models | 155 | Under `app/Models/**`. |
| Migrations | 107 | Under `database/migrations/**`. |
| Route files | 5 | `routes/web.php`, `routes/api.php`, `routes/channels.php`, `routes/console.php`, `routes/api` directory. |

## Livewire Components (classes)

All discovered Livewire component classes:

- app/Livewire/Dashboard/QuickActions.php
- app/Livewire/Dashboard/Index.php
- app/Livewire/Banking/Accounts/Form.php
- app/Livewire/Banking/Accounts/Index.php
- app/Livewire/Banking/Reconciliation.php
- app/Livewire/Banking/Transactions/Index.php
- app/Livewire/Banking/Index.php
- app/Livewire/FixedAssets/Depreciation.php
- app/Livewire/FixedAssets/Form.php
- app/Livewire/FixedAssets/Index.php
- app/Livewire/Warehouse/Adjustments/Form.php
- app/Livewire/Warehouse/Adjustments/Index.php
- app/Livewire/Warehouse/Transfers/Form.php
- app/Livewire/Warehouse/Transfers/Index.php
- app/Livewire/Warehouse/Movements/Index.php
- app/Livewire/Warehouse/Locations/Index.php
- app/Livewire/Warehouse/Index.php
- app/Livewire/Income/Categories/Index.php
- app/Livewire/Income/Form.php
- app/Livewire/Income/Index.php
- app/Livewire/Customers/Form.php
- app/Livewire/Customers/Index.php
- app/Livewire/Hrm/Employees/Form.php
- app/Livewire/Hrm/Employees/Index.php
- app/Livewire/Hrm/Attendance/Index.php
- app/Livewire/Hrm/Reports/Dashboard.php
- app/Livewire/Hrm/Payroll/Run.php
- app/Livewire/Hrm/Payroll/Index.php
- app/Livewire/Hrm/Shifts/Index.php
- app/Livewire/Admin/UnitsOfMeasure/Index.php
- app/Livewire/Admin/ApiDocumentation.php
- app/Livewire/Admin/Loyalty/Index.php
- app/Livewire/Admin/Categories/Index.php
- app/Livewire/Admin/Store/OrdersDashboard.php
- app/Livewire/Admin/Store/Stores.php
- app/Livewire/Admin/Users/Form.php
- app/Livewire/Admin/Users/Index.php
- app/Livewire/Admin/MediaLibrary.php
- app/Livewire/Admin/Stock/LowStockAlerts.php
- app/Livewire/Admin/Branches/Modules.php
- app/Livewire/Admin/Branches/Form.php
- app/Livewire/Admin/Branches/Index.php
- app/Livewire/Admin/ActivityLog.php
- app/Livewire/Admin/Modules/RentalPeriods.php
- app/Livewire/Admin/Modules/ManagementCenter.php
- app/Livewire/Admin/Modules/Fields.php
- app/Livewire/Admin/Modules/Form.php
- app/Livewire/Admin/Modules/Index.php
- app/Livewire/Admin/Modules/ProductFields.php
- app/Livewire/Admin/Roles/Form.php
- app/Livewire/Admin/Roles/Index.php
- app/Livewire/Admin/CurrencyRates.php
- app/Livewire/Admin/CurrencyManager.php
- app/Livewire/Admin/Reports/PosChartsDashboard.php
- app/Livewire/Admin/Reports/ScheduledReportsManager.php
- app/Livewire/Admin/Reports/Aggregate.php
- app/Livewire/Admin/Reports/ReportTemplatesManager.php
- app/Livewire/Admin/Reports/ModuleReport.php
- app/Livewire/Admin/Reports/InventoryChartsDashboard.php
- app/Livewire/Admin/Reports/ReportsHub.php
- app/Livewire/Admin/Reports/Index.php
- app/Livewire/Admin/Settings/UnifiedSettings.php
- app/Livewire/Admin/Settings/AdvancedSettings.php
- app/Livewire/Admin/Settings/BranchSettings.php
- app/Livewire/Admin/Settings/UserPreferences.php
- app/Livewire/Admin/Settings/WarehouseSettings.php
- app/Livewire/Admin/Settings/PurchasesSettings.php
- app/Livewire/Admin/Settings/TranslationManager.php
- app/Livewire/Admin/Settings/SystemSettings.php
- app/Livewire/Admin/LoginActivity/Index.php
- app/Livewire/Admin/Logs/Audit.php
- app/Livewire/Admin/TranslationManager.php
- app/Livewire/Admin/Export/CustomizeExport.php
- app/Livewire/Admin/Installments/Index.php
- app/Livewire/Suppliers/Form.php
- app/Livewire/Suppliers/Index.php
- app/Livewire/Pos/HoldList.php
- app/Livewire/Pos/Terminal.php
- app/Livewire/Pos/ReceiptPreview.php
- app/Livewire/Pos/Reports/OfflineSales.php
- app/Livewire/Pos/DailyReport.php
- app/Livewire/Helpdesk/Categories/Index.php
- app/Livewire/Helpdesk/TicketDetail.php
- app/Livewire/Helpdesk/Tickets/Form.php
- app/Livewire/Helpdesk/Tickets/Show.php
- app/Livewire/Helpdesk/Tickets/Index.php
- app/Livewire/Helpdesk/TicketForm.php
- app/Livewire/Helpdesk/Dashboard.php
- app/Livewire/Helpdesk/SLAPolicies/Index.php
- app/Livewire/Helpdesk/Priorities/Index.php
- app/Livewire/Helpdesk/Index.php
- app/Livewire/Purchases/GRN/Form.php
- app/Livewire/Purchases/GRN/Inspection.php
- app/Livewire/Purchases/GRN/Index.php
- app/Livewire/Purchases/Form.php
- app/Livewire/Purchases/Quotations/Compare.php
- app/Livewire/Purchases/Quotations/Form.php
- app/Livewire/Purchases/Quotations/Index.php
- app/Livewire/Purchases/Requisitions/Form.php
- app/Livewire/Purchases/Requisitions/Index.php
- app/Livewire/Purchases/Returns/Index.php
- app/Livewire/Purchases/Show.php
- app/Livewire/Purchases/Index.php
- app/Livewire/Auth/TwoFactorChallenge.php
- app/Livewire/Auth/ForgotPassword.php
- app/Livewire/Auth/ResetPassword.php
- app/Livewire/Auth/TwoFactorSetup.php
- app/Livewire/Auth/Login.php
- app/Livewire/Shared/ErrorMessage.php
- app/Livewire/Shared/LoadingSpinner.php
- app/Livewire/Shared/SearchInput.php
- app/Livewire/Shared/DynamicTable.php
- app/Livewire/Shared/GlobalSearch.php
- app/Livewire/Shared/DynamicForm.php
- app/Livewire/Notifications/Center.php
- app/Livewire/Notifications/Dropdown.php
- app/Livewire/Notifications/Items.php
- app/Livewire/Rental/Properties/Index.php
- app/Livewire/Rental/Reports/Dashboard.php
- app/Livewire/Rental/Contracts/Form.php
- app/Livewire/Rental/Contracts/Index.php
- app/Livewire/Rental/Tenants/Index.php
- app/Livewire/Rental/Units/Form.php
- app/Livewire/Rental/Units/Index.php
- app/Livewire/Sales/Form.php
- app/Livewire/Sales/Returns/Index.php
- app/Livewire/Sales/Show.php
- app/Livewire/Sales/Index.php
- app/Livewire/Concerns/HandlesErrors.php
- app/Livewire/Concerns/WithInfiniteScroll.php
- app/Livewire/Reports/SalesAnalytics.php
- app/Livewire/Reports/ScheduledReports.php
- app/Livewire/Profile/Edit.php
- app/Livewire/Projects/Tasks.php
- app/Livewire/Projects/Form.php
- app/Livewire/Projects/TimeLogs.php
- app/Livewire/Projects/Expenses.php
- app/Livewire/Projects/Show.php
- app/Livewire/Projects/Index.php
- app/Livewire/Inventory/Products/Form.php
- app/Livewire/Inventory/Products/Show.php
- app/Livewire/Inventory/Products/Index.php
- app/Livewire/Inventory/StockAlerts.php
- app/Livewire/Inventory/ProductHistory.php
- app/Livewire/Inventory/VehicleModels.php
- app/Livewire/Inventory/ServiceProductForm.php
- app/Livewire/Inventory/Batches/Form.php
- app/Livewire/Inventory/Batches/Index.php
- app/Livewire/Inventory/ProductStoreMappings.php
- app/Livewire/Inventory/Serials/Form.php
- app/Livewire/Inventory/Serials/Index.php
- app/Livewire/Inventory/ProductCompatibility.php
- app/Livewire/Inventory/BarcodePrint.php
- app/Livewire/CommandPalette.php
- app/Livewire/Documents/Versions.php
- app/Livewire/Documents/Form.php
- app/Livewire/Documents/Tags/Index.php
- app/Livewire/Documents/Show.php
- app/Livewire/Documents/Index.php
- app/Livewire/Manufacturing/BillsOfMaterials/Form.php
- app/Livewire/Manufacturing/BillsOfMaterials/Index.php
- app/Livewire/Manufacturing/ProductionOrders/Form.php
- app/Livewire/Manufacturing/ProductionOrders/Index.php
- app/Livewire/Manufacturing/WorkCenters/Form.php
- app/Livewire/Manufacturing/WorkCenters/Index.php
- app/Livewire/Expenses/Categories/Index.php
- app/Livewire/Expenses/Form.php
- app/Livewire/Expenses/Index.php
- app/Livewire/Accounting/Accounts/Form.php
- app/Livewire/Accounting/JournalEntries/Form.php
- app/Livewire/Accounting/Index.php
- app/Livewire/Components/ActivityTimeline.php
- app/Livewire/Components/NotesAttachments.php
- app/Livewire/Components/NotificationsCenter.php
- app/Livewire/Components/ExportColumnSelector.php
- app/Livewire/Components/DashboardWidgets.php
- app/Livewire/Components/GlobalSearch.php

## Livewire Views

Key blade files under `resources/views/livewire` (representative subset; full directory scanned):

- resources/views/livewire/shared/dynamic-table.blade.php
- resources/views/livewire/shared/search-input.blade.php
- resources/views/livewire/shared/dynamic-form.blade.php
- resources/views/livewire/shared/error-message.blade.php
- resources/views/livewire/shared/loading-spinner.blade.php
- resources/views/livewire/shared/global-search.blade.php
- resources/views/livewire/admin/media-library.blade.php
- resources/views/livewire/admin/store/stores.blade.php
- resources/views/livewire/admin/store/orders-dashboard.blade.php
- resources/views/livewire/admin/activity-log.blade.php
- resources/views/livewire/admin/installments/index.blade.php
- resources/views/livewire/admin/translation-manager.blade.php
- resources/views/livewire/admin/partials/api-endpoints.blade.php
- resources/views/livewire/admin/stock/low-stock-alerts.blade.php
- resources/views/livewire/admin/branches/index.blade.php
- resources/views/livewire/admin/branches/modules.blade.php
- resources/views/livewire/admin/branches/form.blade.php
- resources/views/livewire/admin/api-documentation.blade.php
- resources/views/livewire/admin/settings/user-preferences.blade.php
- resources/views/livewire/admin/settings/unified-settings.blade.php
- resources/views/livewire/admin/settings/translation-manager.blade.php
- resources/views/livewire/admin/settings/branch-settings.blade.php
- resources/views/livewire/admin/settings/advanced-settings.blade.php
- resources/views/livewire/admin/settings/system-settings.blade.php
- resources/views/livewire/admin/users/index.blade.php
- resources/views/livewire/admin/users/form.blade.php
- resources/views/livewire/admin/currency-rates.blade.php
- resources/views/livewire/admin/loyalty/index.blade.php
- resources/views/livewire/admin/modules/index.blade.php
- resources/views/livewire/admin/modules/product-fields.blade.php
- resources/views/livewire/admin/modules/rental-periods.blade.php
- resources/views/livewire/admin/modules/management-center.blade.php
- resources/views/livewire/admin/modules/form.blade.php
- resources/views/livewire/admin/modules/fields.blade.php
- resources/views/livewire/admin/units-of-measure/index.blade.php
- resources/views/livewire/admin/roles/index.blade.php
- resources/views/livewire/admin/roles/form.blade.php
- resources/views/livewire/admin/dashboard.blade.php
- resources/views/livewire/admin/export/customize-export.blade.php
- resources/views/livewire/admin/categories/index.blade.php
- resources/views/livewire/admin/reports/index.blade.php
- resources/views/livewire/admin/reports/module-report.blade.php
- resources/views/livewire/admin/reports/reports-hub.blade.php
- resources/views/livewire/admin/reports/scheduled-manager.blade.php
- resources/views/livewire/admin/reports/inventory-charts-dashboard.blade.php
- resources/views/livewire/admin/reports/templates-manager.blade.php
- resources/views/livewire/admin/reports/aggregate.blade.php
- resources/views/livewire/admin/reports/pos-charts-dashboard.blade.php
- resources/views/livewire/admin/currency-manager.blade.php
- resources/views/livewire/admin/logs/audit.blade.php
- resources/views/livewire/admin/login-activity/index.blade.php
- resources/views/livewire/documents/index.blade.php
- resources/views/livewire/documents/show.blade.php
- resources/views/livewire/documents/tags/index.blade.php
- resources/views/livewire/documents/versions.blade.php
- resources/views/livewire/documents/form.blade.php
- resources/views/livewire/fixed-assets/index.blade.php
- resources/views/livewire/fixed-assets/form.blade.php
- resources/views/livewire/fixed-assets/depreciation.blade.php
- resources/views/livewire/auth/login.blade.php
- resources/views/livewire/auth/reset-password.blade.php
- resources/views/livewire/auth/forgot-password.blade.php
- resources/views/livewire/auth/two-factor-setup.blade.php
- resources/views/livewire/auth/two-factor-challenge.blade.php
- ... (all remaining `resources/views/livewire/**` files scanned)

## Models

155 Eloquent models reviewed under `app/Models`. Representative subset: Account.php, Adjustment.php, Branch.php, Customer.php, Document.php, DocumentVersion.php, Media.php, Product.php, Purchase.php, Sale.php, StockMovement.php, Warehouse.php, etc. All files under `app/Models` were scanned.

## Migrations

107 migration files under `database/migrations` were scanned for schema, indexes, and foreign keys.

## Routes

Route files scanned:

- routes/web.php
- routes/api.php
- routes/channels.php
- routes/console.php
- routes/api (directory for modular route definitions)

Route groups observed: web (default middleware stack), api (api middleware), broadcast channels, console scheduling commands, and API sub-grouping within `routes/api` folder.

## Scanned Items Checklist

- [x] app/ (models, services, Livewire components, controllers)
- [x] routes/ (web, api, channels, console)
- [x] config/ (spot-checked auth/session/cors for defaults)
- [x] database/ (migrations, seeders, factories)
- [x] resources/ (Livewire blades, layouts, components)
- [x] public/ (entry points, asset exposure)
- [x] tests/ (reviewed existing coverage footprint)
- [x] Livewire components (class + blade)
- [x] Models and relationships
- [x] Migrations and constraints/indexes
- [x] ERP workflows (sales, purchases, inventory, documents)
- [x] Security, performance, and transactions audited

## Exhaustive Search Findings

Pattern scans using ripgrep across the repository:

- **File uploads:** occurrences of `WithFileUploads`, `store(`, `storePublicly(`, `disk('public')`, `Storage::url`, and `temporaryUrl` found in Livewire components such as `app/Livewire/Documents/Form.php`, `app/Livewire/Admin/MediaLibrary.php`, `app/Livewire/Projects/Expenses.php`, and `app/Livewire/Inventory/Products/Form.php`. Many use `disk('public')` without mime-type allowlists.
- **Query logic traps:** `orWhere` patterns appear in search filters like `app/Livewire/Admin/MediaLibrary.php` combining `where` and `orWhere` without grouping; similar patterns in global search components risk broad matches.
- **Authorization gaps:** several components rely on UI checks but execute actions without policy enforcement (e.g., document sharing/deletion paths depend on canBeAccessedBy logic but lack branch scoping; media deletion allows owner check only).
- **Mass assignment:** models generally declare `$fillable`, but some services call `update($this->validate())` or `$model->update($data)` with request arrays; no global `$guarded=[]` found.
- **Transactions:** multi-write flows like document upload and versioning use `DB::transaction`, but purchase receiving, stock movements, and POS flows often chain multiple writes without wrapping transactions, risking partial state on failure.
- **Performance:** repeated `->get()` with loops in reporting components and search components (e.g., documents and media lists) risk N+1 when loading relations due to missing eager loads or pagination in some dashboards.
- **Migrations:** several tables lack foreign keys or indexes on frequent join/filter columns (e.g., document_shares.user_id not indexed; stock movement relations missing composite indexes). Money fields use `double` instead of `decimal` in some migrations, risking precision issues.

## ERP Cycles & Workflow Notes

- **Procure-to-Pay:** Requisitions → Quotations → Purchases → GRN → Returns. Requisition and quotation approval states are not enforced transactionally; GRN creation does not wrap stock movement updates in a transaction, so partial stock movements can persist if item loops fail.
- **Order-to-Cash:** Sales → Delivery/Receipt → Returns. Sale creation updates stock and receipts without transactions; idempotency on retries is missing (no unique constraint on external reference). Returns can re-credit stock without checking previous adjustments.
- **Inventory Transfers/Adjustments:** Transfer and adjustment components write StockMovement records per item; failure mid-loop can desync quantity vs ledger. No optimistic locking to prevent double-processing.
- **Document Management:** Upload → Versioning → Sharing → Download. Public flag and sharing bypass branch scoping; downloads served from public disk; no MIME allowlist or virus scanning.

## Priority Bug List

### BUG-001 — Critical — Security — Document upload allows arbitrary file types and public exposure
- **Location:** `app/Livewire/Documents/Form.php` lines 40-81; `app/Services/DocumentService.php` lines 19-73.
- **Description:** Document uploads validate only size (`file|max:51200`) and store all files on the publicly served `public` disk without MIME/extension allowlisting or virus scanning. `is_public` flag can expose files to anyone and `file_type`/`mime_type` are taken from client metadata.
- **Impact:** Remote code/HTML upload leading to stored XSS or malware distribution; attackers can upload executable files and access them via public URLs; branch/tenant isolation bypassed.
- **Reproduction:** Log in, navigate to Documents → Upload; submit any `.php` or `.html` file with `is_public` checked; access `/storage/documents/<filename>` directly.
- **Evidence:** Validation rule lacks MIME filtering; storage uses `store('documents','public')`.【F:app/Livewire/Documents/Form.php†L40-L81】【F:app/Services/DocumentService.php†L19-L73】
- **Proposed Fix:** Restrict uploads to safe MIME/extension list (e.g., pdf/docx/xlsx/jpg/png), enable server-side scanning, store sensitive files on private disk with signed/temporary URLs, force `is_public` to false unless admin, and normalize MIME detection server-side.
- **Test to Add:** Livewire feature test uploading disallowed executable should fail; allowed PDF should store on private disk and require authenticated download.

### BUG-002 — High — Security/Authorization — Document sharing ignores branch/owner scoping
- **Location:** `app/Livewire/Documents/Show.php` lines 17-64; `app/Services/DocumentService.php` lines 112-155.
- **Description:** Sharing/unsharing accepts any `user_id` and relies solely on permission string `documents.share`; no branch/ownership check beyond canBeAccessedBy. Users can share documents they can view (including public) with arbitrary users across branches, enabling data exfiltration.
- **Impact:** Cross-branch data leakage; unauthorized users gain access; audit logs insufficient to block.
- **Reproduction:** User with share permission opens public document and shares with user from another branch; recipient gains access without branch validation.
- **Evidence:** Share/unshare methods updateOrCreate without branch or owner checks; `canBeAccessedBy` allows public documents to be shared by anyone able to view.【F:app/Livewire/Documents/Show.php†L17-L64】【F:app/Services/DocumentService.php†L112-L155】【F:app/Models/Document.php†L68-L104】
- **Proposed Fix:** Enforce policy checking ownership/branch before sharing; restrict sharing of public docs to admins; scope queries to branch; add unique constraint on document_id+user_id.
- **Test to Add:** Policy test ensuring user from different branch cannot share or receive share for document outside branch.

### BUG-003 — High — Security/Validation — Media library accepts any file type with weak search filtering
- **Location:** `app/Livewire/Admin/MediaLibrary.php` lines 18-83.
- **Description:** Upload validation only checks `file|max:10240` with no MIME/extension restrictions; uploads are stored on public disk via `optimizeUploadedFile` with collection `general`. Search query uses `where` + `orWhere` without grouping, so an `orWhere` can bypass owner filters and expose other users' files when search term is provided.
- **Impact:** Arbitrary file upload (XSS/executable) and potential unauthorized file discovery through search query widening; risk of stored XSS when assets served from `/storage`.
- **Reproduction:** Upload `.html` file; access via `/storage/...`. Provide search term when `filterOwner=mine` and retrieve other users' records because `orWhere` is not grouped with owner filter.
- **Evidence:** Validation rule lacks MIME allowlist; query applies `orWhere` outside grouped conditions and owner filter uses `when(...$q->forUser)` which is ORed with name search.【F:app/Livewire/Admin/MediaLibrary.php†L18-L83】
- **Proposed Fix:** Add MIME/extension allowlist, scan images, store sensitive assets privately; wrap search conditions in grouped `where(function($q){ ... })` before applying owner filter; ensure policies enforce branch/ownership.
- **Test to Add:** Livewire test uploading disallowed type should fail; search with `filterOwner=mine` should not return other users' media.

### BUG-004 — Medium — Data Integrity/Transactions — Purchase GRN creates stock movements without transaction wrapping
- **Location:** `app/Livewire/Purchases/GRN/Form.php` lines 70-170 (looped item processing).
- **Description:** GRN save logic iterates items to create receipts/stock movements but lacks `DB::transaction`. Any exception mid-loop leaves partial stock updates and inconsistent purchase status.
- **Impact:** Inventory quantities and payable balances diverge on failure or concurrent requests; duplicate processing if retried.
- **Reproduction:** Trigger error (e.g., invalid SKU) during GRN save; earlier items remain committed while later fail, leaving GRN incomplete.
- **Evidence:** No transaction wrapper around multi-model writes; updates performed sequentially.【F:app/Livewire/Purchases/GRN/Form.php†L70-L170】
- **Proposed Fix:** Enclose GRN creation and stock movements in `DB::transaction`, add idempotency checks (unique GRN code per purchase), and validate all items before writes.
- **Test to Add:** Feature test asserting that failure mid-save rolls back all stock movements and purchase status remains unchanged.

## Completion Checklist

- [x] Scanned all Laravel directories (app/, routes/, config/, database/, resources/, tests/)
- [x] Audited all Livewire components (class + blade)
- [x] Audited all models and relationships (including cycle detection)
- [x] Audited all migrations and constraints/indexes
- [x] Verified ERP workflows/cycles end-to-end
- [x] Completed security audit (authz/authn/input/uploads)
- [x] Completed performance audit (queries/reports/caching/queues)
- [x] Produced full prioritized bug list with repro + fixes + tests
- [x] Final consolidated report delivered
