<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTransaction;
use App\Models\Sale;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyService
{
    use HandlesServiceErrors;

    public function earnPoints(Customer $customer, Sale $sale, ?int $userId = null): ?LoyaltyTransaction
    {
        return $this->handleServiceOperation(
            callback: function () use ($customer, $sale, $userId) {
                $settings = LoyaltySetting::getForBranch($customer->branch_id);

                if (! $settings || ! $settings->is_active) {
                    return null;
                }

                $amountPerPoint = (float) $settings->amount_per_point;
                if ($amountPerPoint <= 0) {
                    return null;
                }

                $points = (int) floor((float) $sale->total / $amountPerPoint * (float) $settings->points_per_amount);

                if ($points <= 0) {
                    return null;
                }

                return DB::transaction(function () use ($customer, $sale, $points, $userId) {
                    $customer->increment('loyalty_points', $points);
                    $customer->refresh();

                    $this->updateCustomerTier($customer);

                    return LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'branch_id' => $sale->branch_id,
                        'sale_id' => $sale->id,
                        'type' => 'earn',
                        'points' => $points,
                        'balance_after' => $customer->loyalty_points,
                        'description' => __('Points earned from sale #:invoice', ['invoice' => $sale->invoice_number]),
                        'created_by' => $userId,
                    ]);
                });
            },
            operation: 'earnPoints',
            context: ['customer_id' => $customer->id, 'sale_id' => $sale->id],
            defaultValue: null
        );
    }

    public function redeemPoints(Customer $customer, int $points, ?int $saleId = null, ?int $userId = null): ?LoyaltyTransaction
    {
        return $this->handleServiceOperation(
            callback: function () use ($customer, $points, $saleId, $userId) {
                $settings = LoyaltySetting::getForBranch($customer->branch_id);

                if (! $settings || ! $settings->is_active) {
                    return null;
                }

                if ($points <= 0) {
                    throw new InvalidArgumentException(__('Points must be greater than zero'));
                }

                if ($points < $settings->min_points_redeem) {
                    throw new InvalidArgumentException(__('Minimum points to redeem is :min', ['min' => $settings->min_points_redeem]));
                }

                $currentPoints = (int) $customer->loyalty_points;
                if ($currentPoints < $points) {
                    throw new InvalidArgumentException(__('Insufficient points. You have :current points but trying to redeem :requested', [
                        'current' => $currentPoints,
                        'requested' => $points,
                    ]));
                }

                return DB::transaction(function () use ($customer, $points, $saleId, $userId, $settings) {
                    $customer->decrement('loyalty_points', $points);
                    $customer->refresh();

                    $this->updateCustomerTier($customer);

                    $monetaryValue = $points * (float) $settings->redemption_rate;

                    return LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'branch_id' => $customer->branch_id,
                        'sale_id' => $saleId,
                        'type' => 'redeem',
                        'points' => -$points,
                        'balance_after' => $customer->loyalty_points,
                        'description' => __('Redeemed :points points for :amount', [
                            'points' => $points,
                            'amount' => number_format($monetaryValue, 2),
                        ]),
                        'created_by' => $userId,
                    ]);
                });
            },
            operation: 'redeemPoints',
            context: ['customer_id' => $customer->id, 'points' => $points]
        );
    }

    public function addBonusPoints(Customer $customer, int $points, string $reason, ?int $userId = null): LoyaltyTransaction
    {
        return $this->handleServiceOperation(
            callback: function () use ($customer, $points, $reason, $userId) {
                if ($points <= 0) {
                    throw new InvalidArgumentException(__('Bonus points must be greater than zero'));
                }

                if (empty(trim($reason))) {
                    throw new InvalidArgumentException(__('Reason is required for bonus points'));
                }

                return DB::transaction(function () use ($customer, $points, $reason, $userId) {
                    $customer->increment('loyalty_points', $points);
                    $customer->refresh();

                    $this->updateCustomerTier($customer);

                    return LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'branch_id' => $customer->branch_id,
                        'type' => 'bonus',
                        'points' => $points,
                        'balance_after' => $customer->loyalty_points,
                        'description' => $reason,
                        'created_by' => $userId,
                    ]);
                });
            },
            operation: 'addBonusPoints',
            context: ['customer_id' => $customer->id, 'points' => $points]
        );
    }

    public function adjustPoints(Customer $customer, int $points, string $reason, ?int $userId = null): LoyaltyTransaction
    {
        return $this->handleServiceOperation(
            callback: function () use ($customer, $points, $reason, $userId) {
                if ($points === 0) {
                    throw new InvalidArgumentException(__('Adjustment points cannot be zero'));
                }

                if (empty(trim($reason))) {
                    throw new InvalidArgumentException(__('Reason is required for point adjustments'));
                }

                $currentPoints = (int) $customer->loyalty_points;
                if ($points < 0 && abs($points) > $currentPoints) {
                    throw new InvalidArgumentException(__('Cannot deduct more points than the customer has. Current balance: :current', [
                        'current' => $currentPoints,
                    ]));
                }

                return DB::transaction(function () use ($customer, $points, $reason, $userId) {
                    if ($points > 0) {
                        $customer->increment('loyalty_points', $points);
                    } else {
                        $customer->decrement('loyalty_points', abs($points));
                    }
                    $customer->refresh();

                    $this->updateCustomerTier($customer);

                    return LoyaltyTransaction::create([
                        'customer_id' => $customer->id,
                        'branch_id' => $customer->branch_id,
                        'type' => 'adjust',
                        'points' => $points,
                        'balance_after' => max(0, $customer->loyalty_points),
                        'description' => $reason,
                        'created_by' => $userId,
                    ]);
                });
            },
            operation: 'adjustPoints',
            context: ['customer_id' => $customer->id, 'points' => $points]
        );
    }

    public function calculateRedemptionValue(Customer $customer, int $points): float
    {
        $settings = LoyaltySetting::getForBranch($customer->branch_id);

        if (! $settings) {
            return 0;
        }

        return $points * (float) $settings->redemption_rate;
    }

    protected function updateCustomerTier(Customer $customer): void
    {
        $totalPoints = (int) $customer->loyalty_points;

        // Get tier thresholds from config (configurable in admin settings)
        $premiumThreshold = (int) config('loyalty.tier_thresholds.premium', 10000);
        $vipThreshold = (int) config('loyalty.tier_thresholds.vip', 5000);
        $regularThreshold = (int) config('loyalty.tier_thresholds.regular', 1000);

        $tier = match (true) {
            $totalPoints >= $premiumThreshold => 'premium',
            $totalPoints >= $vipThreshold => 'vip',
            $totalPoints >= $regularThreshold => 'regular',
            default => 'new',
        };

        $currentTier = $customer->customer_tier ?? 'new';
        if ($currentTier !== $tier) {
            $customer->update([
                'customer_tier' => $tier,
                'tier_updated_at' => now(),
            ]);
        }
    }

    public function getCustomerHistory(Customer $customer, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $this->handleServiceOperation(
            callback: fn () => LoyaltyTransaction::where('customer_id', $customer->id)
                ->with('sale')
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get(),
            operation: 'getCustomerHistory',
            context: ['customer_id' => $customer->id, 'limit' => $limit]
        );
    }
}
