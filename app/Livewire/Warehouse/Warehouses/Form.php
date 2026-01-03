<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Warehouses;

use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?int $warehouseId = null;

    public string $name = '';

    public string $code = '';

    public string $type = 'main';

    public string $status = 'active';

    public string $address = '';

    public string $notes = '';

    public bool $overrideCode = false;

    public function mount(?int $warehouse = null): void
    {
        $this->authorize('warehouse.manage');

        if ($warehouse) {
            $this->warehouseId = $warehouse;
            $this->loadWarehouse();
            $this->overrideCode = true; // When editing, code is already set
        }
    }

    protected function loadWarehouse(): void
    {
        $warehouse = Warehouse::findOrFail($this->warehouseId);
        $this->name = $warehouse->name;
        $this->code = $warehouse->code ?? '';
        $this->type = $warehouse->type ?? 'main';
        $this->status = $warehouse->status ?? 'active';
        $this->address = $warehouse->address ?? '';
        $this->notes = $warehouse->notes ?? '';
    }

    public function updatedName(): void
    {
        // Auto-generate code from name if not overriding and creating new
        if (! $this->overrideCode && ! $this->warehouseId) {
            $this->code = $this->generateCode();
        }
    }

    protected function generateCode(): string
    {
        $prefix = 'WH';
        $base = strtoupper(Str::slug(Str::limit($this->name, 10, ''), ''));
        
        if (empty($base)) {
            $base = sprintf('%03d', Warehouse::count() + 1);
        }
        
        $code = $prefix . '-' . $base;
        $counter = 1;

        while (Warehouse::where('code', $code)->where('id', '!=', $this->warehouseId)->exists()) {
            $code = $prefix . $base . $counter;
            $counter++;
        }

        return $code;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:50',
            'status' => 'required|in:active,inactive',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public function save(): mixed
    {
        $this->authorize('warehouse.manage');
        $this->validate();

        $user = auth()->user();

        // Auto-generate code if empty
        if (empty($this->code)) {
            $this->code = $this->generateCode();
        }

        $data = [
            'name' => $this->name,
            'code' => $this->code ?: null,
            'type' => $this->type,
            'status' => $this->status,
            'address' => $this->address ?: null,
            'notes' => $this->notes ?: null,
            'branch_id' => $user?->branch_id,
        ];

        try {
            if ($this->warehouseId) {
                $warehouse = Warehouse::findOrFail($this->warehouseId);
                $data['updated_by'] = $user?->id;
                $warehouse->update($data);
                session()->flash('success', __('Warehouse updated successfully'));
            } else {
                $data['created_by'] = $user?->id;
                Warehouse::create($data);
                session()->flash('success', __('Warehouse created successfully'));
            }

            Cache::forget('warehouse_stats_'.($user?->branch_id ?? 'all'));
            Cache::forget('all_warehouses_'.($user?->branch_id ?? 'all'));

            return $this->redirectRoute('app.warehouse.index', navigate: true);
        } catch (\Exception $e) {
            $this->addError('name', __('Failed to save warehouse. Please try again.'));
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.warehouse.warehouses.form');
    }
}
