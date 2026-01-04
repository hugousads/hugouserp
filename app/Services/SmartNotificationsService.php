<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\GeneralNotification;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Smart Notifications Service
 * 
 * Handles automatic notifications for:
 * - Low stock alerts
 * - Overdue invoices
 * - Payment reminders
 * - System alerts
 */
class SmartNotificationsService
{
    /**
     * Check and send low stock notifications
     */
    public function checkLowStockAlerts(?int $branchId = null): array
    {
        $notified = [];

        try {
            $stockExpr = StockService::getStockCalculationExpression();
            
            $query = Product::query()
                ->select('products.*')
                ->selectRaw("{$stockExpr} as current_quantity")
                ->whereRaw("{$stockExpr} <= products.min_stock")
                ->where('products.min_stock', '>', 0)
                ->where('products.track_stock_alerts', true)
                ->when($branchId, fn($q) => $q->where('products.branch_id', $branchId));

            $lowStockProducts = $query->get();

            if ($lowStockProducts->isEmpty()) {
                return $notified;
            }

            // Get users who should receive notifications
            $users = $this->getUsersForNotification('inventory.products.view', $branchId);

            foreach ($lowStockProducts as $product) {
                foreach ($users as $user) {
                    // Check if we already notified today for this product
                    $alreadyNotified = DB::table('notifications')
                        ->where('notifiable_id', $user->id)
                        ->where('notifiable_type', User::class)
                        ->whereDate('created_at', today())
                        ->whereJsonContains('data->product_id', $product->id)
                        ->exists();

                    if (!$alreadyNotified) {
                        $user->notify(new GeneralNotification(
                            type: 'low_stock',
                            title: __('Low Stock Alert'),
                            message: __(':product stock is low (:qty remaining, min: :min)', [
                                'product' => $product->name,
                                'qty' => $product->current_quantity,
                                'min' => $product->min_stock,
                            ]),
                            actionUrl: route('app.inventory.products.index', ['search' => $product->name]),
                            actionLabel: __('View Product'),
                            data: [
                                'product_id' => $product->id,
                                'product_name' => $product->name,
                                'current_qty' => $product->current_quantity,
                                'min_stock' => $product->min_stock,
                            ]
                        ));
                        $notified[] = $product->name;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Low stock notification error: ' . $e->getMessage());
        }

        return $notified;
    }

    /**
     * Check and send overdue invoice notifications
     */
    public function checkOverdueInvoices(?int $branchId = null): array
    {
        $notified = [];

        try {
            $query = Sale::query()
                ->where('status', 'pending')
                ->where('due_total', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->with(['customer']);

            $overdueInvoices = $query->get();

            if ($overdueInvoices->isEmpty()) {
                return $notified;
            }

            // Get users who should receive notifications
            $users = $this->getUsersForNotification('sales.view', $branchId);

            foreach ($overdueInvoices as $invoice) {
                foreach ($users as $user) {
                    // Check if we already notified today for this invoice
                    $alreadyNotified = DB::table('notifications')
                        ->where('notifiable_id', $user->id)
                        ->where('notifiable_type', User::class)
                        ->whereDate('created_at', today())
                        ->whereJsonContains('data->invoice_id', $invoice->id)
                        ->exists();

                    if (!$alreadyNotified) {
                        $customerName = $invoice->customer?->name ?? __('Walk-in');
                        $daysOverdue = now()->diffInDays($invoice->due_date);

                        $user->notify(new GeneralNotification(
                            type: 'overdue_invoice',
                            title: __('Overdue Invoice'),
                            message: __('Invoice :ref from :customer is :days days overdue (Due: :amount)', [
                                'ref' => $invoice->reference_no ?? $invoice->code,
                                'customer' => $customerName,
                                'days' => $daysOverdue,
                                'amount' => number_format($invoice->due_total, 2),
                            ]),
                            actionUrl: route('app.sales.show', $invoice->id),
                            actionLabel: __('View Invoice'),
                            data: [
                                'invoice_id' => $invoice->id,
                                'reference' => $invoice->reference_no ?? $invoice->code,
                                'customer' => $customerName,
                                'due_total' => $invoice->due_total,
                                'days_overdue' => $daysOverdue,
                            ]
                        ));
                        $notified[] = $invoice->reference_no ?? $invoice->code;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Overdue invoice notification error: ' . $e->getMessage());
        }

        return $notified;
    }

    /**
     * Check and send payment reminders for invoices due soon
     */
    public function checkPaymentReminders(?int $branchId = null, int $daysBefore = 3): array
    {
        $notified = [];

        try {
            $dueDate = today()->addDays($daysBefore);

            $query = Sale::query()
                ->where('status', 'pending')
                ->where('due_total', '>', 0)
                ->whereNotNull('due_date')
                ->whereDate('due_date', $dueDate)
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->with(['customer']);

            $upcomingInvoices = $query->get();

            if ($upcomingInvoices->isEmpty()) {
                return $notified;
            }

            // Get users who should receive notifications
            $users = $this->getUsersForNotification('sales.view', $branchId);

            foreach ($upcomingInvoices as $invoice) {
                foreach ($users as $user) {
                    $customerName = $invoice->customer?->name ?? __('Walk-in');

                    $user->notify(new GeneralNotification(
                        type: 'payment_reminder',
                        title: __('Payment Reminder'),
                        message: __('Invoice :ref from :customer is due in :days days (Amount: :amount)', [
                            'ref' => $invoice->reference_no ?? $invoice->code,
                            'customer' => $customerName,
                            'days' => $daysBefore,
                            'amount' => number_format($invoice->due_total, 2),
                        ]),
                        actionUrl: route('app.sales.show', $invoice->id),
                        actionLabel: __('View Invoice'),
                        data: [
                            'invoice_id' => $invoice->id,
                            'reference' => $invoice->reference_no ?? $invoice->code,
                            'customer' => $customerName,
                            'due_total' => $invoice->due_total,
                            'days_until_due' => $daysBefore,
                        ]
                    ));
                    $notified[] = $invoice->reference_no ?? $invoice->code;
                }
            }
        } catch (\Exception $e) {
            Log::error('Payment reminder notification error: ' . $e->getMessage());
        }

        return $notified;
    }

    /**
     * Get users who should receive notifications based on permission
     */
    protected function getUsersForNotification(string $permission, ?int $branchId = null): \Illuminate\Support\Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->permission($permission)
            ->get();
    }

    /**
     * Run all smart notification checks
     */
    public function runAllChecks(?int $branchId = null): array
    {
        return [
            'low_stock' => $this->checkLowStockAlerts($branchId),
            'overdue_invoices' => $this->checkOverdueInvoices($branchId),
            'payment_reminders' => $this->checkPaymentReminders($branchId),
        ];
    }
}
