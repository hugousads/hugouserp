<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Customizable Dashboard with Drag-and-Drop Widget System
 * 
 * Features:
 * - Reorderable widgets via drag-and-drop
 * - Show/hide widgets per user preference
 * - Multiple layout options
 * - Saved user preferences
 */
class CustomizableDashboard extends Component
{
    #[Layout('layouts.app')]
    
    // Dashboard configuration
    public array $widgets = [];
    public array $widgetOrder = [];
    public array $hiddenWidgets = [];
    public string $layoutMode = 'default'; // default, compact, expanded
    
    // Data
    public array $stats = [];
    public array $salesChartData = [];
    public array $inventoryChartData = [];
    public array $paymentMethodsData = [];
    public array $lowStockProducts = [];
    public array $recentSales = [];
    public array $trendIndicators = [];
    
    // Branch context
    public ?int $branchId = null;
    public bool $isAdmin = false;
    public bool $isEditing = false;
    
    protected int $cacheTtl = 300;

    /**
     * Available widgets configuration
     */
    protected array $availableWidgets = [
        'quick_actions' => [
            'title' => 'Quick Actions',
            'icon' => 'zap',
            'size' => 'full',
            'default_enabled' => true,
            'permission' => null,
        ],
        'stats_cards' => [
            'title' => 'Stats Overview',
            'icon' => 'bar-chart-2',
            'size' => 'full',
            'default_enabled' => true,
            'permission' => 'dashboard.view',
        ],
        'performance' => [
            'title' => 'Performance Insights',
            'icon' => 'trending-up',
            'size' => 'full',
            'default_enabled' => true,
            'permission' => 'dashboard.view',
        ],
        'sales_chart' => [
            'title' => 'Sales Trend',
            'icon' => 'line-chart',
            'size' => 'large',
            'default_enabled' => true,
            'permission' => 'sales.view',
        ],
        'inventory_chart' => [
            'title' => 'Inventory Status',
            'icon' => 'pie-chart',
            'size' => 'medium',
            'default_enabled' => true,
            'permission' => 'inventory.products.view',
        ],
        'payment_mix' => [
            'title' => 'Payment Methods',
            'icon' => 'credit-card',
            'size' => 'medium',
            'default_enabled' => true,
            'permission' => 'sales.view',
        ],
        'low_stock' => [
            'title' => 'Low Stock Alerts',
            'icon' => 'alert-triangle',
            'size' => 'half',
            'default_enabled' => true,
            'permission' => 'inventory.products.view',
        ],
        'recent_sales' => [
            'title' => 'Recent Sales',
            'icon' => 'shopping-cart',
            'size' => 'half',
            'default_enabled' => true,
            'permission' => 'sales.view',
        ],
        'quick_stats' => [
            'title' => 'Quick Stats',
            'icon' => 'activity',
            'size' => 'full',
            'default_enabled' => true,
            'permission' => 'dashboard.view',
        ],
    ];

    public function mount(): void
    {
        $user = Auth::user();
        if (!$user || !$user->can('dashboard.view')) {
            abort(403);
        }

        $this->branchId = session('admin_branch_context', $user->branch_id);
        $this->isAdmin = $user->hasRole('super-admin') || $user->hasRole('admin');
        $this->cacheTtl = (int) (SystemSetting::where('key', 'advanced.cache_ttl')->value('value') ?? 300);

        // Load user's dashboard preferences
        $this->loadUserPreferences();
        
        // Load all data
        $this->loadAllData();
    }

