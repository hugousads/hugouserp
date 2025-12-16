<?php

declare(strict_types=1);

namespace App\Livewire\Hrm\Shifts;

use App\Models\Shift;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';
    public ?string $status = null;
    public ?int $branchId = null;

    // Modal state
    public bool $showModal = false;
    public ?int $editingId = null;

    // Form fields
    public string $name = '';
    public string $code = '';
    public string $startTime = '09:00';
    public string $endTime = '17:00';
    public int $gracePeriodMinutes = 15;
    public array $workingDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    public string $description = '';
    public bool $isActive = true;

    protected array $daysOfWeek = [
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->can('hrm.view')) {
            abort(403);
        }

        $this->branchId = $user->branch_id;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function openModal(?int $id = null): void
    {
        $this->authorize('hrm.manage');
        $this->resetForm();

        if ($id) {
            $shift = Shift::findOrFail($id);
            $this->editingId = $id;
            $this->name = $shift->name ?? '';
            $this->code = $shift->code ?? '';
            $this->startTime = $shift->start_time ?? '09:00';
            $this->endTime = $shift->end_time ?? '17:00';
            $this->gracePeriodMinutes = $shift->grace_period_minutes ?? 15;
            $this->workingDays = $shift->working_days ?? [];
            $this->description = $shift->description ?? '';
            $this->isActive = $shift->is_active ?? true;
        }

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
        $this->code = '';
        $this->startTime = '09:00';
        $this->endTime = '17:00';
        $this->gracePeriodMinutes = 15;
        $this->workingDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $this->description = '';
        $this->isActive = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('hrm.manage');

        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:shifts,code' . ($this->editingId ? ',' . $this->editingId : ''),
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'gracePeriodMinutes' => 'required|integer|min:0|max:120',
            'workingDays' => 'array',
            'description' => 'nullable|string|max:1000',
        ];

        $this->validate($rules);

        // Ensure branch_id is set from authenticated user
        $branchId = auth()->user()?->branch_id ?? $this->branchId;

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'grace_period_minutes' => $this->gracePeriodMinutes,
            'working_days' => $this->workingDays,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'branch_id' => $branchId,
        ];

        if ($this->editingId) {
            Shift::findOrFail($this->editingId)->update($data);
            session()->flash('success', __('Shift updated successfully'));
        } else {
            Shift::create($data);
            session()->flash('success', __('Shift created successfully'));
        }

        $this->closeModal();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('hrm.manage');
        $shift = Shift::findOrFail($id);
        $shift->update(['is_active' => !$shift->is_active]);
    }

    public function delete(int $id): void
    {
        $this->authorize('hrm.manage');
        Shift::findOrFail($id)->delete();
        session()->flash('success', __('Shift deleted successfully'));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();

        if (! $user || ! $user->can('hrm.view')) {
            abort(403);
        }

        $query = Shift::query()
            ->with(['branch'])
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term);
                });
            })
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');

        $shifts = $query->paginate(20);

        return view('livewire.hrm.shifts.index', [
            'shifts' => $shifts,
            'daysOfWeek' => $this->daysOfWeek,
        ]);
    }
}
