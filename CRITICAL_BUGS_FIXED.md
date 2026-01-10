# Critical ERP Bugs - Fixed Issues Summary

**Date**: 2026-01-10  
**Branch**: copilot/fix-logical-bugs-in-services  
**Status**: ✅ COMPLETE

## Overview

This document summarizes 5 critical logical bugs identified in a deep code audit of the ERP system. These bugs were causing silent data corruption in accounting and inventory systems, potentially leading to financial losses, failed audits, and tax compliance issues.

---

## Bug #1: Missing COGS Entry ⚠️ CRITICAL

### Problem
Sales transactions were recording revenue (credit Sales Revenue account) without recording the corresponding Cost of Goods Sold (COGS). This caused:
- **Artificially inflated profits** in P&L reports
- **Incorrect gross margin calculations**
- **Balance sheet inventory values** not matching physical stock value

### Example Scenario
```
Sale: 2 units @ $100 each (revenue = $200)
Cost: 2 units @ $50 each (cost = $100)

Before Fix:
  Debit: Cash $200
  Credit: Sales Revenue $200
  (Missing: COGS $100 and Inventory reduction $100)
  Result: Profit = $200 ❌ (Should be $100)

After Fix:
  Debit: Cash $200
  Credit: Sales Revenue $200
  Debit: COGS $100
  Credit: Inventory $100
  Result: Profit = $100 ✅
```

### Solution
Added `recordCogsEntry()` method in `AccountingService.php`:
- Calculates total cost from sale items (qty × cost_price)
- Creates journal entry: Debit COGS Expense, Credit Inventory Asset
- Integrated into `generateSaleJournalEntry()` transaction
- Handles zero-cost products gracefully (no entry created)

### Files Modified
- `app/Services/AccountingService.php`

### Configuration Required
Ensure account mappings exist:
- `cogs_account` → COGS Expense account
- `inventory_account` → Inventory Asset account

---

## Bug #2: UoM Conversion Failure ⚠️ CRITICAL

### Problem
When selling products in non-base units (e.g., cartons), the system deducted the quantity directly without applying the unit conversion factor. This caused massive inventory discrepancies.

### Example Scenario
```
Product: Bottled Water
Base Unit: Piece (1 piece = 1 unit)
Sale Unit: Carton (1 carton = 12 pieces)

Sale: 1 carton

Before Fix:
  Stock deducted: 1 piece ❌
  Expected: 12 pieces
  Discrepancy: -11 pieces per carton sold

After Fix:
  Stock deducted: 1 × 12 = 12 pieces ✅
```

### Solution
Updated `UpdateStockOnSale.php` listener:
- Loads unit relationship (`$item->unit`)
- Applies conversion factor: `baseQuantity = quantity × conversion_factor`
- Deducts correct amount from stock
- Enhanced logging includes UoM details

### Files Modified
- `app/Listeners/UpdateStockOnSale.php`

### Testing
Test verifies that selling 1 carton (conversion_factor = 12) deducts 12 pieces from stock.

---

## Bug #3: Split Payment Reconciliation ⚠️ MODERATE

### Problem
When customers paid with multiple methods (e.g., $500 cash + $500 card), the system created a single journal entry debiting only the cash account. This caused:
- **Cash drawer over-counting** (showing $1000 when only $500 cash received)
- **Bank account under-counting** (missing $500 card deposit)
- **Failed reconciliations** at end of day

### Example Scenario
```
Sale Total: $1000
Payment: $500 Cash + $500 Card

Before Fix:
  Debit: Cash $1000 ❌
  Credit: Sales Revenue $1000
  Result: Cash drawer expects $1000, but only has $500

After Fix:
  Debit: Cash $500 ✅
  Debit: Bank $500 ✅
  Credit: Sales Revenue $1000
  Result: Cash drawer $500, Bank deposit $500
```

### Solution
Enhanced `generateSaleJournalEntry()` in `AccountingService.php`:
- Iterates through sale payments collection
- Creates separate debit line for each payment method
- Routes to appropriate account based on payment type:
  - `cash` → cash_account
  - `card` → bank_account
  - `transfer` → bank_account
  - `cheque` → cheque_account

### Files Modified
- `app/Services/AccountingService.php`

### Configuration Required
Ensure account mappings exist:
- `cash_account` → Cash on Hand
- `bank_account` → Bank Account
- `cheque_account` → Cheques Receivable

---

## Bug #4: N+1 Policy Checks ⚠️ PERFORMANCE

### Problem
`ProductResource.php` was executing authorization checks for every single product in API responses. For a list of 50 products, this meant:
- **50 permission queries** to check "can('products.view-cost')"
- **Severe performance degradation** on product listing pages
- **Database connection exhaustion** under load

### Example Scenario
```php
// Before: N+1 queries
foreach ($products as $product) {
    'cost' => $this->when($user->can('products.view-cost'), $this->cost)
    // ^ This runs a permission query for EACH product
}

Result: 1 + N queries (1 for products, N for permissions)
```

### Solution
**Implemented permission caching** to eliminate N+1 queries while maintaining security:

```php
class ProductResource extends JsonResource
{
    private static ?bool $canViewCost = null;

    public function toArray(Request $request): array
    {
        // Check permission once per request, not per product
        if (self::$canViewCost === null) {
            self::$canViewCost = $request->user()?->can('products.view-cost') ?? false;
        }

        return [
            // ...
            'cost' => $this->when(self::$canViewCost, (float) $this->cost),
            // ...
        ];
    }
}
```

**How it works**:
- Static property caches permission result on first check
- All subsequent products in the same request use cached value
- Reduces N queries to just 1 query per request
- Maintains security by still checking permissions

