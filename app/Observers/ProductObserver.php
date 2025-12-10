<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductObserver
{
    public function creating(Product $product): void
    {
        if (! $product->getAttribute('sku')) {
            $product->sku = strtoupper(Str::random(8));
        }
        if (! $product->getAttribute('barcode')) {
            $product->barcode = 'P'.strtoupper(Str::random(11));
        }
        if ($product->getAttribute('name')) {
            $product->name = trim((string) $product->name);
        }
        if ($product->getAttribute('default_price') !== null) {
            $product->default_price = round((float) $product->default_price, 2);
        }
        if ($product->getAttribute('standard_cost') !== null) {
            $product->standard_cost = round((float) $product->standard_cost, 2);
        }
        if ($product->getAttribute('cost') !== null) {
            $product->cost = round((float) $product->cost, 2);
        }
    }

    public function created(Product $product): void
    {
        $this->audit('created', $product);
    }

    public function updated(Product $product): void
    {
        $changes = $product->getChanges();

        // Normalize numeric fields
        if (array_key_exists('default_price', $changes)) {
            $product->default_price = round((float) $product->default_price, 2);
        }
        if (array_key_exists('standard_cost', $changes)) {
            $product->standard_cost = round((float) $product->standard_cost, 2);
        }
        if (array_key_exists('cost', $changes)) {
            $product->cost = round((float) $product->cost, 2);
        }

        $this->audit('updated', $product, $changes);
    }

    public function deleted(Product $product): void
    {
        $this->audit('deleted', $product);
    }

    protected function audit(string $action, Product $product, array $changes = []): void
    {
        try {
            $req = request();
            AuditLog::create([
                'user_id' => optional(auth()->user())->getKey(),
                'action' => "Product:{$action}",
                'subject_type' => Product::class,
                'subject_id' => $product->getKey(),
                'ip' => $req?->ip(),
                'user_agent' => (string) $req?->userAgent(),
                'old_values' => [],
                'new_values' => $changes ?: $product->attributesToArray(),
            ]);
        } catch (\Throwable $e) {
            // ignore audit failures
        }
    }
}
