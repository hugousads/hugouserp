<?php

declare(strict_types=1);

namespace App\Livewire\Admin\UnitsOfMeasure;

use App\Models\UnitOfMeasure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $nameAr = '';

    public string $symbol = '';

    public string $type = 'unit';

    public ?int $baseUnitId = null;

    public float $conversionFactor = 1;

    public int $decimalPlaces = 2;

    public bool $isBaseUnit = false;

    public bool $isActive = true;

    public int $sortOrder = 0;

    protected $queryString = ['search'];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('inventory.units.view')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedIsBaseUnit(): void
    {
        if ($this->isBaseUnit) {
            $this->baseUnitId = null;
            $this->conversionFactor = 1;
        }
    }

    public function render()
    {
        $units = UnitOfMeasure::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('name_ar', 'like', "%{$this->search}%")
                ->orWhere('symbol', 'like', "%{$this->search}%"))
            ->with('baseUnit')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        $baseUnits = UnitOfMeasure::baseUnits()->active()->orderBy('name')->get();

        $unitTypes = [
            'unit' => __('Unit'),
            'weight' => __('Weight'),
            'length' => __('Length'),
            'volume' => __('Volume'),
            'area' => __('Area'),
            'time' => __('Time'),
            'other' => __('Other'),
        ];

        return view('livewire.admin.units-of-measure.index', [
            'units' => $units,
            'baseUnits' => $baseUnits,
            'unitTypes' => $unitTypes,
        ]);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->sortOrder = UnitOfMeasure::max('sort_order') + 1;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->nameAr = '';
        $this->symbol = '';
        $this->type = 'unit';
        $this->baseUnitId = null;
        $this->conversionFactor = 1;
        $this->decimalPlaces = 2;
        $this->isBaseUnit = false;
        $this->isActive = true;
        $this->sortOrder = 0;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $unit = UnitOfMeasure::find($id);
        if ($unit) {
            $this->editingId = $id;
            $this->name = $unit->name;
            $this->nameAr = $unit->name_ar ?? '';
            $this->symbol = $unit->symbol;
            $this->type = $unit->type;
            $this->baseUnitId = $unit->base_unit_id;
            $this->conversionFactor = (float) $unit->conversion_factor;
            $this->decimalPlaces = $unit->decimal_places;
            $this->isBaseUnit = $unit->is_base_unit;
            $this->isActive = $unit->is_active;
            $this->sortOrder = $unit->sort_order;
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->editingId 
                    ? Rule::unique('units_of_measure', 'name')->ignore($this->editingId) 
                    : Rule::unique('units_of_measure', 'name'),
            ],
            'nameAr' => 'nullable|string|max:255',
            'symbol' => [
                'required',
                'string',
                'max:20',
                $this->editingId 
                    ? Rule::unique('units_of_measure', 'symbol')->ignore($this->editingId) 
                    : Rule::unique('units_of_measure', 'symbol'),
            ],
            'type' => 'required|string|in:unit,weight,length,volume,area,time,other',
            'baseUnitId' => 'nullable|exists:units_of_measure,id',
            'conversionFactor' => 'required|numeric|min:0.000001',
            'decimalPlaces' => 'integer|min:0|max:6',
            'sortOrder' => 'integer|min:0',
        ];

        $this->validate($rules);

        $user = Auth::user();

        $data = [
            'name' => $this->name,
            'name_ar' => $this->nameAr ?: null,
            'symbol' => $this->symbol,
            'type' => $this->type,
            'base_unit_id' => $this->isBaseUnit ? null : $this->baseUnitId,
            'conversion_factor' => $this->isBaseUnit ? 1 : $this->conversionFactor,
            'decimal_places' => $this->decimalPlaces,
            'is_base_unit' => $this->isBaseUnit,
            'is_active' => $this->isActive,
            'sort_order' => $this->sortOrder,
            'updated_by' => $user?->id,
        ];

        try {
            if ($this->editingId) {
                $unit = UnitOfMeasure::findOrFail($this->editingId);
                $unit->update($data);
                session()->flash('success', __('Unit updated successfully'));
            } else {
                $data['created_by'] = $user?->id;
                UnitOfMeasure::create($data);
                session()->flash('success', __('Unit created successfully'));
            }

            $this->closeModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->addError('name', __('Failed to save unit. Please try again.'));
        }
    }

    public function delete(int $id): void
    {
        $unit = UnitOfMeasure::find($id);
        if ($unit) {
            if ($unit->products()->count() > 0) {
                session()->flash('error', __('Cannot delete unit with products'));

                return;
            }
            if ($unit->derivedUnits()->count() > 0) {
                session()->flash('error', __('Cannot delete base unit with derived units'));

                return;
            }
            $unit->delete();
            session()->flash('success', __('Unit deleted successfully'));
        }
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('inventory.units.manage');
        
        $unit = UnitOfMeasure::find($id);
        if ($unit) {
            $unit->update(['is_active' => ! $unit->is_active]);
        }
    }
}
