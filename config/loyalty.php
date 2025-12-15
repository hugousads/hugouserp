<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Customer Tier Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the loyalty points required to achieve each customer tier.
    | These values can be overridden via environment variables.
    |
    */
    'tier_thresholds' => [
        'premium' => env('LOYALTY_TIER_PREMIUM', 10000),
        'vip' => env('LOYALTY_TIER_VIP', 5000),
        'regular' => env('LOYALTY_TIER_REGULAR', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Loyalty Settings
    |--------------------------------------------------------------------------
    |
    | Default settings for loyalty program when branch-specific settings
    | are not available.
    |
    */
    'defaults' => [
        'points_per_amount' => env('LOYALTY_POINTS_PER_AMOUNT', 1),
        'amount_per_point' => env('LOYALTY_AMOUNT_PER_POINT', 10),
        'redemption_rate' => env('LOYALTY_REDEMPTION_RATE', 0.1),
        'min_points_redeem' => env('LOYALTY_MIN_POINTS_REDEEM', 100),
    ],
];
