<?php

declare(strict_types=1);

namespace App\Livewire\Manufacturing\WorkCenters;

use App\Models\Branch;
use App\Models\WorkCenter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?WorkCenter $workCenter = null;

    public bool $editMode = false;

    public string $code = '';

    public string $name = '';

    public string $name_ar = '';

    public string $description = '';

    public string $type = 'manual';

    public ?float $capacity_per_hour = null;

    public float $cost_per_hour = 0.0;

    public string $status = 'active';

    protected function rules(): array
    {
        $workCenterId = $this->workCenter?->id;

        return [
            'code' => ['required', 'string', 'max:50', 'unique:work_centers,code,'.$workCenterId],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:manual,machine,assembly,quality_control,packaging'],
            'capacity_per_hour' => ['nullable', 'numeric', 'min:0'],
            'cost_per_hour' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,maintenance,inactive'],
        ];
    }

    public function mount(?WorkCenter $workCenter = null): void
    {
        if ($workCenter && $workCenter->exists) {
            $this->authorize('manufacturing.edit');
            $this->workCenter = $workCenter;
            $this->editMode = true;
            $this->fillFormFromModel();
        } else {
            $this->authorize('manufacturing.create');
        }
    }

    protected function fillFormFromModel(): void
    {
        $this->code = $this->workCenter->code;
        $this->name = $this->workCenter->name;
        $this->name_ar = $this->workCenter->name_ar ?? '';
        $this->description = $this->workCenter->description ?? '';
        $this->type = $this->workCenter->type;
        $this->capacity_per_hour = $this->workCenter->capacity_per_hour ? (float) $this->workCenter->capacity_per_hour : null;
        $this->cost_per_hour = (float) $this->workCenter->cost_per_hour;
        $this->status = $this->workCenter->status;
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        $branchId = $user->branch_id ?? Branch::first()?->id;
        
        if (!$branchId) {
            session()->flash('error', __('No branch available. Please contact your administrator.'));
            return;
        }

        $data = [
            'branch_id' => $branchId,
            'code' => $this->code,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'type' => $this->type,
            'capacity_per_hour' => $this->capacity_per_hour,
            'cost_per_hour' => $this->cost_per_hour,
            'status' => $this->status,
        ];

        if ($this->editMode) {
            $this->workCenter->update($data);
            session()->flash('message', __('Work Center updated successfully.'));
        } else {
            WorkCenter::create($data);
            session()->flash('message', __('Work Center created successfully.'));
        }

        $this->redirect(route('app.manufacturing.work-centers.index'), navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.manufacturing.work-centers.form');
    }
}
