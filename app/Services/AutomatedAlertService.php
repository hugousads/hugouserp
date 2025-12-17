<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * AutomatedAlertService - Automated business alerts and notifications
 * 
 * NEW FEATURE: Proactive business monitoring and alerting
 * 
 * FEATURES:
 * - Low stock alerts
 * - Overdue payment alerts
 * - Expiring product alerts
 * - Credit limit warnings
 * - Supplier performance alerts
 */
class AutomatedAlertService
{
    /**
     * Check for low stock products and generate alerts.
     */
    public function checkLowStockAlerts(?int $branchId = null): array
    {
        $products = Product::lowStock()
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with(['branch', 'module'])
            ->get();

        $alerts = [];

        foreach ($products as $product) {
            $alerts[] = [
                'type' => 'low_stock',
                'severity' => $this->getStockSeverity($product),
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'current_stock' => $product->stock_quantity,
                'alert_threshold' => $product->stock_alert_threshold,
                'reorder_point' => $product->reorder_point,
                'branch_id' => $product->branch_id,
                'branch_name' => $product->branch->name ?? null,
                'message' => "Low stock alert: {$product->name} has {$product->stock_quantity} units remaining (threshold: {$product->stock_alert_threshold})",
                'action_required' => $product->stock_quantity <= ($product->reorder_point ?? 0) ? 'reorder' : 'monitor',
            ];
        }

        return $alerts;
    }

    /**
     * Get stock severity level.
     */
    private function getStockSeverity(Product $product): string
    {
        if ($product->stock_quantity <= 0) {
            return 'critical';
        }

        if ($product->reorder_point && $product->stock_quantity <= $product->reorder_point) {
            return 'high';
        }

        if ($product->stock_alert_threshold && $product->stock_quantity <= $product->stock_alert_threshold) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check for overdue sales payments.
     */
    public function checkOverdueSalesAlerts(?int $branchId = null): array
    {
        $overdueSales = Sale::query()
            ->whereNotNull('payment_due_date')
            ->where('payment_due_date', '<', now())
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with(['customer', 'branch'])
            ->get();

        $alerts = [];

        foreach ($overdueSales as $sale) {
            $daysOverdue = now()->diffInDays($sale->payment_due_date);

            $customerName = $sale->customer ? $sale->customer->name : 'Unknown';
            $amountDue = $sale->amount_due ? $sale->amount_due : $sale->due_total;
            
            $alerts[] = [
                'type' => 'overdue_payment',
                'severity' => $this->getPaymentOverdueSeverity($daysOverdue),
                'sale_id' => $sale->id,
                'sale_code' => $sale->code,
                'customer_id' => $sale->customer_id,
                'customer_name' => $customerName,
                'amount_due' => $amountDue,
                'payment_due_date' => $sale->payment_due_date,
                'days_overdue' => $daysOverdue,
                'branch_id' => $sale->branch_id,
                'message' => "Payment overdue: Invoice {$sale->code} for {$customerName} is {$daysOverdue} days overdue",
                'action_required' => 'contact_customer',
            ];
        }

        return $alerts;
    }

    /**
     * Get payment overdue severity.
     */
    private function getPaymentOverdueSeverity(int $daysOverdue): string
    {
        if ($daysOverdue > 60) {
            return 'critical';
        } elseif ($daysOverdue > 30) {
            return 'high';
        } elseif ($daysOverdue > 14) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check for customers approaching credit limit.
     */
    public function checkCreditLimitAlerts(?int $branchId = null): array
    {
        $customers = Customer::query()
            ->where('credit_limit', '>', 0)
            ->whereRaw('balance >= (credit_limit * 0.8)') // 80% of credit limit
            ->where('status', 'active')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('branch')
            ->get();

        $alerts = [];

        foreach ($customers as $customer) {
            $utilization = ($customer->balance / $customer->credit_limit) * 100;

            $severity = $utilization >= 100 ? 'critical' : ($utilization >= 90 ? 'high' : 'medium');
            $action = $utilization >= 100 ? 'credit_hold' : 'review_credit';
            
            $alerts[] = [
                'type' => 'credit_limit_warning',
                'severity' => $severity,
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'current_balance' => $customer->balance,
                'credit_limit' => $customer->credit_limit,
                'utilization_percentage' => round($utilization, 2),
                'available_credit' => max(0, $customer->credit_limit - $customer->balance),
                'branch_id' => $customer->branch_id,
                'message' => "Credit limit warning: {$customer->name} is at " . round($utilization, 0) . "% of credit limit",
                'action_required' => $action,
            ];
        }

        return $alerts;
    }

    /**
     * Check for expiring products.
     */
    public function checkExpiringProductAlerts(int $days = 30, ?int $branchId = null): array
    {
        $products = Product::query()
            ->where('is_perishable', true)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)])
            ->where('stock_quantity', '>', 0)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with('branch')
            ->get();

