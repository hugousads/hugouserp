<?php

declare(strict_types=1);

namespace App\Livewire\Hrm\Shifts;

use App\Http\Requests\Traits\HasMultilingualValidation;
use App\Models\Shift;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;
    use HasMultilingualValidation;

    public ?int $shiftId = null;

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

    public function mount(?int $shift = null): void
    {
        $this->authorize('hrm.manage');

        if ($shift) {
            $this->shiftId = $shift;
            $this->loadShift();
        }
    }

    protected function loadShift(): void
    {
        $shift = Shift::findOrFail($this->shiftId);

        $this->name = $shift->name ?? '';
        $this->code = $shift->code ?? '';
        $this->startTime = $shift->start_time ?? '09:00';
        $this->endTime = $shift->end_time ?? '17:00';
        $this->gracePeriodMinutes = $shift->grace_period_minutes ?? 15;
        $this->workingDays = $shift->working_days ?? [];
        $this->description = $shift->description ?? '';
        $this->isActive = $shift->is_active ?? true;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:shifts,code'.($this->shiftId ? ','.$this->shiftId : ''),
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'gracePeriodMinutes' => 'required|integer|min:0|max:120',
            'workingDays' => 'array',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function save(): void
    {
        $this->authorize('hrm.manage');

        $this->validate();

        $branchId = auth()->user()?->branch_id ?? null;

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

        if ($this->shiftId) {
            Shift::findOrFail($this->shiftId)->update($data);
            session()->flash('success', __('Shift updated successfully'));
        } else {
            Shift::create($data);
            session()->flash('success', __('Shift created successfully'));
        }

        $this->redirectRoute('app.hrm.shifts.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.hrm.shifts.form', [
            'daysOfWeek' => $this->daysOfWeek,
        ]);
    }
}
