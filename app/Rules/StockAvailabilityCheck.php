<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * StockAvailabilityCheck - Validate product has sufficient stock
 * 
 * NEW FEATURE: Custom validation rule for stock availability checking
 */
class StockAvailabilityCheck implements ValidationRule
{
    private float $quantity;
    private bool $allowBackorder;

    public function __construct(float $quantity, bool $allowBackorder = false)
    {
        $this->quantity = $quantity;
        $this->allowBackorder = $allowBackorder;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        $product = Product::find($value);

        if (!$product) {
            $fail('The selected product does not exist.');
            return;
        }

        // Service products don't require stock
        if ($product->isService()) {
            return;
        }

        // Check if backorder is allowed
        if ($this->allowBackorder || $product->allow_backorder) {
            return;
        }

        // Check minimum order quantity
        if ($product->minimum_order_quantity && $this->quantity < $product->minimum_order_quantity) {
            $fail("Minimum order quantity for {$product->name} is {$product->minimum_order_quantity}");
            return;
        }

        // Check maximum order quantity
        if ($product->maximum_order_quantity && $this->quantity > $product->maximum_order_quantity) {
            $fail("Maximum order quantity for {$product->name} is {$product->maximum_order_quantity}");
            return;
        }

        // Check stock availability
        if (!$product->isInStock($this->quantity)) {
            $available = $product->getAvailableQuantity();
            $fail("Insufficient stock for {$product->name}. Available: {$available}, Requested: {$this->quantity}");
            return;
        }

        // Check if product is expired
        if ($product->isExpired()) {
            $fail("Product {$product->name} has expired on {$product->expiry_date->format('Y-m-d')}");
            return;
        }

        // Warn if product is expiring soon
        if ($product->isExpiringSoon(7)) {
            // This is a warning, not a failure - could be logged or handled differently
            // For now, we'll allow it but you might want to add a warning mechanism
        }
    }
}
