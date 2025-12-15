# Module Context System

## Overview
The Module Context System provides UI-level module filtering that works alongside the existing route-based module system. It allows users to filter their workspace by specific business modules or view all modules at once.

## Architecture

### Two Complementary Systems

1. **Route-Level Module Context** (Existing)
   - Middleware: `SetModuleContext` (alias: `module`)
   - Purpose: API and route parameter-based module identification
   - Storage: Request attributes and container
   - Usage: API routes with `{moduleKey}` parameter or `X-Module-Key` header

2. **UI-Level Module Context** (New)
   - Middleware: `ModuleContext` (recommended alias: `module.ui`)
   - Service: `ModuleContextService`
   - Purpose: Session-based UI filtering and navigation
   - Storage: Session
   - Usage: View filtering, sidebar context, report filtering

### How They Work Together

The two systems are designed to be compatible and complementary:
- **Route context** determines which module's API/routes are being accessed
- **UI context** determines what the user sees in the interface
- They can be aligned or independent based on use case

The `ModuleContext` middleware is aware of `SetModuleContext` and can optionally sync with it.

## Components

### 1. ModuleContext Middleware (UI-Level)
**Location:** `app/Http/Middleware/ModuleContext.php`

- Ensures `module_context` session variable exists (defaults to 'all')
- Allows context switching via `?module_context=<module>` query parameter
- Compatible with existing `SetModuleContext` middleware
- Can detect route-level module keys and provide hints
- Valid contexts: all, inventory, pos, sales, purchases, accounting, warehouse, manufacturing, hrm, rental, fixed_assets, banking, projects, documents, helpdesk

**Registration:**
Add as middleware alias in `bootstrap/app.php`:
```php
$middleware->alias([
    'module.ui' => \App\Http\Middleware\ModuleContext::class,
    // Other aliases...
]);
```

**Usage in Routes:**
```php
// For UI routes that need context filtering
Route::middleware(['auth', 'module.ui'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports', ReportsController::class);
});

// Can be combined with existing module middleware
Route::middleware(['auth', 'module', 'module.ui'])->group(function () {
    // Routes that use both systems
});
```

### 2. ModuleContextService (Enhanced)
**Location:** `app/Services/ModuleContextService.php`

Static service for accessing and managing both UI and route-level contexts.

**Methods:**

**UI Context Methods:**
- `current()`: Get current UI context (string)
- `set(string $context)`: Set UI context
- `is(string $context)`: Check if specific UI context is active
- `isAll()`: Check if "All Modules" is active
- `getAvailableModules()`: Get all available modules with labels
- `currentLabel()`: Get label for current context

**Route Context Methods:**
- `routeKey()`: Get route-level module key (from SetModuleContext)
- `matchesRouteKey()`: Check if UI context matches route key

**Usage:**
```php
use App\Services\ModuleContextService;

// Get current UI context
$context = ModuleContextService::current();

// Get route-level module key (if using API routes)
$routeKey = ModuleContextService::routeKey();

// Check if contexts are aligned
if (ModuleContextService::matchesRouteKey()) {
    // UI and route contexts match
}

// Check UI context
if (ModuleContextService::is('inventory')) {
    // Show inventory-specific content
}

// Get label
$label = ModuleContextService::currentLabel(); // e.g., "Inventory"
```

### 3. Module Context Selector Component
**Location:** `resources/views/components/module-context-selector.blade.php`

Blade component that displays a dropdown for selecting module context.

**Usage in Blade:**
```blade
<x-module-context-selector />
```

**Features:**
- Alpine.js dropdown with smooth transitions
- Displays current context with icon
- Shows checkmark next to active context
- Clicking a module switches context via URL parameter

### 4. Integration in Layouts

Add the selector to your layout header:

```blade
{{-- In layouts/app.blade.php or navigation component --}}
<div class="flex items-center gap-4">
    <x-module-context-selector />
    
    {{-- Other header items --}}
</div>
```

## Filtering Content by Context

### In Livewire Components
```php
use App\Services\ModuleContextService;

public function render()
{
    $context = ModuleContextService::current();
    
    $query = Report::query();
    
    if (!ModuleContextService::isAll()) {
        $query->where('module', $context);
    }
    
    return view('livewire.reports.index', [
        'reports' => $query->paginate(20),
    ]);
}
```

### In Blade Views
```blade
@php
    $context = \App\Services\ModuleContextService::current();
@endphp

@if($context === 'inventory' || $context === 'all')
    {{-- Show inventory-related content --}}
@endif
```

