# Validation and Testing Guide

## Pre-Migration Validation

### 1. Check Current State
```sql
-- Check if buggy tables exist
SHOW TABLES LIKE 'stock_transfer%';

-- Check current column names (should show 'transfer_id' before migration)
DESCRIBE stock_transfer_approvals;
DESCRIBE stock_transfer_documents;
DESCRIBE stock_transfer_history;

-- Check if any data exists
SELECT COUNT(*) FROM stock_transfer_approvals;
SELECT COUNT(*) FROM stock_transfer_documents;
SELECT COUNT(*) FROM stock_transfer_history;
```

### 2. Backup Database
```bash
# Create backup before migration
php artisan db:backup
# OR
mysqldump -u username -p database_name > backup_before_fix.sql
```

## Running the Migration

```bash
# Run the migration
php artisan migrate

# If issues occur, rollback
php artisan migrate:rollback
```

## Post-Migration Validation

### 1. Verify Tables Created
```sql
-- Should show 5 tables
SHOW TABLES LIKE 'stock_transfer%';

-- Expected output:
-- stock_transfer_approvals
-- stock_transfer_documents
-- stock_transfer_history
-- stock_transfer_items
-- stock_transfers
```

### 2. Verify Column Names Fixed
```sql
-- Check stock_transfer_approvals (should have stock_transfer_id)
DESCRIBE stock_transfer_approvals;

-- Check stock_transfer_documents (should have stock_transfer_id)
DESCRIBE stock_transfer_documents;

-- Check stock_transfer_history (should have stock_transfer_id)
DESCRIBE stock_transfer_history;

-- Check stock_transfer_items (should have stock_transfer_id)
DESCRIBE stock_transfer_items;
```

### 3. Verify Foreign Keys
```sql
-- Check all foreign keys reference correct tables
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_NAME = 'stock_transfers'
ORDER BY TABLE_NAME;

-- Expected output should show:
-- stock_transfer_approvals.stock_transfer_id -> stock_transfers.id
-- stock_transfer_documents.stock_transfer_id -> stock_transfers.id
-- stock_transfer_history.stock_transfer_id -> stock_transfers.id
-- stock_transfer_items.stock_transfer_id -> stock_transfers.id
```

### 4. Verify Indexes
```sql
-- Check indexes on stock_transfer_approvals
SHOW INDEXES FROM stock_transfer_approvals;

-- Check indexes on stock_transfer_documents
SHOW INDEXES FROM stock_transfer_documents;

-- Check indexes on stock_transfer_history
SHOW INDEXES FROM stock_transfer_history;
```

## Functional Testing

### 1. Test Basic Transfer Creation
```php
// Via Tinker: php artisan tinker

use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\Branch;

// Create a test transfer
$transfer = StockTransfer::create([
    'transfer_number' => 'TEST-001',
    'from_warehouse_id' => Warehouse::first()->id,
    'to_warehouse_id' => Warehouse::skip(1)->first()->id,
    'from_branch_id' => Branch::first()->id,
    'to_branch_id' => Branch::first()->id,
    'status' => 'draft',
    'priority' => 'medium',
]);

// Verify it was created
echo "Transfer created with ID: " . $transfer->id;
```

### 2. Test Transfer Items
```php
use App\Models\StockTransferItem;
use App\Models\Product;

// Add an item to the transfer
$item = StockTransferItem::create([
    'stock_transfer_id' => $transfer->id,
    'product_id' => Product::first()->id,
    'qty_requested' => 10,
    'unit_cost' => 100,
]);

// Verify relationship works
$transfer->load('items');
echo "Transfer has " . $transfer->items->count() . " items";
```

### 3. Test Approvals
```php
use App\Models\StockTransferApproval;
use App\Models\User;

// Create an approval
$approval = StockTransferApproval::create([
    'stock_transfer_id' => $transfer->id,
    'approval_level' => 1,
    'approver_id' => User::first()->id,
    'status' => 'pending',
]);

// Verify relationship works
$transfer->load('approvals');
echo "Transfer has " . $transfer->approvals->count() . " approvals";
```

### 4. Test Documents
```php
use App\Models\StockTransferDocument;

// Attach a document
$document = StockTransferDocument::create([
    'stock_transfer_id' => $transfer->id,
    'document_type' => 'packing_list',
    'file_name' => 'test.pdf',
    'file_path' => '/path/to/test.pdf',
]);

// Verify relationship works
$transfer->load('documents');
echo "Transfer has " . $transfer->documents->count() . " documents";
```

