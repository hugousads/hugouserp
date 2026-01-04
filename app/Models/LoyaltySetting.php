<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class LoyaltySetting extends Model
{
    use HasBranch;

    protected $fillable = [
        'branch_id',
        'points_per_amount',
        'amount_per_point',
        'redemption_rate',
        'min_points_redeem',
        'points_expiry_days',
        'is_active',
    ];

    protected $casts = [
        'points_per_amount' => 'decimal:2',
        'amount_per_point' => 'decimal:2',
        'redemption_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function getForBranch(?int $branchId): ?self
    {
        if (! $branchId) {
            return self::whereNull('branch_id')->first() ?? self::first();
        }

        return self::where('branch_id', $branchId)->first()
            ?? self::whereNull('branch_id')->first();
    }

    public static function cachedForBranch(?int $branchId, int $ttlSeconds = 3600): ?self
    {
        $cacheKey = sprintf('loyalty_settings:%s', $branchId ?? 'global');

        return Cache::remember($cacheKey, $ttlSeconds, function () use ($branchId) {
            return static::getForBranch($branchId);
        });
    }
}
