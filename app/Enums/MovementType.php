<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Movement Type Enum
 *
 * Represents all possible types of stock movements.
 * MySQL 8.4 compatible - can be used with ENUM column type.
 */
enum MovementType: string
{
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case TRANSFER = 'transfer';
    case ADJUSTMENT = 'adjustment';
    case RETURN = 'return';
    case PRODUCTION = 'production';
    case PRODUCTION_RETURN = 'production_return';
    case API_SYNC = 'api_sync';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => __('Purchase'),
            self::SALE => __('Sale'),
            self::TRANSFER => __('Transfer'),
            self::ADJUSTMENT => __('Adjustment'),
            self::RETURN => __('Return'),
            self::PRODUCTION => __('Production'),
            self::PRODUCTION_RETURN => __('Production Return'),
            self::API_SYNC => __('API Sync'),
        };
    }

    /**
     * Get color for display.
     */
    public function color(): string
    {
        return match ($this) {
            self::PURCHASE => 'green',
            self::SALE => 'blue',
            self::TRANSFER => 'purple',
            self::ADJUSTMENT => 'amber',
            self::RETURN => 'red',
            self::PRODUCTION => 'cyan',
            self::PRODUCTION_RETURN => 'orange',
            self::API_SYNC => 'slate',
        };
    }

    /**
     * Check if movement type increases stock.
     */
    public function isStockIn(): bool
    {
        return in_array($this, [
            self::PURCHASE,
            self::RETURN,
            self::PRODUCTION,
            self::API_SYNC,
        ]);
    }

    /**
     * Check if movement type decreases stock.
     */
    public function isStockOut(): bool
    {
        return in_array($this, [
            self::SALE,
            self::PRODUCTION_RETURN,
        ]);
    }

    /**
     * Check if movement type can be either (depends on direction).
     */
    public function isBidirectional(): bool
    {
        return in_array($this, [
            self::TRANSFER,
            self::ADJUSTMENT,
        ]);
    }

    /**
     * Get all types as array for validation.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