    /**
     * Load user's dashboard preferences
     */
    protected function loadUserPreferences(): void
    {
        $user = Auth::user();
        $preferences = $user->preferences ?? [];
        
        // Get saved widget order or use defaults
        $this->widgetOrder = $preferences['dashboard_widget_order'] ?? array_keys($this->availableWidgets);
        $this->hiddenWidgets = $preferences['dashboard_hidden_widgets'] ?? [];
        $this->layoutMode = $preferences['dashboard_layout_mode'] ?? 'default';
        
        // Build widgets array with visibility
        $this->widgets = [];
        foreach ($this->widgetOrder as $widgetKey) {
            if (isset($this->availableWidgets[$widgetKey])) {
                $widget = $this->availableWidgets[$widgetKey];
                $widget['key'] = $widgetKey;
                $widget['visible'] = !in_array($widgetKey, $this->hiddenWidgets);
                
                // Check permission
                if ($widget['permission'] && !Auth::user()->can($widget['permission'])) {
                    continue; // Skip widgets user doesn't have permission for
                }
                
                $this->widgets[] = $widget;
            }
        }
        
        // Add any new widgets not in saved order
        foreach ($this->availableWidgets as $key => $widget) {
            if (!in_array($key, $this->widgetOrder)) {
                if ($widget['permission'] && !Auth::user()->can($widget['permission'])) {
                    continue;
                }
                $widget['key'] = $key;
                $widget['visible'] = $widget['default_enabled'];
                $this->widgets[] = $widget;
            }
        }
    }

    /**
     * Toggle edit mode for dashboard customization
     */
    public function toggleEditMode(): void
    {
        $this->isEditing = !$this->isEditing;
    }

    /**
     * Update widget order (called from drag-drop JS)
     */
    public function updateWidgetOrder(array $order): void
    {
        $this->widgetOrder = $order;
        $this->saveUserPreferences();
        $this->loadUserPreferences();
    }

    /**
     * Toggle widget visibility
     */
    public function toggleWidget(string $widgetKey): void
    {
        if (in_array($widgetKey, $this->hiddenWidgets)) {
            $this->hiddenWidgets = array_values(array_diff($this->hiddenWidgets, [$widgetKey]));
        } else {
            $this->hiddenWidgets[] = $widgetKey;
        }
        
        $this->saveUserPreferences();
        $this->loadUserPreferences();
    }

    /**
     * Change layout mode
     */
    public function setLayoutMode(string $mode): void
    {
        if (in_array($mode, ['default', 'compact', 'expanded'])) {
            $this->layoutMode = $mode;
            $this->saveUserPreferences();
        }
    }

    /**
     * Reset dashboard to defaults
     */
    public function resetDashboard(): void
    {
        $this->widgetOrder = array_keys($this->availableWidgets);
        $this->hiddenWidgets = [];
        $this->layoutMode = 'default';
        $this->saveUserPreferences();
        $this->loadUserPreferences();
    }

    /**
     * Save user preferences
     */
    protected function saveUserPreferences(): void
    {
        $user = Auth::user();
        $preferences = $user->preferences ?? [];
        
        $preferences['dashboard_widget_order'] = $this->widgetOrder;
        $preferences['dashboard_hidden_widgets'] = $this->hiddenWidgets;
        $preferences['dashboard_layout_mode'] = $this->layoutMode;
        
        $user->preferences = $preferences;
        $user->save();
    }

    /**
     * Load all dashboard data
     */
    protected function loadAllData(): void
    {
        $this->loadStats();
        $this->loadChartData();
        $this->loadLowStockProducts();
        $this->loadRecentSales();
        $this->loadTrendIndicators();
    }

    /**
     * Refresh data (clear cache and reload)
     */
    public function refreshData(): void
    {
        $cacheKey = $this->getCachePrefix();
        Cache::forget("{$cacheKey}:stats");
        Cache::forget("{$cacheKey}:chart_data");
        Cache::forget("{$cacheKey}:low_stock");
        Cache::forget("{$cacheKey}:recent_sales");
        Cache::forget("{$cacheKey}:trends");

        $this->loadAllData();
    }

    protected function getCachePrefix(): string
    {
        return "dashboard:branch_{$this->branchId}:admin_{$this->isAdmin}";
    }

