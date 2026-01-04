<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Settings;

use App\Models\Media;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class UnifiedSettings extends Component
{
    public string $activeTab = 'general';

    public array $tabs = [
        'general' => 'General Settings',
        'branding' => 'Branding',
        'inventory' => 'Inventory',
        'pos' => 'POS',
        'accounting' => 'Accounting',
        'warehouse' => 'Warehouse',
        'manufacturing' => 'Manufacturing',
        'hrm' => 'HRM & Payroll',
        'rental' => 'Rental',
        'fixed_assets' => 'Fixed Assets',
        'sales' => 'Sales & Invoicing',
        'purchases' => 'Purchases',
        'integrations' => 'Integrations & API',
        'notifications' => 'Notifications',
        'branch' => 'Branch Settings',
        'security' => 'Security',
        'backup' => 'Backup',
        'advanced' => 'Advanced',
    ];

    public array $tabDescriptions = [
        'general' => 'Company information, timezone, and regional settings',
        'branding' => 'Logo, colors, and company appearance',
        'inventory' => 'Stock management and costing method',
        'pos' => 'Point of Sale terminal settings',
        'accounting' => 'Chart of accounts and financial settings',
        'warehouse' => 'Warehouse locations and stock alerts',
        'manufacturing' => 'Production and BOM settings',
        'hrm' => 'Employee, payroll, and attendance settings',
        'rental' => 'Rental units and contracts settings',
        'fixed_assets' => 'Asset depreciation settings',
        'sales' => 'Invoice numbering and sales defaults',
        'purchases' => 'Purchase order settings',
        'integrations' => 'API keys and third-party connections',
        'notifications' => 'Email and alert preferences',
        'branch' => 'Branch-specific settings',
        'security' => 'Password policies and session settings',
        'backup' => 'Database backup settings',
        'advanced' => 'Developer and system settings',
    ];

    // General settings
    public string $company_name = '';
    public string $company_email = '';
    public string $company_phone = '';
    public string $timezone = 'UTC';
    public string $date_format = 'Y-m-d';
    public string $default_currency = 'USD';

    // Branding settings
    public ?int $branding_logo_id = null;
    public ?int $branding_favicon_id = null;
    public string $branding_logo = '';  // Legacy URL support
    public string $branding_favicon = '';  // Legacy URL support
    public string $branding_primary_color = '#10b981';
    public string $branding_secondary_color = '#3b82f6';
    public string $branding_tagline = '';

    // Inventory settings
    public string $inventory_costing_method = 'FIFO';
    public int $stock_alert_threshold = 10;
    public bool $use_per_product_threshold = true;

    // POS settings
    public bool $pos_allow_negative_stock = false;
    public int $pos_max_discount_percent = 20;
    public bool $pos_auto_print_receipt = true;
    public string $pos_rounding_rule = 'none';

    // Accounting settings
    public string $accounting_coa_template = 'standard';

    // HRM settings
    public int $hrm_working_days_per_week = 5;
    public float $hrm_working_hours_per_day = 8.0;
    public int $hrm_late_arrival_threshold = 15;
    public string $hrm_transport_allowance_type = 'percentage';
    public float $hrm_transport_allowance_value = 10.0;
    public string $hrm_housing_allowance_type = 'percentage';
    public float $hrm_housing_allowance_value = 0.0;
    public float $hrm_meal_allowance = 0.0;
    public float $hrm_health_insurance_deduction = 0.0;

    // Rental settings
    public int $rental_grace_period_days = 5;
    public string $rental_penalty_type = 'percentage';
    public float $rental_penalty_value = 5.0;

    // Sales settings
    public int $sales_payment_terms_days = 30;
    public string $sales_invoice_prefix = 'INV-';
    public int $sales_invoice_starting_number = 1000;

    // Branch settings
    public bool $multi_branch = false;
    public bool $require_branch_selection = true;

    // Security settings
    public bool $require_2fa = false;
    public int $session_timeout = 120;
    public bool $enable_audit_log = true;

    // Advanced settings
    public bool $enable_api = true;
    public bool $enable_webhooks = false;
    public int $cache_ttl = 3600;

    // Backup settings
    public bool $auto_backup = false;
    public string $backup_frequency = 'daily';
    public int $backup_retention_days = 30;
    public string $backup_storage = 'local';

    // Notifications
    public bool $notifications_low_stock = true;
    public bool $notifications_payment_due = true;
    public bool $notifications_new_order = true;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('settings.view')) {
            abort(403);
        }

        // Get tab from query string (supports both ?tab= and hash via JS)
        $this->activeTab = request()->query('tab', 'general');
        
        // Validate the tab exists
        if (!array_key_exists($this->activeTab, $this->tabs)) {
            $this->activeTab = 'general';
        }
        
        $this->loadSettings();
    }

    protected function loadSettings(): void
    {
        // Bulk load all settings for performance
        $settings = Cache::remember('system_settings_all', 3600, function () {
            return SystemSetting::pluck('value', 'key')->toArray();
        });

        // Load general settings
        $this->company_name = $settings['company.name'] ?? config('app.name', 'HugouERP');
        $this->company_email = $settings['company.email'] ?? '';
        $this->company_phone = $settings['company.phone'] ?? '';
        $this->timezone = $settings['app.timezone'] ?? config('app.timezone', 'UTC');
        $this->date_format = $settings['app.date_format'] ?? 'Y-m-d';
        $this->default_currency = $settings['general.default_currency'] ?? 'USD';

        // Load branding settings
        $this->branding_logo_id = isset($settings['branding.logo_id']) ? (int) $settings['branding.logo_id'] : null;
        $this->branding_favicon_id = isset($settings['branding.favicon_id']) ? (int) $settings['branding.favicon_id'] : null;
        $this->branding_logo = $settings['branding.logo'] ?? '';  // Legacy support
        $this->branding_favicon = $settings['branding.favicon'] ?? '';  // Legacy support
        $this->branding_primary_color = $settings['branding.primary_color'] ?? '#10b981';
        $this->branding_secondary_color = $settings['branding.secondary_color'] ?? '#3b82f6';
        $this->branding_tagline = $settings['branding.tagline'] ?? '';

        // Load inventory settings
        $this->inventory_costing_method = $settings['inventory.costing_method'] ?? 'FIFO';
        $this->stock_alert_threshold = (int) ($settings['inventory.stock_alert_threshold'] ?? 10);
        $this->use_per_product_threshold = (bool) ($settings['inventory.use_per_product_threshold'] ?? true);

        // Load POS settings
        $this->pos_allow_negative_stock = (bool) ($settings['pos.allow_negative_stock'] ?? false);
        $this->pos_max_discount_percent = (int) ($settings['pos.max_discount_percent'] ?? 20);
        $this->pos_auto_print_receipt = (bool) ($settings['pos.auto_print_receipt'] ?? true);
        $this->pos_rounding_rule = $settings['pos.rounding_rule'] ?? 'none';

        // Load accounting settings
        $this->accounting_coa_template = $settings['accounting.coa_template'] ?? 'standard';

        // Load HRM settings
        $this->hrm_working_days_per_week = (int) ($settings['hrm.working_days_per_week'] ?? 5);
        $this->hrm_working_hours_per_day = (float) ($settings['hrm.working_hours_per_day'] ?? 8.0);
        $this->hrm_late_arrival_threshold = (int) ($settings['hrm.late_arrival_threshold'] ?? 15);
        $this->hrm_transport_allowance_type = $settings['hrm.transport_allowance_type'] ?? 'percentage';
        $this->hrm_transport_allowance_value = (float) ($settings['hrm.transport_allowance_value'] ?? 10.0);
        $this->hrm_housing_allowance_type = $settings['hrm.housing_allowance_type'] ?? 'percentage';
        $this->hrm_housing_allowance_value = (float) ($settings['hrm.housing_allowance_value'] ?? 0.0);
        $this->hrm_meal_allowance = (float) ($settings['hrm.meal_allowance'] ?? 0.0);
        $this->hrm_health_insurance_deduction = (float) ($settings['hrm.health_insurance_deduction'] ?? 0.0);

        // Load rental settings
        $this->rental_grace_period_days = (int) ($settings['rental.grace_period_days'] ?? 5);
        $this->rental_penalty_type = $settings['rental.penalty_type'] ?? 'percentage';
        $this->rental_penalty_value = (float) ($settings['rental.penalty_value'] ?? 5.0);

        // Load sales settings
        $this->sales_payment_terms_days = (int) ($settings['sales.payment_terms_days'] ?? 30);
        $this->sales_invoice_prefix = $settings['sales.invoice_prefix'] ?? 'INV-';
        $this->sales_invoice_starting_number = (int) ($settings['sales.invoice_starting_number'] ?? 1000);

        // Load branch settings
        $this->multi_branch = (bool) ($settings['system.multi_branch'] ?? false);
        $this->require_branch_selection = (bool) ($settings['system.require_branch_selection'] ?? true);

        // Load security settings (supporting legacy key for backward compatibility)
        $this->require_2fa = (bool) ($settings['security.2fa_required']
            ?? $settings['security.require_2fa']
            ?? false);
        $this->session_timeout = (int) ($settings['security.session_timeout'] ?? 120);
        $this->enable_audit_log = (bool) ($settings['security.enable_audit_log'] ?? true);

        // Load advanced settings
        $this->enable_api = (bool) ($settings['advanced.enable_api'] ?? true);
        $this->enable_webhooks = (bool) ($settings['advanced.enable_webhooks'] ?? false);
        $this->cache_ttl = (int) ($settings['advanced.cache_ttl'] ?? 3600);

        // Load backup settings
        $this->auto_backup = (bool) ($settings['backup.auto_backup'] ?? false);
        $this->backup_frequency = $settings['backup.frequency'] ?? 'daily';
        $this->backup_retention_days = (int) ($settings['backup.retention_days'] ?? 30);
        $this->backup_storage = $settings['backup.storage'] ?? 'local';

        // Load notification settings
        $this->notifications_low_stock = (bool) ($settings['notifications.low_stock'] ?? true);
        $this->notifications_payment_due = (bool) ($settings['notifications.payment_due'] ?? true);
        $this->notifications_new_order = (bool) ($settings['notifications.new_order'] ?? true);
    }

    protected function getSetting(string $key, $default = null)
    {
        $settings = Cache::remember('system_settings_all', 3600, function () {
            return SystemSetting::pluck('value', 'key')->toArray();
        });
        return $settings[$key] ?? $default;
    }

    protected function setSetting(string $key, $value, string $group = 'general'): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'is_public' => false,
            ]
        );
    }

    public function switchTab(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs)) {
            $this->activeTab = $tab;
            // Update URL with the new tab (dispatched to JS)
            $this->dispatch('tab-changed', tab: $tab);
        }
    }

    protected function redirectToTab(?string $tab = null): mixed
    {
        $tab ??= $this->activeTab;

        return $this->redirectRoute('admin.settings', ['tab' => $tab], navigate: true);
    }

    public function saveGeneral(): mixed
    {
        $this->validate([
            'company_name' => 'required|string|max:255',
            'company_email' => 'nullable|email|max:255',
            'company_phone' => 'nullable|string|max:50',
            'timezone' => 'required|string',
            'date_format' => 'required|string',
            'default_currency' => 'required|string|size:3',
        ]);

        $this->setSetting('company.name', $this->company_name, 'general');
        $this->setSetting('company.email', $this->company_email, 'general');
        $this->setSetting('company.phone', $this->company_phone, 'general');
        $this->setSetting('app.timezone', $this->timezone, 'general');
        $this->setSetting('app.date_format', $this->date_format, 'general');
        $this->setSetting('general.default_currency', $this->default_currency, 'general');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('General settings saved successfully'));

        return $this->redirectToTab('general');
    }

    #[On('media-selected')]
    public function handleMediaSelected(string $fieldId, int $mediaId, array $media): void
    {
        if ($fieldId === 'branding-logo') {
            $this->branding_logo_id = $mediaId;
            $this->branding_logo = $media['url'] ?? '';
        } elseif ($fieldId === 'branding-favicon') {
            $this->branding_favicon_id = $mediaId;
            $this->branding_favicon = $media['url'] ?? '';
        }
    }

    #[On('media-cleared')]
    public function handleMediaCleared(string $fieldId): void
    {
        if ($fieldId === 'branding-logo') {
            $this->branding_logo_id = null;
            $this->branding_logo = '';
        } elseif ($fieldId === 'branding-favicon') {
            $this->branding_favicon_id = null;
            $this->branding_favicon = '';
        }
    }

    public function saveBranding(): mixed
    {
        $this->validate([
            'branding_primary_color' => 'required|string|max:7',
            'branding_secondary_color' => 'required|string|max:7',
            'branding_tagline' => 'nullable|string|max:255',
        ]);

        // Save media IDs (preferred) and also URLs for backward compatibility
        $this->setSetting('branding.logo_id', $this->branding_logo_id, 'branding');
        $this->setSetting('branding.favicon_id', $this->branding_favicon_id, 'branding');
        
        // Get URLs from media if IDs are set, otherwise use the legacy URL values
        $logoUrl = $this->branding_logo;
        $faviconUrl = $this->branding_favicon;
        
        if ($this->branding_logo_id) {
            $logoMedia = Media::find($this->branding_logo_id);
            $logoUrl = $logoMedia?->url ?? $this->branding_logo;
        }
        
        if ($this->branding_favicon_id) {
            $faviconMedia = Media::find($this->branding_favicon_id);
            $faviconUrl = $faviconMedia?->url ?? $this->branding_favicon;
        }
        
        $this->setSetting('branding.logo', $logoUrl, 'branding');
        $this->setSetting('branding.favicon', $faviconUrl, 'branding');
        $this->setSetting('branding.primary_color', $this->branding_primary_color, 'branding');
        $this->setSetting('branding.secondary_color', $this->branding_secondary_color, 'branding');
        $this->setSetting('branding.tagline', $this->branding_tagline, 'branding');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Branding settings saved successfully'));

        return $this->redirectToTab('branding');
    }

    public function saveBranch(): mixed
    {
        $this->setSetting('system.multi_branch', $this->multi_branch, 'branch');
        $this->setSetting('system.require_branch_selection', $this->require_branch_selection, 'branch');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Branch settings saved successfully'));

        return $this->redirectToTab('branch');
    }

    public function saveSecurity(): mixed
    {
        $this->validate([
            'session_timeout' => 'required|integer|min:5|max:1440',
        ]);

        // Normalize to the current key and remove the legacy one to avoid drift
        SystemSetting::where('key', 'security.require_2fa')->delete();
        $this->setSetting('security.2fa_required', $this->require_2fa, 'security');
        $this->setSetting('security.session_timeout', $this->session_timeout, 'security');
        $this->setSetting('security.enable_audit_log', $this->enable_audit_log, 'security');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Security settings saved successfully'));

        return $this->redirectToTab('security');
    }

    public function saveAdvanced(): mixed
    {
        $this->validate([
            'cache_ttl' => 'required|integer|min:60|max:86400',
        ]);

        $this->setSetting('advanced.enable_api', $this->enable_api, 'advanced');
        $this->setSetting('advanced.enable_webhooks', $this->enable_webhooks, 'advanced');
        $this->setSetting('advanced.cache_ttl', $this->cache_ttl, 'advanced');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        Cache::forget('api_enabled_setting'); // Clear API enabled cache for middleware
        session()->flash('success', __('Advanced settings saved successfully'));

        return $this->redirectToTab('advanced');
    }

    public function saveBackup(): mixed
    {
        $this->validate([
            'backup_retention_days' => 'required|integer|min:1|max:365',
            'backup_frequency' => 'required|in:daily,weekly,monthly',
            'backup_storage' => 'required|in:local,s3,ftp',
        ]);

        $this->setSetting('backup.auto_backup', $this->auto_backup, 'backup');
        $this->setSetting('backup.frequency', $this->backup_frequency, 'backup');
        $this->setSetting('backup.retention_days', $this->backup_retention_days, 'backup');
        $this->setSetting('backup.storage', $this->backup_storage, 'backup');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Backup settings saved successfully'));

        return $this->redirectToTab('backup');
    }

    public function saveInventory(): mixed
    {
        $this->validate([
            'inventory_costing_method' => 'required|in:FIFO,LIFO,AVG',
            'stock_alert_threshold' => 'required|integer|min:0',
        ]);

        $this->setSetting('inventory.costing_method', $this->inventory_costing_method, 'inventory');
        $this->setSetting('inventory.stock_alert_threshold', $this->stock_alert_threshold, 'inventory');
        $this->setSetting('inventory.use_per_product_threshold', $this->use_per_product_threshold, 'inventory');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Inventory settings saved successfully'));

        return $this->redirectToTab('inventory');
    }

    public function savePos(): mixed
    {
        $this->validate([
            'pos_max_discount_percent' => 'required|integer|min:0|max:100',
            'pos_rounding_rule' => 'required|in:none,0.05,0.10,0.25,0.50,1.00',
        ]);

        $this->setSetting('pos.allow_negative_stock', $this->pos_allow_negative_stock, 'pos');
        $this->setSetting('pos.max_discount_percent', $this->pos_max_discount_percent, 'pos');
        $this->setSetting('pos.auto_print_receipt', $this->pos_auto_print_receipt, 'pos');
        $this->setSetting('pos.rounding_rule', $this->pos_rounding_rule, 'pos');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('POS settings saved successfully'));

        return $this->redirectToTab('pos');
    }

    public function saveAccounting(): mixed
    {
        $this->validate([
            'accounting_coa_template' => 'required|in:standard,retail,service',
        ]);

        $this->setSetting('accounting.coa_template', $this->accounting_coa_template, 'accounting');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Accounting settings saved successfully'));

        return $this->redirectToTab('accounting');
    }

    public function saveHrm(): mixed
    {
        $this->validate([
            'hrm_working_days_per_week' => 'required|integer|min:1|max:7',
            'hrm_working_hours_per_day' => 'required|numeric|min:1|max:24',
            'hrm_late_arrival_threshold' => 'required|integer|min:0',
            'hrm_transport_allowance_type' => 'required|in:percentage,fixed',
            'hrm_transport_allowance_value' => 'required|numeric|min:0',
            'hrm_housing_allowance_type' => 'required|in:percentage,fixed',
            'hrm_housing_allowance_value' => 'required|numeric|min:0',
            'hrm_meal_allowance' => 'required|numeric|min:0',
            'hrm_health_insurance_deduction' => 'required|numeric|min:0',
        ]);

        $this->setSetting('hrm.working_days_per_week', $this->hrm_working_days_per_week, 'hrm');
        $this->setSetting('hrm.working_hours_per_day', $this->hrm_working_hours_per_day, 'hrm');
        $this->setSetting('hrm.late_arrival_threshold', $this->hrm_late_arrival_threshold, 'hrm');
        $this->setSetting('hrm.transport_allowance_type', $this->hrm_transport_allowance_type, 'hrm');
        $this->setSetting('hrm.transport_allowance_value', $this->hrm_transport_allowance_value, 'hrm');
        $this->setSetting('hrm.housing_allowance_type', $this->hrm_housing_allowance_type, 'hrm');
        $this->setSetting('hrm.housing_allowance_value', $this->hrm_housing_allowance_value, 'hrm');
        $this->setSetting('hrm.meal_allowance', $this->hrm_meal_allowance, 'hrm');
        $this->setSetting('hrm.health_insurance_deduction', $this->hrm_health_insurance_deduction, 'hrm');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('HRM settings saved successfully'));

        return $this->redirectToTab('hrm');
    }

    public function saveRental(): mixed
    {
        $this->validate([
            'rental_grace_period_days' => 'required|integer|min:0',
            'rental_penalty_type' => 'required|in:percentage,fixed',
            'rental_penalty_value' => 'required|numeric|min:0',
        ]);

        $this->setSetting('rental.grace_period_days', $this->rental_grace_period_days, 'rental');
        $this->setSetting('rental.penalty_type', $this->rental_penalty_type, 'rental');
        $this->setSetting('rental.penalty_value', $this->rental_penalty_value, 'rental');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Rental settings saved successfully'));

        return $this->redirectToTab('rental');
    }

    public function saveSales(): mixed
    {
        $this->validate([
            'sales_payment_terms_days' => 'required|integer|min:0',
            'sales_invoice_prefix' => 'required|string|max:10',
            'sales_invoice_starting_number' => 'required|integer|min:1',
        ]);

        $this->setSetting('sales.payment_terms_days', $this->sales_payment_terms_days, 'sales');
        $this->setSetting('sales.invoice_prefix', $this->sales_invoice_prefix, 'sales');
        $this->setSetting('sales.invoice_starting_number', $this->sales_invoice_starting_number, 'sales');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Sales settings saved successfully'));

        return $this->redirectToTab('sales');
    }

    public function saveNotifications(): mixed
    {
        $this->setSetting('notifications.low_stock', $this->notifications_low_stock, 'notifications');
        $this->setSetting('notifications.payment_due', $this->notifications_payment_due, 'notifications');
        $this->setSetting('notifications.new_order', $this->notifications_new_order, 'notifications');

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        session()->flash('success', __('Notification settings saved successfully'));

        return $this->redirectToTab('notifications');
    }

    public function restoreDefaults(string $group): mixed
    {
        // Load defaults from config
        $defaults = config("settings.{$group}", []);
        
        foreach ($defaults as $key => $config) {
            $defaultValue = $config['default'] ?? null;
            $fullKey = "{$group}.{$key}";
            
            SystemSetting::where('key', $fullKey)->delete();
        }

        Cache::forget('system_settings');
        Cache::forget('system_settings_all');
        $this->loadSettings();
        
        session()->flash('success', __('Settings restored to defaults for :group', ['group' => $group]));

        return $this->redirectToTab($this->activeTab);
    }

    public function render()
    {
        $currencies = \App\Models\Currency::active()->ordered()->get(['code', 'name', 'symbol']);
        
        return view('livewire.admin.settings.unified-settings', [
            'currencies' => $currencies,
        ]);
    }
}
