# Scope Methods Performance Optimization Guide

## Overview
This document outlines performance optimization opportunities for scope methods across the HugousERP models.

## 1. Database Indexes

### Recommended Indexes for Scope Methods

#### Account Model
```sql
-- For scopeActive()
CREATE INDEX idx_accounts_is_active ON accounts(is_active) WHERE is_active = true;

-- For scopeType()
CREATE INDEX idx_accounts_type ON accounts(type);

-- For scopeCategory()
CREATE INDEX idx_accounts_category ON accounts(account_category);

-- Composite index for common queries
CREATE INDEX idx_accounts_type_active ON accounts(type, is_active);
```

#### Product Model
```sql
-- For scopeLowStock()
CREATE INDEX idx_products_stock_alert ON products(stock_quantity, stock_alert_threshold) 
WHERE stock_alert_threshold IS NOT NULL;

-- For scopeOutOfStock()
CREATE INDEX idx_products_out_of_stock ON products(stock_quantity) WHERE stock_quantity <= 0;

-- For scopeInStock()
CREATE INDEX idx_products_in_stock ON products(stock_quantity) WHERE stock_quantity > 0;

-- For scopeExpiringSoon()
CREATE INDEX idx_products_expiring ON products(is_perishable, expiry_date) 
WHERE is_perishable = true AND expiry_date IS NOT NULL;

-- For scopeActive()
CREATE INDEX idx_products_active ON products(is_active) WHERE is_active = true;

-- Composite index for common product queries
CREATE INDEX idx_products_active_stock ON products(is_active, stock_quantity);
```

#### Branch-Aware Models
```sql
-- For models extending BaseModel with branch_id
CREATE INDEX idx_{table}_branch_id ON {table}(branch_id);

-- Composite indexes for branch + status queries
CREATE INDEX idx_{table}_branch_active ON {table}(branch_id, is_active);
```

## 2. Query Optimization

### N+1 Query Prevention

When using scope methods, always consider eager loading relationships:

```php
// ❌ Bad - N+1 queries
$accounts = Account::active()->get();
foreach ($accounts as $account) {
    echo $account->branch->name; // N+1 query
}

// ✅ Good - Eager loading
$accounts = Account::active()->with('branch')->get();
foreach ($accounts as $account) {
    echo $account->branch->name; // No additional query
}
```

### Selective Column Loading

Avoid loading all columns when you only need a few:

```php
// ❌ Bad - Loads all columns
$products = Product::lowStock()->get();

// ✅ Good - Only loads needed columns
$products = Product::lowStock()
    ->select('id', 'name', 'stock_quantity', 'stock_alert_threshold')
    ->get();
```

## 3. Query Caching

### Implementing Cache for Expensive Queries

```php
use Illuminate\Support\Facades\Cache;

class Product extends BaseModel
{
    /**
     * Get low stock products with caching
     */
    public static function getLowStockCached(int $ttl = 300): Collection
    {
        return Cache::remember('products.low_stock', $ttl, function () {
            return static::lowStock()
                ->select('id', 'name', 'stock_quantity', 'stock_alert_threshold')
                ->get();
        });
    }
    
    /**
     * Get out of stock count with caching
     */
    public static function getOutOfStockCountCached(int $ttl = 300): int
    {
        return Cache::remember('products.out_of_stock.count', $ttl, function () {
            return static::outOfStock()->count();
        });
    }
}
```

### Cache Invalidation

```php
// In Product model observers or events
protected static function booted(): void
{
    static::updated(function ($product) {
        if ($product->wasChanged('stock_quantity')) {
            Cache::forget('products.low_stock');
            Cache::forget('products.out_of_stock.count');
        }
    });
}
```

## 4. Pagination for Large Result Sets

Always paginate when dealing with potentially large datasets:

```php
// ❌ Bad - Loads all results into memory
$products = Product::active()->get();

// ✅ Good - Paginated results
$products = Product::active()->paginate(50);

// ✅ Better - Cursor pagination for better performance
$products = Product::active()->cursorPaginate(50);
```

## 5. Raw Queries for Complex Scopes

For very complex scope methods, consider using raw queries with indexes:

```php
public function scopeComplexInventoryReport(Builder $query): Builder
{
    return $query->selectRaw('
        products.*,
        COALESCE(stock_quantity, 0) as current_stock,
        COALESCE(stock_alert_threshold, 0) as alert_threshold,
        CASE 
            WHEN stock_quantity <= 0 THEN "out_of_stock"
            WHEN stock_quantity <= stock_alert_threshold THEN "low_stock"
            ELSE "in_stock"
        END as stock_status
    ')->where('is_active', true);
}
```

## 6. Query Result Chunking

For processing large datasets, use chunking to reduce memory usage:

```php
// Process large result sets in chunks
Product::active()->chunk(1000, function ($products) {
    foreach ($products as $product) {
        // Process each product
    }
});

// Or use lazy collections for streaming
Product::active()->lazy()->each(function ($product) {
    // Process each product with minimal memory footprint
});
```

## 7. Monitoring and Profiling

### Laravel Debugbar
Install and use Laravel Debugbar to monitor query performance:

```bash
composer require barryvdh/laravel-debugbar --dev
```

### Query Logging
Enable query logging to identify slow queries:

```php
DB::listen(function ($query) {
    if ($query->time > 1000) { // Queries taking more than 1 second
        Log::warning('Slow query detected', [
            'sql' => $query->sql,
            'time' => $query->time,
            'bindings' => $query->bindings,
        ]);
    }
});
```

## 8. Recommended Indexes Migration

Create a migration to add the recommended indexes:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account indexes
        Schema::table('accounts', function (Blueprint $table) {
            $table->index('is_active');
            $table->index('type');
            $table->index('account_category');
            $table->index(['type', 'is_active']);
        });

        // Product indexes
        Schema::table('products', function (Blueprint $table) {
            $table->index(['stock_quantity', 'stock_alert_threshold']);
            $table->index('stock_quantity');
            $table->index(['is_perishable', 'expiry_date']);
            $table->index(['is_active', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['type']);
            $table->dropIndex(['account_category']);
            $table->dropIndex(['type', 'is_active']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['stock_quantity', 'stock_alert_threshold']);
            $table->dropIndex(['stock_quantity']);
            $table->dropIndex(['is_perishable', 'expiry_date']);
            $table->dropIndex(['is_active', 'stock_quantity']);
        });
    }
};
```

## 9. Best Practices Summary

1. **Always add indexes** for columns used in WHERE clauses of scope methods
2. **Use eager loading** to prevent N+1 queries
3. **Implement caching** for frequently accessed, slowly changing data
4. **Paginate results** instead of loading all records
5. **Use chunking/lazy loading** for batch processing
6. **Monitor query performance** with debugging tools
7. **Select only needed columns** to reduce data transfer
8. **Use composite indexes** for queries filtering on multiple columns
9. **Consider materialized views** for complex aggregations
10. **Regular ANALYZE** tables to keep query optimizer statistics up to date

## Implementation Priority

1. **High Priority** - Add indexes for scope methods used in dashboards and list views
2. **Medium Priority** - Implement caching for dashboard widgets and statistics
3. **Low Priority** - Optimize rarely-used scope methods

## Monitoring Metrics

Track these metrics to measure optimization impact:
- Average query execution time
- Peak memory usage
- Number of queries per request
- Cache hit ratio
- Page load times for key screens

---

**Note**: Always test performance changes in a staging environment before deploying to production.
