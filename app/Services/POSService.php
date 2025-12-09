<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PosSession;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Tax;
use App\Models\User;
use App\Rules\ValidPriceOverride;
use App\Services\Contracts\POSServiceInterface;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;

class POSService implements POSServiceInterface
{
    use HandlesServiceErrors;

    public function __construct(
        protected DiscountService $discounts
    ) {}

    public function checkout(array $payload): Sale
    {
        return $this->handleServiceOperation(
            callback: fn () => DB::transaction(function () use ($payload) {
                $items = $payload['items'] ?? [];
                abort_if(empty($items), 422, 'No items');

                $user = auth()->user();
                $branchId = $payload['branch_id'] ?? request()->attributes->get('branch_id');

                // Validate branch ID is present
                abort_if(!$branchId, 422, __('Branch context is required'));

                // Validate POS session exists and is open
                if (($payload['channel'] ?? 'pos') === 'pos') {
                    $activeSession = PosSession::where('branch_id', $branchId)
                        ->where('user_id', $user?->id)
                        ->where('status', PosSession::STATUS_OPEN)
                        ->first();

                    if (! $activeSession && config('pos.require_session', true)) {
                        abort(422, __('No active POS session. Please open a session first.'));
                    }
                }

                $sale = Sale::create([
                    'branch_id' => $branchId,
                    'warehouse_id' => $payload['warehouse_id'] ?? null,
                    'customer_id' => $payload['customer_id'] ?? null,
                    'status' => 'completed',
                    'channel' => $payload['channel'] ?? 'pos',
                    'currency' => $payload['currency'] ?? 'EGP',
                    'sub_total' => 0,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'grand_total' => 0,
                    'paid_total' => 0,
                    'due_total' => 0,
                    'notes' => $payload['notes'] ?? null,
                    'created_by' => $user?->id,
                ]);

                $subtotal = 0.0;
                $discountTotal = 0.0;
                $taxTotal = 0.0;

                $previousDailyDiscount = 0.0;
                if ($user && $user->daily_discount_limit !== null) {
                    $previousDailyDiscount = (float) Sale::where('created_by', $user->id)
                        ->whereDate('created_at', now()->toDateString())
                        ->sum('discount_total');
                }

                foreach ($items as $it) {
                    // Use lockForUpdate() to prevent concurrent stock issues and overselling
                    // Note: In high-concurrency environments, handle potential deadlocks with retry logic
                    $product = Product::lockForUpdate()->findOrFail($it['product_id']);
                    $qty = (float) ($it['qty'] ?? 1);
                    $price = isset($it['price']) ? (float) $it['price'] : (float) ($product->default_price ?? 0);

                    if ($user && ! $user->can_modify_price && $price != (float) ($product->default_price ?? 0)) {
                        abort(422, __('You are not allowed to modify prices'));
                    }

                    (new ValidPriceOverride((float) $product->cost, 0.0))->validate('price', $price, function ($m) {
                        abort(422, $m);
                    });

                    $itemDiscountPercent = (float) ($it['discount'] ?? 0);
                    if ($user && $user->max_discount_percent !== null && $itemDiscountPercent > $user->max_discount_percent) {
                        abort(422, __('Discount exceeds your maximum allowed discount of :max%', ['max' => $user->max_discount_percent]));
                    }

                    $lineDisc = $this->discounts->lineTotal($qty, $price, $itemDiscountPercent, (bool) ($it['percent'] ?? true));

                    if ($user && $user->daily_discount_limit !== null && $lineDisc > 0) {
                        $totalUsedWithThisLine = $previousDailyDiscount + $discountTotal + $lineDisc;
                        if ($totalUsedWithThisLine > $user->daily_discount_limit) {
                            abort(422, __('Daily discount limit of :limit EGP exceeded. Already used: :used EGP, this transaction adds: :add EGP', [
                                'limit' => number_format($user->daily_discount_limit, 2),
                                'used' => number_format($previousDailyDiscount, 2),
                                'add' => number_format($discountTotal + $lineDisc, 2),
                            ]));
                        }
                    }
                    $lineSub = $qty * $price;
                    $lineTax = 0.0;

                    if (! empty($it['tax_id']) && class_exists(Tax::class)) {
                        $tax = Tax::find($it['tax_id']);
                        if ($tax) {
                            $lineTax = ($lineSub - $lineDisc) * ((float) $tax->rate / 100);
                        }
                    }

                    $subtotal += $lineSub;
                    $discountTotal += $lineDisc;
                    $taxTotal += $lineTax;

                    SaleItem::create([
                        'sale_id' => $sale->getKey(),
                        'product_id' => $product->getKey(),
                        'branch_id' => $branchId,
                        'qty' => $qty,
                        'unit_price' => $price,
                        'discount' => $lineDisc,
                        'tax_id' => $it['tax_id'] ?? null,
                        'line_total' => round($lineSub - $lineDisc + $lineTax, 2),
                    ]);
                }

                $grandTotal = round($subtotal - $discountTotal + $taxTotal, 2);

                $sale->sub_total = round($subtotal, 2);
                $sale->discount_total = round($discountTotal, 2);
                $sale->tax_total = round($taxTotal, 2);
                $sale->grand_total = $grandTotal;

                $payments = $payload['payments'] ?? [];
                $paidTotal = 0.0;

                if (! empty($payments)) {
                    foreach ($payments as $payment) {
                        $amount = (float) ($payment['amount'] ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }

                        SalePayment::create([
                            'sale_id' => $sale->getKey(),
                            'branch_id' => $branchId,
                            'payment_method' => $payment['method'] ?? 'cash',
                            'amount' => $amount,
                            'currency' => $payment['currency'] ?? 'EGP',
                            'reference_no' => $payment['reference_no'] ?? null,
                            'card_type' => $payment['card_type'] ?? null,
                            'card_last_four' => $payment['card_last_four'] ?? null,
                            'bank_name' => $payment['bank_name'] ?? null,
                            'cheque_number' => $payment['cheque_number'] ?? null,
                            'cheque_date' => $payment['cheque_date'] ?? null,
                            'notes' => $payment['notes'] ?? null,
                            'status' => 'completed',
                            'created_by' => $user?->id,
                        ]);

                        $paidTotal += $amount;
                    }
                } else {
                    SalePayment::create([
                        'sale_id' => $sale->getKey(),
                        'branch_id' => $branchId,
                        'payment_method' => 'cash',
                        'amount' => $grandTotal,
                        'currency' => 'EGP',
                        'status' => 'completed',
                        'created_by' => $user?->id,
                    ]);
                    $paidTotal = $grandTotal;
                }

                $sale->paid_total = round($paidTotal, 2);
                $sale->due_total = round(max(0, $grandTotal - $paidTotal), 2);
                $sale->status = $paidTotal >= $grandTotal ? 'completed' : 'partial';
                $sale->save();

                event(new \App\Events\SaleCompleted($sale));

                return $sale->load(['items.product', 'payments', 'customer']);
            }),
            operation: 'checkout',
            context: ['payload' => $payload]
        );
    }

