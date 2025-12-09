<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\VehicleModel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class VehicleModels extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $brandFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [
        'brand' => '',
        'model' => '',
        'year_from' => null,
        'year_to' => null,
        'engine_type' => '',
        'category' => '',
        'is_active' => true,
    ];

    protected function rules(): array
    {
        return [
            'form.brand' => ['required', 'string', 'max:100'],
            'form.model' => ['required', 'string', 'max:100'],
            'form.year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'form.year_to' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:form.year_from'],
            'form.engine_type' => ['nullable', 'string', 'max:255'],
            'form.category' => ['nullable', 'string', 'max:100'],
            'form.is_active' => ['boolean'],
        ];
    }

    public function mount(): void
    {
        $this->authorize('spares.compatibility.manage');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingBrandFilter(): void
    {
        $this->resetPage();
    }

    public function openForm(?int $id = null): void
    {
        $this->resetValidation();

        if ($id) {
            $model = VehicleModel::findOrFail($id);
            $this->editingId = $id;
            $this->form = [
                'brand' => $model->brand,
                'model' => $model->model,
                'year_from' => $model->year_from,
                'year_to' => $model->year_to,
                'engine_type' => $model->engine_type ?? '',
                'category' => $model->category ?? '',
                'is_active' => (bool) $model->is_active,
            ];
        } else {
            $this->editingId = null;
            $this->form = [
                'brand' => '',
                'model' => '',
                'year_from' => null,
                'year_to' => null,
                'engine_type' => '',
                'category' => '',
                'is_active' => true,
            ];
        }

        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'brand' => $this->form['brand'],
            'model' => $this->form['model'],
            'year_from' => $this->form['year_from'] ?: null,
            'year_to' => $this->form['year_to'] ?: null,
            'engine_type' => $this->form['engine_type'] ?: null,
            'category' => $this->form['category'] ?: null,
            'is_active' => $this->form['is_active'],
        ];

        if ($this->editingId) {
            VehicleModel::where('id', $this->editingId)->update($data);
            session()->flash('status', __('Vehicle model updated successfully.'));
        } else {
            VehicleModel::create($data);
            session()->flash('status', __('Vehicle model created successfully.'));
        }

        $this->closeForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('spares.compatibility.manage');
        VehicleModel::where('id', $id)->delete();
        session()->flash('status', __('Vehicle model deleted successfully.'));
    }

    public function toggleActive(int $id): void
    {
        $model = VehicleModel::findOrFail($id);
        $model->is_active = ! $model->is_active;
        $model->save();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $query = VehicleModel::query()
            ->withCount('compatibilities');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('brand', 'like', "%{$this->search}%")
                    ->orWhere('model', 'like', "%{$this->search}%");
            });
        }

        if ($this->brandFilter) {
            $query->where('brand', $this->brandFilter);
        }

        $brands = VehicleModel::distinct()->pluck('brand')->sort()->values();

        return view('livewire.inventory.vehicle-models', [
            'models' => $query->orderBy('brand')->orderBy('model')->paginate(15),
            'brands' => $brands,
        ]);
    }
}
