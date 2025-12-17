<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'tax_number' => $this->tax_number,
            'credit_limit' => $this->when(
                $request->user()?->can('customers.view-financial'),
                (float) $this->credit_limit
            ),
            'current_balance' => $this->when(
                $request->user()?->can('customers.view-financial'),
                (float) $this->current_balance
            ),
            'is_active' => $this->is_active,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'sales_count' => $this->when(
                $request->user()?->can('customers.view-sales'),
                $this->whenCounted('sales')
            ),
            'total_purchases' => $this->when(
                $request->user()?->can('customers.view-financial') && $this->relationLoaded('sales'),
                fn () => $this->sales->sum('grand_total')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
