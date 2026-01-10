<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tax;
use App\Services\Contracts\TaxServiceInterface;
use App\Traits\HandlesServiceErrors;

class TaxService implements TaxServiceInterface
{
    use HandlesServiceErrors;

    public function rate(?int $taxId): float
    {
        if (! $taxId || ! class_exists(Tax::class)) {
            return 0.0;
        }
        $tax = Tax::find($taxId);

        return (float) ($tax->rate ?? 0.0);
    }

    public function compute(float $base, ?int $taxId): float
    {
        $r = $this->rate($taxId);

        // BUG FIX #5: Use bcmath for precise tax calculation with line-level rounding (2 decimals)
        $rateDecimal = bcdiv((string) $r, '100', 6);
        $taxAmount = bcmul((string) $base, $rateDecimal, 4);

        // Round to 2 decimal places at line level for e-invoicing compliance
        return (float) bcdiv($taxAmount, '1', 2);
    }

    public function amountFor(float $base, ?int $taxId): float
    {
        return $this->handleServiceOperation(
            callback: function () use ($base, $taxId) {
                if (! $taxId || ! class_exists(Tax::class)) {
                    return 0.0;
                }

                $tax = Tax::find($taxId);
                if (! $tax) {
                    return 0.0;
                }

                $rate = (float) $tax->rate;

                if ($rate <= 0) {
                    return 0.0;
                }

                if ($tax->is_inclusive ?? false) {
                    // Use bcmath for precise inclusive tax calculation
                    $divisor = bcadd('1', bcdiv((string) $rate, '100', 6), 6);
                    $baseExcl = bcdiv((string) $base, $divisor, 6);
                    $taxPortion = bcsub((string) $base, $baseExcl, 6);

                    return (float) bcdiv($taxPortion, '1', 4);
                }

                // Use bcmath for precise tax calculation
                $taxAmount = bcmul((string) $base, bcdiv((string) $rate, '100', 6), 6);

                return (float) bcdiv($taxAmount, '1', 4);
            },
            operation: 'amountFor',
            context: ['base' => $base, 'tax_id' => $taxId],
            defaultValue: 0.0
        );
    }

    public function totalWithTax(float $base, ?int $taxId): float
    {
        return $this->handleServiceOperation(
            callback: function () use ($base, $taxId) {
                if (! $taxId || ! class_exists(Tax::class)) {
                    return (float) bcdiv((string) $base, '1', 4);
                }

                $tax = Tax::find($taxId);
                if (! $tax) {
                    return (float) bcdiv((string) $base, '1', 4);
                }

                if ($tax->is_inclusive ?? false) {
                    return (float) bcdiv((string) $base, '1', 4);
                }

                // Use bcmath for precise total calculation
                $taxAmount = $this->amountFor($base, $taxId);
                $total = bcadd((string) $base, (string) $taxAmount, 6);

                return (float) bcdiv($total, '1', 4);
            },
            operation: 'totalWithTax',
            context: ['base' => $base, 'tax_id' => $taxId],
            defaultValue: (float) bcdiv((string) $base, '1', 4)
        );
    }
}
