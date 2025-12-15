# Enhanced Settings System

## Overview
The Enhanced Settings System provides a comprehensive interface for managing system-wide and module-specific settings with support for defaults and overrides.

## Architecture

### Settings Storage
- **Config Files**: Default values in `config/settings.php`
- **Database**: Override values in `system_settings` table
- **Cache**: Settings cached for performance (TTL: 3600 seconds)

### Settings Hierarchy
1. **Config Defaults**: Defined in `config/settings.php`
2. **System Overrides**: Stored in `system_settings` table
3. **Branch Overrides**: (Future) Branch-specific settings
4. **User Overrides**: (Future) User-specific preferences

## Configuration File Structure

`config/settings.php` defines all available settings:

```php
return [
    'general' => [
        'company_name' => [
            'label' => 'Company Name',
            'type' => 'string',
            'default' => 'HugousERP',
            'required' => true,
            'description' => 'Name of your company',
        ],
        // More settings...
    ],
    'inventory' => [
        'default_costing_method' => [
            'label' => 'Default Costing Method',
            'type' => 'select',
            'options' => ['FIFO' => 'First In First Out', 'LIFO' => 'Last In First Out', 'AVG' => 'Average Cost'],
            'default' => 'FIFO',
            'description' => 'Default inventory costing method',
        ],
        // More settings...
    ],
    // More groups...
];
```

## Available Setting Groups

### 1. General Settings
- Company information (name, email, phone)
- Default language, currency, timezone
- Date/time formats
- Decimal places

### 2. Inventory Settings
- Default costing method (FIFO/LIFO/AVG)
- Stock alert threshold
- Per-product threshold option
- Default warehouse

### 3. POS Settings
- Allow negative stock
- Maximum discount percentage
- Auto-print receipt
- Cash rounding rules
- Receipt footer text

### 4. Accounting Settings
- Default chart of accounts template
- Default accounts (sales, purchases, inventory, AR, AP)

### 5. HRM Settings
- Working days per week
- Working hours per day
- Late arrival threshold (minutes)
- Basic tax rate

### 6. Rental Settings
- Grace period days
- Penalty type (percentage/fixed)
- Penalty value

### 7. Sales & Invoicing Settings
- Default payment terms (days)
- Invoice prefix
- Invoice starting number
- Default tax percentage
- Auto-email invoice option

### 8. Purchases Settings
- Require approval before receive
- Allow edit cost after receiving
- Purchase order prefix

### 9. Branch Settings
- Multi-branch mode
- Require branch selection

### 10. Security Settings
- Require 2FA
- Session timeout (minutes)
- Enable audit log

### 11. Advanced Settings
- Enable API access
- Enable webhooks
- Cache TTL

### 12. Backup Settings
- Auto backup
- Backup frequency (daily/weekly/monthly)
- Backup retention days
- Backup storage (local/s3/ftp)

### 13. Notifications Settings
- Low stock alerts
- Payment due alerts
- New order alerts

### 14. Integrations Settings
- Shopify API credentials
- WooCommerce credentials
- Payment gateway credentials (Paymob, Stripe)

## Usage

### Accessing Settings in Code

```php
use App\Models\SystemSetting;

// Get a single setting
$value = SystemSetting::where('key', 'inventory.costing_method')->value('value');

// Get setting with fallback to config
$value = SystemSetting::where('key', 'pos.max_discount_percent')->value('value') 
    ?? config('settings.pos.max_discount_percent.default', 20);

// Using helper (if implemented)
$value = setting('pos.max_discount_percent', 20);
```

### Setting Values

```php
SystemSetting::updateOrCreate(
    ['key' => 'pos.max_discount_percent'],
    [
        'value' => 25,
        'group' => 'pos',
        'is_public' => false,
    ]
);

// Clear cache after update
Cache::forget('system_settings');
Cache::forget('system_settings_all');
```

### UI Management

