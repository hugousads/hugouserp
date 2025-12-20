<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\Module;
use App\Models\Product;
use App\Models\Tax;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ServiceProductForm extends Component
{
    public ?int $productId = null;

    public ?int $moduleId = null;

    public string $name = '';

    public string $code = '';

    public string $sku = '';

    public ?string $description = null;

    public float $defaultPrice = 0;

    public float $cost = 0;

    public ?float $hourlyRate = null;

    public ?int $serviceDuration = null;

    public string $durationUnit = 'hours';

    public ?int $taxId = null;

    public string $status = 'active';

    public string $notes = '';

    public bool $showModal = false;

    protected $listeners = [
        'openServiceForm' => 'open',
        'editService' => 'edit',
    ];

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'sku' => 'nullable|string|max:50',
            'defaultPrice' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'hourlyRate' => 'nullable|numeric|min:0',
            'serviceDuration' => 'nullable|integer|min:1',
            'durationUnit' => 'required|in:minutes,hours,days',
            'taxId' => 'nullable|exists:taxes,id',
            'status' => 'required|in:active,inactive',
        ];
    }

    /**
     * Check if the user has the required permission for the action.
     */
    protected function authorizeAction(string $action): void
    {
        $user = Auth::user();
        
        if (! $user) {
            abort(403, __('Unauthorized'));
        }

        $permission = match ($action) {
            'create' => 'inventory.products.create',
            'update' => 'inventory.products.update',
            'view' => 'inventory.products.view',
            default => null,
        };

        if ($permission && ! $user->can($permission)) {
            abort(403, __('Unauthorized'));
        }
    }

    /**
     * Verify the user has a valid branch assignment.
     */
    protected function requireUserBranch(): int
    {
        $user = Auth::user();
        
        if (! $user || ! $user->branch_id) {
            abort(403, __('User must be assigned to a branch to perform this action'));
        }

        return $user->branch_id;
    }

    /**
     * Verify the product belongs to the user's branch.
     */
    protected function authorizeProductBranch(Product $product): void
    {
        $userBranchId = $this->requireUserBranch();
        
        if ($product->branch_id !== $userBranchId) {
            abort(403, __('Access denied to product from another branch'));
        }
    }

    /**
     * Validate and return a service module for the user's branch.
     */
    protected function validateServiceModule(?int $moduleId): ?int
    {
        if (! $moduleId) {
            return null;
        }

        $module = Module::where('id', $moduleId)
            ->where(function ($query) {
                $query->where('is_service', true)
                    ->orWhere('key', 'services');
            })
            ->first();

        if (! $module) {
            abort(422, __('Invalid or non-service module'));
        }

        return $module->id;
    }

    public function render()
    {
        $modules = Module::where(function ($query) {
            $query->where('is_service', true)
                ->orWhere('key', 'services');
        })
            ->orderBy('name')
            ->get();

        $taxes = Tax::where('is_active', true)->orderBy('name')->get();

        return view('livewire.inventory.service-product-form', [
            'modules' => $modules,
            'taxes' => $taxes,
        ]);
    }

    public function open(?int $moduleId = null): void
    {
        $this->authorizeAction('create');
        $this->requireUserBranch();
        
        $this->resetForm();
        $this->moduleId = $this->validateServiceModule($moduleId);
        $this->showModal = true;
    }

    public function edit(int $productId): void
    {
        $this->authorizeAction('update');
        $this->requireUserBranch();
        
        $product = Product::find($productId);
        if (! $product) {
            abort(404, __('Product not found'));
        }

        $this->authorizeProductBranch($product);
        
        $this->productId = $product->id;
        $this->moduleId = $product->module_id;
        $this->name = $product->name;
        $this->code = $product->code ?? '';
        $this->sku = $product->sku ?? '';
        $this->defaultPrice = (float) $product->default_price;
        $this->cost = (float) ($product->cost ?: $product->standard_cost);
        $this->hourlyRate = $product->hourly_rate;
        $this->serviceDuration = $product->service_duration;
        $this->durationUnit = $product->duration_unit ?? 'hours';
        $this->taxId = $product->tax_id;
        $this->status = $product->status;
        $this->notes = $product->notes ?? '';
        $this->showModal = true;
    }

    public function resetForm(): void
    {
        $this->productId = null;
        $this->name = '';
        $this->code = '';
        $this->sku = '';
        $this->description = null;
        $this->defaultPrice = 0;
        $this->cost = 0;
        $this->hourlyRate = null;
        $this->serviceDuration = null;
        $this->durationUnit = 'hours';
        $this->taxId = null;
        $this->status = 'active';
        $this->notes = '';
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $userBranchId = $this->requireUserBranch();
        
        if ($this->productId) {
            $this->authorizeAction('update');
        } else {
            $this->authorizeAction('create');
        }

        $this->validate();
        
        // Validate module_id is a valid service module
        $validatedModuleId = $this->validateServiceModule($this->moduleId);

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'sku' => $this->sku ?: null,
            'module_id' => $validatedModuleId,
            'type' => 'service',
            'product_type' => 'service',
            'default_price' => $this->defaultPrice,
            'standard_cost' => $this->cost,
            'cost' => $this->cost,
            'hourly_rate' => $this->hourlyRate,
            'service_duration' => $this->serviceDuration,
            'duration_unit' => $this->durationUnit,
            'tax_id' => $this->taxId,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
            'is_serialized' => false,
            'is_batch_tracked' => false,
        ];

        if ($this->productId) {
            $product = Product::find($this->productId);
            if (! $product) {
                abort(404, __('Product not found'));
            }
            
            // Re-verify branch ownership before update
            $this->authorizeProductBranch($product);
            
            // Force branch_id to remain the same (no cross-branch transfer)
            $product->update($data);
            $this->dispatch('notify', type: 'success', message: __('Service updated successfully'));
        } else {
            $data['created_by'] = Auth::id();
            
            // Create new product - branch_id is guarded so it must be set explicitly
            // after instantiation to enforce branch scoping security
            $product = new Product($data);
            $product->branch_id = $userBranchId;
            $product->save();
            
            $this->dispatch('notify', type: 'success', message: __('Service created successfully'));
        }

        $this->dispatch('serviceUpdated');
        $this->close();
    }

    public function calculateFromHourly(): void
    {
        if ($this->hourlyRate && $this->serviceDuration) {
            $hours = match ($this->durationUnit) {
                'minutes' => bcdiv((string) $this->serviceDuration, '60', 4),
                'hours' => (string) $this->serviceDuration,
                'days' => bcmul((string) $this->serviceDuration, '8', 4),
                default => (string) $this->serviceDuration,
            };
            $calculated = bcmul((string) $this->hourlyRate, $hours, 4);
            $this->defaultPrice = (float) bcdiv($calculated, '1', 2);
        }
    }
}