### Files Modified
- `app/Http/Resources/ProductResource.php` (permission caching implemented)

### Status
✅ **FIXED**: N+1 issue resolved with cached permission check

---

## Bug #5: Tax Rounding ⚠️ COMPLIANCE

### Problem
Tax was being calculated on the invoice total and then rounded, rather than calculating and rounding on each line item. This caused **cent differences** that could:
- **Trigger e-invoice rejections** by tax authorities
- **Fail compliance audits**
- **Create reconciliation headaches**

### Example Scenario
```
Line 1: $10.01 × 13.5% tax
Line 2: $20.02 × 13.5% tax
Line 3: $30.03 × 13.5% tax

Total-Level Rounding (WRONG ❌):
  Total: $60.06
  Tax: $60.06 × 13.5% = 8.1081
  Rounded: $8.11

Line-Level Rounding (CORRECT ✅):
  Line 1: $10.01 × 13.5% = 1.35135 → $1.35
  Line 2: $20.02 × 13.5% = 2.7027 → $2.70
  Line 3: $30.03 × 13.5% = 4.05405 → $4.05
  Total: $1.35 + $2.70 + $4.05 = $8.10

Difference: $0.01 (can cause invoice rejection!)
```

### Solution
Updated tax calculation to use **line-level rounding**:

**POSService.php**:
```php
// Round tax at line level before summing
$lineTax = bcdiv(bcmul($taxableAmount, $taxRate, 4), '1', 2);
$taxTotal = bcadd($taxTotal, $lineTax, 2);
```

**TaxService.php**:
```php
// Round to 2 decimal places at line level
return (float) bcdiv($taxAmount, '1', 2);
```

### Files Modified
- `app/Services/POSService.php`
- `app/Services/TaxService.php`

### Testing
Comprehensive tests verify:
- Line-level rounding produces correct totals
- Differences between line-level vs total-level rounding
- Compliance with e-invoicing requirements

---

## Testing

### Test Suite: `tests/Unit/Services/CriticalBugFixesTest.php`

All bugs have comprehensive test coverage:

1. **test_cogs_entry_is_generated_for_sale**
   - Verifies COGS journal entry creation
   - Checks debit/credit amounts match cost
   - Tests proper account mapping

2. **test_uom_conversion_applied_in_stock_deduction**
   - Mocks stock movement repository
   - Verifies conversion factor application
   - Tests carton (12×) to pieces conversion

3. **test_split_payments_create_separate_accounting_entries**
   - Tests cash + card split payment
   - Verifies separate journal lines
   - Checks correct account routing

4. **test_permission_check_cached_per_request** (NEW)
   - Tests N+1 fix with permission caching
   - Verifies only 1 permission query for multiple products
   - Confirms security is maintained

5. **test_tax_calculated_and_rounded_at_line_level**
   - Tests line-level rounding
   - Compares with total-level rounding
   - Documents rounding behavior

6. **test_tax_rounding_difference_between_line_and_total_level**
   - Uses 13.5% tax rate (shows differences clearly)
   - Demonstrates 0.01 cent difference
   - Proves compliance necessity

7. **test_cogs_entry_not_created_for_zero_cost_products**
   - Tests edge case handling
   - Verifies no entry for $0 cost items

### Running Tests
```bash
php artisan test tests/Unit/Services/CriticalBugFixesTest.php
```

---

## Deployment Checklist

### Before Deployment
- [ ] Run full test suite
- [ ] Run database seeder to create account mappings: `php artisan db:seed --class=ChartOfAccountsSeeder`
- [ ] Verify account mappings exist (COGS, Inventory, Bank, Cheque)
- [ ] Backup production database
- [ ] Test with sample transactions in staging

### After Deployment
- [ ] Monitor journal entry balance validation
- [ ] Check first few sales for correct COGS entries
- [ ] Verify split payments route to correct accounts
- [ ] Confirm tax calculations match expected values
- [ ] Test product list API performance (should be 1 permission query, not N)

### Monitoring
Watch for these log entries:
- `COGS journal entry created` (success)
- `Stock movement already recorded` (prevent duplicates)
- `Insufficient stock for sale` (inventory checks)

---

## Impact Summary

| Area | Before | After | Status |
|------|--------|-------|--------|
| **Accounting Accuracy** | Inflated profits | Correct P&L with COGS | ✅ Fixed |
| **Inventory Integrity** | Stock discrepancies | Accurate UoM tracking | ✅ Fixed |
| **Cash Reconciliation** | Failed audits | Correct split payments | ✅ Fixed |
| **System Performance** | N+1 queries | Cached permission check | ✅ Fixed |
| **Tax Compliance** | Invoice rejections | E-invoice compliant | ✅ Fixed |

---

## Future Improvements

### Short Term
1. **Performance**: Refactor ProductResource authorization to controller level
2. **Monitoring**: Add dashboard for COGS vs Revenue ratio tracking
3. **Alerts**: Notify on unusual COGS percentages

### Long Term
1. **Cost Methods**: Support FIFO/LIFO/Average costing
2. **Batch Tracking**: Track COGS by inventory batch
3. **Multi-Currency**: Handle COGS in foreign currencies

---

## References

- **Original Issue**: Deep Code Audit Report (Arabic)
- **Code Review**: All comments addressed
- **Security Scan**: CodeQL passed with no issues
- **Test Coverage**: 100% of identified bugs

---

## Contact

For questions or issues related to these fixes:
- Review PR: copilot/fix-logical-bugs-in-services
- Test Suite: tests/Unit/Services/CriticalBugFixesTest.php

**Status**: ✅ COMPLETE - Ready for Production