    protected function scopeSalesQuery($query)
    {
        if (!$this->isAdmin && $this->branchId) {
            return $query->where('branch_id', $this->branchId);
        }
        return $query;
    }

    protected function scopeProductsQuery($query)
    {
        if (!$this->isAdmin && $this->branchId) {
            return $query->where('branch_id', $this->branchId);
        }
        return $query;
    }

    /**
     * Calculate inventory statistics using a single query for better performance
     */
    protected function calculateInventoryStats(string $stockExpr, ?int $branchFilter): array
    {
        // Use a single query with CASE expressions for better performance
        $result = DB::table('products')
            ->whereNull('deleted_at')
            ->when($branchFilter, fn ($q) => $q->where('branch_id', $branchFilter))
            ->selectRaw("
                SUM(CASE WHEN ({$stockExpr}) > COALESCE(min_stock, 0) THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN min_stock IS NOT NULL AND min_stock > 0 AND ({$stockExpr}) <= min_stock AND ({$stockExpr}) > 0 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN ({$stockExpr}) <= 0 THEN 1 ELSE 0 END) as out_of_stock
            ")
            ->first();

        return [
            'labels' => [__('In Stock'), __('Low Stock'), __('Out of Stock')],
            'data' => [
                (int) ($result->in_stock ?? 0),
                (int) ($result->low_stock ?? 0),
                (int) ($result->out_of_stock ?? 0),
            ],
        ];
    }

    protected function loadStats(): void
    {
        $cacheKey = "{$this->getCachePrefix()}:stats";

        $this->stats = Cache::remember($cacheKey, $this->cacheTtl, function () {
            $today = now()->startOfDay();
            $startOfMonth = now()->startOfMonth();

            $salesQuery = $this->scopeSalesQuery(Sale::query());
            $productsQuery = $this->scopeProductsQuery(Product::query());

            return [
                'today_sales' => number_format((clone $salesQuery)->whereDate('created_at', $today)->sum('grand_total') ?? 0, 2),
                'month_sales' => number_format((clone $salesQuery)->where('created_at', '>=', $startOfMonth)->sum('grand_total') ?? 0, 2),
                'open_invoices' => (clone $salesQuery)->where('status', 'pending')->count(),
                'active_branches' => $this->isAdmin ? Branch::where('is_active', true)->count() : 1,
                'active_users' => $this->isAdmin 
                    ? User::where('is_active', true)->count() 
                    : User::where('is_active', true)->where('branch_id', $this->branchId)->count(),
                'total_products' => (clone $productsQuery)->count(),
                'low_stock_count' => (clone $productsQuery)
                    ->whereNotNull('min_stock')
                    ->where('min_stock', '>', 0)
                    ->whereRaw('COALESCE((SELECT SUM(CASE WHEN direction = \'in\' THEN qty ELSE -qty END) FROM stock_movements WHERE stock_movements.product_id = products.id), 0) <= min_stock')
                    ->count(),
            ];
        });
    }

    protected function loadChartData(): void
    {
        $cacheKey = "{$this->getCachePrefix()}:chart_data";

        $chartData = Cache::remember($cacheKey, $this->cacheTtl, function () {
            $labels = [];
            $salesData = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('D');
                $salesData[] = (float) $this->scopeSalesQuery(Sale::query())->whereDate('created_at', $date)->sum('grand_total');
            }

            $paymentMethodsRaw = DB::table('sale_payments')
                ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
                ->whereMonth('sales.created_at', now()->month)
                ->when(!$this->isAdmin && $this->branchId, fn ($q) => $q->where('sales.branch_id', $this->branchId))
                ->whereNull('sales.deleted_at')
                ->select('sale_payments.payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(sale_payments.amount) as total'))
                ->groupBy('sale_payments.payment_method')
                ->get();

            $productsQuery = $this->scopeProductsQuery(Product::query());
            
            // Use StockService for consistent stock calculation
            $stockExpr = StockService::getStockCalculationExpression();
            $branchFilter = (!$this->isAdmin && $this->branchId) ? $this->branchId : null;

            return [
                'sales' => ['labels' => $labels, 'data' => $salesData],
                'payment' => [
                    'labels' => $paymentMethodsRaw->pluck('payment_method')->map(fn ($m) => ucfirst($m ?? 'cash'))->toArray(),
                    'data' => $paymentMethodsRaw->pluck('count')->toArray(),
                    'totals' => $paymentMethodsRaw->pluck('total')->toArray(),
                ],
                'inventory' => $this->calculateInventoryStats($stockExpr, $branchFilter),
            ];
        });