### 5. Test History Tracking
```php
use App\Models\StockTransferHistory;

// Record a status change
$history = StockTransferHistory::create([
    'stock_transfer_id' => $transfer->id,
    'from_status' => 'draft',
    'to_status' => 'pending',
    'changed_by' => User::first()->id,
    'changed_at' => now(),
]);

// Verify relationship works
$transfer->load('history');
echo "Transfer has " . $transfer->history->count() . " history records";
```

### 6. Test Status Changes (Integration Test)
```php
// Test the full workflow
$transfer->update(['status' => 'pending']);
$transfer->approve(User::first()->id); // Should create history record automatically
$transfer->markAsShipped(User::first()->id);
$transfer->markAsReceived(User::first()->id);

// Verify history was tracked
$transfer->refresh();
echo "Transfer status: " . $transfer->status;
echo "\nHistory count: " . $transfer->history()->count();
```

### 7. Clean Up Test Data
```php
// Delete test transfer (will cascade to related records)
$transfer->forceDelete();
echo "Test data cleaned up";
```

## Service Testing

### Test via StockTransferService
```php
use App\Services\StockTransferService;

$service = app(StockTransferService::class);

// Test create
$transfer = $service->create([
    'from_warehouse_id' => 1,
    'to_warehouse_id' => 2,
    'from_branch_id' => 1,
    'to_branch_id' => 1,
    'priority' => 'medium',
    'reason' => 'Testing',
    'items' => [
        [
            'product_id' => 1,
            'qty_requested' => 5,
            'unit_cost' => 100,
        ]
    ],
], auth()->id());

// Test approve
$service->approve($transfer->id, auth()->id());

// Test ship
$service->ship($transfer->id, [
    'tracking_number' => 'TEST123',
    'courier_name' => 'Test Courier',
], auth()->id());

// Clean up
$transfer->forceDelete();
```

## Expected Results

After all tests pass, you should see:

✅ 5 stock_transfer tables exist
✅ All columns named `stock_transfer_id` 
✅ All foreign keys point to `stock_transfers` table
✅ All indexes created correctly
✅ StockTransfer model can CRUD records
✅ StockTransferItem model works correctly
✅ StockTransferApproval model works correctly
✅ StockTransferDocument model works correctly
✅ StockTransferHistory model works correctly
✅ All relationships load correctly
✅ Status change tracking works
✅ Service layer functions correctly

## Troubleshooting

### Issue: "Table 'stock_transfers' doesn't exist"
**Solution**: Run `php artisan migrate`

### Issue: "Unknown column 'transfer_id'"
**Solution**: Migration hasn't run yet. Run `php artisan migrate`

### Issue: "Unknown column 'stock_transfer_id'"
**Solution**: Migration ran but there's a typo somewhere. Check model fillable arrays.

### Issue: "Foreign key constraint fails"
**Solution**: Ensure stock_transfers table is created before the related tables

### Issue: "SQLSTATE[42S02]: Base table or view not found: stock_transfer_histories"
**Solution**: The model needs explicit table name. Check `StockTransferHistory` has `protected $table = 'stock_transfer_history';`

## Performance Testing

After validation, test with larger datasets:

```sql
-- Test query performance
EXPLAIN SELECT * FROM stock_transfers 
WHERE status = 'pending' 
ORDER BY created_at DESC 
LIMIT 100;

-- Check index usage
SHOW INDEX FROM stock_transfers;

-- Test join performance
EXPLAIN SELECT st.*, sti.* 
FROM stock_transfers st
JOIN stock_transfer_items sti ON sti.stock_transfer_id = st.id
WHERE st.status = 'pending';
```

## Rollback Testing

If needed, test rollback:

```bash
# Rollback the migration
php artisan migrate:rollback --step=1

# Verify tables removed
# stock_transfers and stock_transfer_items should be gone
# Other tables should have transfer_id instead of stock_transfer_id

# Re-run migration
php artisan migrate
```

## Production Deployment Checklist

- [ ] Backup database
- [ ] Test migration on staging environment
- [ ] Verify all tests pass
- [ ] Schedule maintenance window if needed
- [ ] Run migration during low-traffic period
- [ ] Monitor logs for errors
- [ ] Verify foreign keys created successfully
- [ ] Test key workflows (create, approve, ship, receive)
- [ ] Check application logs for SQL errors
- [ ] Monitor system performance
- [ ] Have rollback plan ready
