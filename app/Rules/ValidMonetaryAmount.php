<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ValidMonetaryAmount Rule
 *
 * Validates that a value is a valid monetary amount:
 * - Must be numeric
 * - Must be non-negative (unless explicitly allowed)
 * - Must have at most specified decimal places
 * - Must not exceed maximum value
 *
 * Usage:
 * 'price' => ['required', new ValidMonetaryAmount(maxDecimals: 4, max: 9999999.99)]
 * 'discount' => ['nullable', new ValidMonetaryAmount(allowNegative: false)]
 */
class ValidMonetaryAmount implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param  int  $maxDecimals  Maximum number of decimal places (default: 4)
     * @param  float|null  $min  Minimum allowed value (default: 0)
     * @param  float|null  $max  Maximum allowed value (default: 99999999.9999)
     * @param  bool  $allowNegative  Whether to allow negative values (default: false)
     */
    public function __construct(
        protected int $maxDecimals = 4,
        protected ?float $min = 0,
        protected ?float $max = 99999999.9999,
        protected bool $allowNegative = false,
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

        // Check for negative if not allowed
        if (! $this->allowNegative && $numericValue < 0) {
            $fail(__('The :attribute cannot be negative.'));

            return;
        }

        // Check minimum value
        if ($this->min !== null && $numericValue < $this->min) {
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