Settings are managed through the Unified Settings page:
- **Route**: `/admin/settings`
- **Component**: `App\Livewire\Admin\Settings\UnifiedSettings`
- **View**: `resources/views/livewire/admin/settings/unified-settings.blade.php`

## Save Methods

Each setting group has its own save method in `UnifiedSettings.php`:

- `saveGeneral()` - General settings
- `saveInventory()` - Inventory settings
- `savePos()` - POS settings
- `saveAccounting()` - Accounting settings
- `saveHrm()` - HRM settings
- `saveRental()` - Rental settings
- `saveSales()` - Sales settings
- `saveBranch()` - Branch settings
- `saveSecurity()` - Security settings
- `saveAdvanced()` - Advanced settings
- `saveBackup()` - Backup settings
- `saveNotifications()` - Notification settings

## Restore Defaults

The `restoreDefaults(string $group)` method allows resetting a group to config defaults:

```php
// In Livewire component
public function restoreDefaults(string $group): void
{
    $defaults = config("settings.{$group}", []);
    
    foreach ($defaults as $key => $config) {
        $fullKey = "{$group}.{$key}";
        SystemSetting::where('key', $fullKey)->delete();
    }
    
    Cache::forget('system_settings');
    Cache::forget('system_settings_all');
    $this->loadSettings();
    
    session()->flash('success', __('Settings restored to defaults'));
}
```

## Enable API Access Feature

The "Enable API Access" setting controls API functionality:

### Implementation

```php
// Check if API is enabled
if (!SystemSetting::where('key', 'advanced.enable_api')->value('value')) {
    abort(403, 'API access is disabled');
}
```

### API Token Management (Future Enhancement)

When API is enabled, users should be able to:
1. Generate API tokens (Laravel Sanctum)
2. View active tokens
3. Revoke tokens
4. Set token expiration

Example implementation:

```php
// Generate token
$token = auth()->user()->createToken('api-access', ['api:read', 'api:write']);

// Store in settings
SystemSetting::updateOrCreate(
    ['key' => 'api.tokens.' . auth()->id()],
    ['value' => json_encode(['token' => $token->plainTextToken, 'created_at' => now()])]
);
```

## Validation Rules

Each setting group defines validation rules:

```php
protected function rules(): array
{
    return [
        'inventory_costing_method' => 'required|in:FIFO,LIFO,AVG',
        'stock_alert_threshold' => 'required|integer|min:0',
        'pos_max_discount_percent' => 'required|integer|min:0|max:100',
        // More rules...
    ];
}
```

## Caching Strategy

Settings are cached for performance:

```php
$settings = Cache::remember('system_settings_all', 3600, function () {
    return SystemSetting::pluck('value', 'key')->toArray();
});
```

After updates, clear cache:

```php
Cache::forget('system_settings');
Cache::forget('system_settings_all');
```

## Database Schema

The `system_settings` table structure:

```php
Schema::create('system_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->string('group', 50)->index();
    $table->boolean('is_public')->default(false);
    $table->text('description')->nullable();
    $table->timestamps();
});
```

## Best Practices

1. **Always provide defaults**: Every setting should have a default in config
2. **Validate input**: Use Laravel validation rules
3. **Clear cache**: Always clear cache after updates
4. **Document settings**: Add descriptions to help users
5. **Group logically**: Organize settings by module/functionality
6. **Use appropriate types**: Select for enums, boolean for toggles, etc.

## Migration from Old Settings

If migrating from an old settings system:

```php
// Example migration
$oldSettings = OldSettings::all();

foreach ($oldSettings as $old) {
    SystemSetting::updateOrCreate(
        ['key' => $old->key],
        [
            'value' => $old->value,
            'group' => $this->detectGroup($old->key),
            'is_public' => false,
        ]
    );
}
```

## Future Enhancements

- Branch-level settings overrides
- User-level preferences
- Settings import/export (JSON)
- Settings version control/audit
- Settings templates/presets
- Multi-tenancy support
- Settings API endpoints
- Real-time settings updates (WebSockets)
