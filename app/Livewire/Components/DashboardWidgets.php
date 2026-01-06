<?php

declare(strict_types=1);

namespace App\Livewire\Components;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\UserDashboardWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class DashboardWidgets extends Component
{
    public array $widgets = [];
    
    public array $widgetData = [];
    
    protected int $cacheTtl = 300;

    public function mount(): void
    {
        $this->loadUserWidgets();
        $this->loadWidgetData();
    }

    public function loadUserWidgets(): void
    {
        $user = Auth::user();
        
        if (!$user) {
            return;
        }

        // Get user's widget preferences or use defaults
        $userWidgets = UserDashboardWidget::where('user_id', $user->id)
            ->orderBy('position')
            ->get();

        if ($userWidgets->isEmpty()) {
            // Default widgets
            $this->widgets = $this->getDefaultWidgets();
        } else {
            $this->widgets = $userWidgets->map(fn($w) => [
                'id' => $w->widget_key,
                'title' => __($w->widget_title),
                'visible' => $w->is_visible,
                'position' => $w->position,
            ])->toArray();
        }
    }

    public function loadWidgetData(): void
    {
        $user = Auth::user();
        
        if (!$user) {
            return;
        }

        $cacheKey = "dashboard_widgets:user_{$user->id}:branch_{$user->branch_id}";
        
        $this->widgetData = Cache::remember($cacheKey, $this->cacheTtl, function () use ($user) {
            $isAdmin = $user->hasRole('super-admin') || $user->hasRole('admin');
            $branchId = $user->branch_id;

            $salesQuery = Sale::query();
            $productsQuery = Product::query();
            $customersQuery = Customer::query();

            if (!$isAdmin && $branchId) {
                $salesQuery->where('branch_id', $branchId);
                $productsQuery->where('branch_id', $branchId);
                $customersQuery->where('branch_id', $branchId);
            }

            return [
                'total_sales_today' => (clone $salesQuery)
                    ->whereDate('created_at', today())
                    ->sum('grand_total') ?? 0,
                'total_sales_week' => (clone $salesQuery)
                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->sum('grand_total') ?? 0,
                'total_sales_month' => (clone $salesQuery)
                    ->whereMonth('created_at', now()->month)
                    ->sum('grand_total') ?? 0,
                'total_revenue_month' => (clone $salesQuery)
                    ->whereMonth('created_at', now()->month)
                    ->where('status', 'completed')
                    ->sum('grand_total') ?? 0,
                'total_products' => (clone $productsQuery)->count(),
                'active_products' => (clone $productsQuery)->where('is_active', true)->count(),
                'total_customers' => (clone $customersQuery)->count(),
                'new_customers_month' => (clone $customersQuery)->whereMonth('created_at', now()->month)->count(),
                // Use indexed query with proper joins for better performance
                // quantity is signed: positive = in, negative = out
                'low_stock_count' => DB::table('products')
                    ->leftJoin('stock_movements', 'stock_movements.product_id', '=', 'products.id')
                    ->whereNull('products.deleted_at')
                    ->whereNotNull('products.min_stock')
                    ->where('products.min_stock', '>', 0)
                    ->when(!$isAdmin && $branchId, fn($q) => $q->where('products.branch_id', $branchId))
                    ->select('products.id')
                    ->selectRaw('COALESCE(SUM(stock_movements.quantity), 0) as current_stock')
                    ->groupBy('products.id', 'products.min_stock')
                    ->havingRaw('current_stock <= products.min_stock')
                    ->count(),
                'pending_orders' => $salesQuery->where('status', 'pending')->count(),
            ];
        });
    }

    public function toggleWidget(string $widgetId): void
    {
        $user = Auth::user();
        
        if (!$user) {
            return;
        }

        $widget = UserDashboardWidget::where('user_id', $user->id)
            ->where('widget_key', $widgetId)
            ->first();

        if ($widget) {
            $widget->update(['is_visible' => !$widget->is_visible]);
        } else {
            // Create widget preference
            $defaultWidget = collect($this->getDefaultWidgets())->firstWhere('id', $widgetId);
            if ($defaultWidget) {
                UserDashboardWidget::create([
                    'user_id' => $user->id,
                    'widget_key' => $widgetId,
                    'widget_title' => $defaultWidget['title'],
                    'is_visible' => true,
                    'position' => count($this->widgets) + 1,
                ]);
            }
        }

        $this->loadUserWidgets();
        Cache::forget("dashboard_widgets:user_{$user->id}:branch_{$user->branch_id}");
        $this->loadWidgetData();
    }

    public function refreshData(): void
    {
        $user = Auth::user();
        if ($user) {
            Cache::forget("dashboard_widgets:user_{$user->id}:branch_{$user->branch_id}");
        }
        $this->loadWidgetData();
    }

    protected function getDefaultWidgets(): array
    {
        return [
            ['id' => 'sales_today', 'title' => __("Today's Sales"), 'visible' => true, 'position' => 1],
            ['id' => 'revenue_month', 'title' => __('Monthly Revenue'), 'visible' => true, 'position' => 2],
            ['id' => 'total_products', 'title' => __('Total Products'), 'visible' => true, 'position' => 3],
            ['id' => 'total_customers', 'title' => __('Total Customers'), 'visible' => true, 'position' => 4],
            ['id' => 'low_stock', 'title' => __('Low Stock Items'), 'visible' => true, 'position' => 5],
            ['id' => 'pending_orders', 'title' => __('Pending Orders'), 'visible' => true, 'position' => 6],
        ];
    }

    public function render()
    {
        return view('livewire.components.dashboard-widgets');
    }
}