    public function openSession(int $branchId, int $userId, float $openingCash = 0): PosSession
    {
        return $this->handleServiceOperation(
            callback: function () use ($branchId, $userId, $openingCash) {
                $existingSession = PosSession::where('branch_id', $branchId)
                    ->where('user_id', $userId)
                    ->where('status', PosSession::STATUS_OPEN)
                    ->first();

                if ($existingSession) {
                    return $existingSession;
                }

                return PosSession::create([
                    'branch_id' => $branchId,
                    'user_id' => $userId,
                    'opening_cash' => $openingCash,
                    'status' => PosSession::STATUS_OPEN,
                    'opened_at' => now(),
                ]);
            },
            operation: 'openSession',
            context: ['branch_id' => $branchId, 'user_id' => $userId, 'opening_cash' => $openingCash]
        );
    }

    public function closeSession(int $sessionId, float $closingCash, ?string $notes = null): PosSession
    {
        return $this->handleServiceOperation(
            callback: function () use ($sessionId, $closingCash, $notes) {
                $session = PosSession::findOrFail($sessionId);

                if (! $session->isOpen()) {
                    abort(422, __('Session is already closed'));
                }

                $salesQuery = Sale::where('branch_id', $session->branch_id)
                    ->where('created_by', $session->user_id)
                    ->where('created_at', '>=', $session->opened_at)
                    ->where('status', '!=', 'cancelled');

                $totalSales = (float) $salesQuery->sum('grand_total');
                $totalTransactions = $salesQuery->count();

                $paymentSummary = SalePayment::whereIn('sale_id', $salesQuery->pluck('id'))
                    ->selectRaw('payment_method, SUM(amount) as total')
                    ->groupBy('payment_method')
                    ->pluck('total', 'payment_method')
                    ->toArray();

                $expectedCash = $session->opening_cash + ($paymentSummary['cash'] ?? 0);
                $cashDifference = $closingCash - $expectedCash;

                $session->update([
                    'closing_cash' => $closingCash,
                    'expected_cash' => $expectedCash,
                    'cash_difference' => $cashDifference,
                    'payment_summary' => $paymentSummary,
                    'total_transactions' => $totalTransactions,
                    'total_sales' => $totalSales,
                    'total_refunds' => 0,
                    'status' => PosSession::STATUS_CLOSED,
                    'closed_at' => now(),
                    'closing_notes' => $notes,
                    'closed_by' => auth()->id(),
                ]);

                return $session->fresh();
            },
            operation: 'closeSession',
            context: ['session_id' => $sessionId, 'closing_cash' => $closingCash]
        );
    }

    public function getCurrentSession(int $branchId, int $userId): ?PosSession
    {
        return PosSession::where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->where('status', PosSession::STATUS_OPEN)
            ->first();
    }

    public function getSessionReport(int $sessionId): array
    {
        $session = PosSession::with(['user', 'branch', 'closedBy'])->findOrFail($sessionId);

        $sales = Sale::where('branch_id', $session->branch_id)
            ->where('created_by', $session->user_id)
            ->whereBetween('created_at', [$session->opened_at, $session->closed_at ?? now()])
            ->with(['items', 'payments', 'customer'])
            ->get();

        return [
            'session' => $session,
            'sales' => $sales,
            'summary' => [
                'total_transactions' => $sales->count(),
                'total_sales' => $sales->sum('grand_total'),
                'total_discount' => $sales->sum('discount_total'),
                'total_tax' => $sales->sum('tax_total'),
                'payment_breakdown' => $session->payment_summary ?? [],
                'opening_cash' => $session->opening_cash,
                'closing_cash' => $session->closing_cash,
                'expected_cash' => $session->expected_cash,
                'cash_difference' => $session->cash_difference,
            ],
        ];
    }

    public function validateDiscount(User $user, float $discountPercent): bool
    {
        return $discountPercent <= ($user->max_discount_percent ?? 100);
    }
}
