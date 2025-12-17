<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Customer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CreditLimitCheck - Validate customer has available credit
 * 
 * NEW FEATURE: Custom validation rule for credit limit checking
 */
class CreditLimitCheck implements ValidationRule
{
    private float $amount;
    private ?string $message = null;

    public function __construct(float $amount)
    {
        $this->amount = $amount;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        $customer = Customer::find($value);

        if (!$customer) {
            $fail('The selected customer does not exist.');
            return;
        }

        if ($customer->credit_hold) {
            $fail("Customer {$customer->name} is on credit hold: {$customer->credit_hold_reason}");
            return;
        }

        if (!$customer->hasAvailableCredit($this->amount)) {
            $availableCredit = $customer->credit_limit - $customer->balance;
            $fail("Customer {$customer->name} has insufficient credit. Available: " . number_format($availableCredit, 2) . ", Required: " . number_format($this->amount, 2));
            return;
        }
    }
}