### In Sidebar Navigation
```blade
@if(\App\Services\ModuleContextService::isAll() || \App\Services\ModuleContextService::is('inventory'))
    <x-sidebar.link route="app.inventory.products.index" label="Products" />
@endif
```

## Context-Aware Reports

Reports should filter based on module context:

```php
// In ReportsController or Livewire component
public function getReports()
{
    $context = ModuleContextService::current();
    
    $reports = collect([
        ['name' => 'Sales Report', 'module' => 'sales'],
        ['name' => 'Inventory Report', 'module' => 'inventory'],
        ['name' => 'POS Report', 'module' => 'pos'],
    ]);
    
    if (!ModuleContextService::isAll()) {
        $reports = $reports->filter(fn($r) => $r['module'] === $context);
    }
    
    return $reports;
}
```

## Persisting Context

The context is stored in the session and persists across requests. To switch context programmatically:

```php
ModuleContextService::set('inventory');
```

Or via URL:
```
/dashboard?module_context=inventory
```

## Best Practices

1. **Always provide "All Modules" option**: Users should be able to view all content
2. **Filter intelligently**: Only filter when context is not "all"
3. **Show context in page titles**: Help users understand their current view
4. **Preserve context on navigation**: Links should maintain the current context
5. **Clear visual indication**: Use the selector component prominently

## Example: Context-Aware Dashboard

```php
// app/Livewire/Dashboard/Index.php
use App\Services\ModuleContextService;

class Index extends Component
{
    public function render()
    {
        $context = ModuleContextService::current();
        $widgets = $this->getWidgetsForContext($context);
        
        return view('livewire.dashboard.index', [
            'widgets' => $widgets,
            'contextLabel' => ModuleContextService::currentLabel(),
        ]);
    }
    
    private function getWidgetsForContext(string $context): array
    {
        $allWidgets = [
            'sales' => ['total_sales', 'pending_orders'],
            'inventory' => ['stock_alerts', 'low_stock_items'],
            'hrm' => ['attendance_today', 'pending_leaves'],
        ];
        
        if ($context === 'all') {
            return collect($allWidgets)->flatten(1)->toArray();
        }
        
        return $allWidgets[$context] ?? [];
    }
}
```

## Compatibility with Existing Systems

### Integration with SetModuleContext

The new UI-level context system is fully compatible with the existing `SetModuleContext` middleware:

**Scenario 1: API Routes with Module Keys**
```php
// Route with module parameter
Route::get('/api/modules/{moduleKey}/products', ProductController::class)
    ->middleware(['api-auth', 'module']);

// SetModuleContext extracts 'moduleKey' from route parameter
// ModuleContext (if applied) maintains independent UI context
```

**Scenario 2: Combined Usage**
```php
// Both systems working together
Route::middleware(['auth', 'module', 'module.ui'])->group(function () {
    Route::get('/app/{moduleKey}/dashboard', DashboardController::class);
});

// In controller:
$routeModule = app('req.module_key'); // From SetModuleContext
$uiContext = ModuleContextService::current(); // From ModuleContext

// Check if they're aligned
if (ModuleContextService::matchesRouteKey()) {
    // User is viewing content for their selected module
}
```

**Scenario 3: UI-Only Routes**
```php
// Routes that don't need route-level module keys
Route::middleware(['auth', 'module.ui'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/reports', ReportsController::class);
});

// Only UI context is used for filtering
```

### Module Key Mapping

The systems use compatible but slightly different key formats:

| Route Key (SetModuleContext) | UI Context Key (ModuleContext) |
|------------------------------|--------------------------------|
| inventory                    | inventory                      |
| pos                          | pos                            |
| fixed-assets                 | fixed_assets                   |
| hrm                          | hrm                            |

The middleware automatically maps between these formats when needed.

### Best Practices for Compatibility

1. **Use `module` alias for API routes**: Keep using the existing `SetModuleContext` for API routes with module parameters
2. **Use `module.ui` alias for UI routes**: Apply the new context system to web UI routes
3. **Combine when needed**: Both can be used together on routes that need both levels of context
4. **Check alignment**: Use `ModuleContextService::matchesRouteKey()` to verify contexts match
5. **Independent operation**: Each system works independently - one doesn't require the other

## Migration Guide

If you have existing code that assumes full module access:

1. Add context checks: `if (ModuleContextService::isAll() || ModuleContextService::is('your_module'))`
2. Filter queries based on context
3. Update navigation to show/hide based on context
4. Test with different contexts to ensure proper filtering

## Future Enhancements

Potential improvements:
- User preferences for default context
- Context-based permissions
- Module-specific themes/branding
- Context history/breadcrumbs
- Quick context switcher keyboard shortcuts