        $alerts = [];

        foreach ($products as $product) {
            $daysUntilExpiry = now()->diffInDays($product->expiry_date);
            $unitCost = $product->cost ? $product->cost : ($product->standard_cost ? $product->standard_cost : 0);
            $estimatedLoss = $product->stock_quantity * $unitCost;

            $alerts[] = [
                'type' => 'expiring_product',
                'severity' => $this->getExpirySeverity($daysUntilExpiry),
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'stock_quantity' => $product->stock_quantity,
                'expiry_date' => $product->expiry_date,
                'days_until_expiry' => $daysUntilExpiry,
                'estimated_loss' => $estimatedLoss,
                'branch_id' => $product->branch_id,
                'message' => "Product expiring: {$product->name} expires in {$daysUntilExpiry} days ({$product->stock_quantity} units in stock)",
                'action_required' => $daysUntilExpiry <= 7 ? 'urgent_sale' : 'promote',
            ];
        }

        return $alerts;
    }

    /**
     * Get expiry severity.
     */
    private function getExpirySeverity(int $daysUntilExpiry): string
    {
        if ($daysUntilExpiry <= 3) {
            return 'critical';
        } elseif ($daysUntilExpiry <= 7) {
            return 'high';
        } elseif ($daysUntilExpiry <= 14) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Check for overdue purchase orders.
     */
    public function checkOverduePurchaseAlerts(?int $branchId = null): array
    {
        $overduePurchases = Purchase::query()
            ->whereNotNull('expected_delivery_date')
            ->whereNull('actual_delivery_date')
            ->where('expected_delivery_date', '<', now())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->with(['supplier', 'branch'])
            ->get();

        $alerts = [];

        foreach ($overduePurchases as $purchase) {
            $daysOverdue = now()->diffInDays($purchase->expected_delivery_date);
            $supplierName = $purchase->supplier ? $purchase->supplier->name : 'Unknown';

            $alerts[] = [
                'type' => 'overdue_delivery',
                'severity' => $daysOverdue > 14 ? 'high' : 'medium',
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code,
                'supplier_id' => $purchase->supplier_id,
                'supplier_name' => $supplierName,
                'expected_delivery_date' => $purchase->expected_delivery_date,
                'days_overdue' => $daysOverdue,
                'order_value' => $purchase->grand_total,
                'branch_id' => $purchase->branch_id,
                'message' => "Delivery overdue: Purchase {$purchase->code} from {$supplierName} is {$daysOverdue} days overdue",
                'action_required' => 'contact_supplier',
            ];
        }

        return $alerts;
    }

    /**
     * Get all alerts for a branch.
     */
    public function getAllAlerts(?int $branchId = null): array
    {
        return [
            'low_stock' => $this->checkLowStockAlerts($branchId),
            'overdue_sales' => $this->checkOverdueSalesAlerts($branchId),
            'credit_limits' => $this->checkCreditLimitAlerts($branchId),
            'expiring_products' => $this->checkExpiringProductAlerts(30, $branchId),
            'overdue_purchases' => $this->checkOverduePurchaseAlerts($branchId),
        ];
    }

    /**
     * Get alert summary statistics.
     */
    public function getAlertSummary(?int $branchId = null): array
    {
        $alerts = $this->getAllAlerts($branchId);

        $summary = [
            'total_alerts' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0,
            'by_type' => [],
        ];

        foreach ($alerts as $type => $typeAlerts) {
            $count = count($typeAlerts);
            $summary['total_alerts'] += $count;
            $summary['by_type'][$type] = $count;

            foreach ($typeAlerts as $alert) {
                $severity = $alert['severity'] ?? 'low';
                $summary[$severity]++;
            }
        }

        return $summary;
    }

    /**
     * Get critical alerts only.
     */
    public function getCriticalAlerts(?int $branchId = null): array
    {
        $allAlerts = $this->getAllAlerts($branchId);
        $critical = [];

        foreach ($allAlerts as $type => $typeAlerts) {
            $criticalOfType = array_filter($typeAlerts, fn($a) => ($a['severity'] ?? '') === 'critical');
            if (!empty($criticalOfType)) {
                $critical[$type] = array_values($criticalOfType);
            }
        }

        return $critical;
    }
}