        $this->salesChartData = $chartData['sales'];
        $this->paymentMethodsData = $chartData['payment'];
        $this->inventoryChartData = $chartData['inventory'];
    }

    protected function loadLowStockProducts(): void
    {
        $cacheKey = "{$this->getCachePrefix()}:low_stock";

        $this->lowStockProducts = Cache::remember($cacheKey, $this->cacheTtl, function () {
            $stockExpr = StockService::getStockCalculationExpression();
            
            return $this->scopeProductsQuery(Product::query())
                ->select('products.*')
                ->selectRaw("{$stockExpr} as current_quantity")
                ->with('category')
                ->whereRaw("{$stockExpr} <= products.min_stock")
                ->where('products.min_stock', '>', 0)
                ->where('products.track_stock_alerts', true)
                ->orderByRaw($stockExpr)
                ->limit(5)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'quantity' => $p->current_quantity ?? 0,
                    'min_stock' => $p->min_stock,
                    'category' => $p->category?->name ?? '-',
                ])
                ->toArray();
        });
    }

    protected function loadRecentSales(): void
    {
        $cacheKey = "{$this->getCachePrefix()}:recent_sales";

        $this->recentSales = Cache::remember($cacheKey, 60, function () {
            return $this->scopeSalesQuery(Sale::query())
                ->with(['user', 'customer'])
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'reference' => $s->reference_no ?? "#{$s->id}",
                    'customer' => $s->customer?->name ?? __('Walk-in'),
                    'total' => number_format($s->grand_total ?? 0, 2),
                    'status' => $s->status,
                    'date' => $s->created_at->format('Y-m-d H:i'),
                ])
                ->toArray();
        });
    }

    protected function loadTrendIndicators(): void
    {
        $cacheKey = "{$this->getCachePrefix()}:trends";

        $this->trendIndicators = Cache::remember($cacheKey, $this->cacheTtl, function () {
            $salesQuery = $this->scopeSalesQuery(Sale::query());

            $currentWeekSales = (clone $salesQuery)
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('grand_total') ?? 0;

            $previousWeekSales = (clone $salesQuery)
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->sum('grand_total') ?? 0;

            $invoiceTotal = (clone $salesQuery)->count();
            $invoiceCleared = (clone $salesQuery)->where('status', 'completed')->count();

            return [
                'weekly_sales' => [
                    'current' => number_format($currentWeekSales, 2),
                    'previous' => number_format($previousWeekSales, 2),
                    'change' => $this->calculatePercentageChange($currentWeekSales, $previousWeekSales),
                ],
                'invoice_clear_rate' => $invoiceTotal > 0 ? round(($invoiceCleared / $invoiceTotal) * 100, 1) : 0,
                'inventory_health' => ($this->stats['total_products'] ?? 0) > 0
                    ? round(max(0, min(100, 100 - ((($this->stats['low_stock_count'] ?? 0) / ($this->stats['total_products'] ?? 1)) * 100))), 1)
                    : 100,
            ];
        });
    }

    protected function calculatePercentageChange(float $current, float $previous): float
    {
        if ($previous <= 0 && $current > 0) return 100.0;
        if ($previous === 0.0) return 0.0;
        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function render(): View
    {
        return view('livewire.dashboard.customizable-dashboard');
    }
}
