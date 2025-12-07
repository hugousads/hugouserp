<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ValidQuantity Rule
 *
 * Validates that a value is a valid quantity for inventory/sales operations:
 * - Must be numeric
 * - Must be positive (greater than 0)
 * - Must have at most specified decimal places
 * - Must not exceed maximum value
 *
 * Usage:
 * 'quantity' => ['required', new ValidQuantity(maxDecimals: 4, max: 99999)]
 * 'stock' => ['nullable', new ValidQuantity()]
 */
class ValidQuantity implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  int  $maxDecimals  Maximum number of decimal places (default: 4)
     * @param  float  $min  Minimum allowed value (default: 0.0001)
     * @param  float|null  $max  Maximum allowed value (default: 999999.9999)
     */
    public function __construct(
        protected int $maxDecimals = 4,
        protected float $min = 0.0001,
        protected ?float $max = 999999.9999,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Check if numeric
        if (! is_numeric($value)) {
            $fail(__('The :attribute must be a valid number.'));

            return;
        }

        $numericValue = (float) $value;

        // Check if positive
        if ($numericValue <= 0) {
            $fail(__('The :attribute must be greater than zero.'));

            return;
        }

        // Check minimum value
        if ($numericValue < $this->min) {
            $fail(__('The :attribute must be at least :min.', ['min' => $this->min]));

            return;
        }

        // Check maximum value
        if ($this->max !== null && $numericValue > $this->max) {
            $fail(__('The :attribute must not exceed :max.', ['max' => $this->max]));

            return;
        }

        // Check decimal places
        $valueString = (string) $value;
        if (str_contains($valueString, '.')) {
            $decimalPlaces = strlen(substr($valueString, strpos($valueString, '.') + 1));
            if ($decimalPlaces > $this->maxDecimals) {
                $fail(__('The :attribute must have at most :decimals decimal places.', ['decimals' => $this->maxDecimals]));

                return;
            }
        }
    }
}
