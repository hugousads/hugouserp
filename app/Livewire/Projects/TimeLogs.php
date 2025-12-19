<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTimeLog;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class TimeLogs extends Component
{
    use AuthorizesRequests, WithPagination;

    public Project $project;
    public ?ProjectTimeLog $editingLog = null;
    public bool $showModal = false;

    // Form fields
    public ?int $task_id = null;
    public ?int $employee_id = null;
    public ?string $date = null;
    public float $hours = 0;
    public bool $is_billable = true;
    public float $hourly_rate = 0;
    public ?string $description = null;

    public function mount(int $projectId): void
    {
        $this->authorize('projects.timelogs.view');
        $this->project = Project::findOrFail($projectId);
        $this->date = now()->format('Y-m-d');
        $this->employee_id = auth()->id();
    }

    public function rules(): array
    {
        return [
            'task_id' => ['nullable', 'exists:project_tasks,id'],
            'employee_id' => ['required', 'exists:users,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.1', 'max:24'],
            'is_billable' => ['boolean'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function createLog(): void
    {
        $this->authorize('projects.timelogs.manage');
        $this->resetForm();
        $this->editingLog = null;
        $this->showModal = true;
    }

    public function editLog(int $id): void
    {
        $this->authorize('projects.timelogs.manage');
        $this->editingLog = $this->project->timeLogs()->findOrFail($id);
        $this->fill($this->editingLog->only([
            'task_id', 'employee_id', 'hours',
            'is_billable', 'hourly_rate', 'description'
        ]));
        $this->date = $this->editingLog->log_date?->format('Y-m-d')
            ?? $this->editingLog->date?->format('Y-m-d');
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('projects.timelogs.manage');
        $this->validate();

        $data = $this->only([
            'task_id', 'employee_id', 'date', 'hours',
            'is_billable', 'hourly_rate', 'description'
        ]);

        $logDate = $data['date'] ?? now()->format('Y-m-d');
        $data['user_id'] = $data['employee_id'] ?? auth()->id();
        $data['log_date'] = $logDate;
        $data['date'] = $logDate;
        $data['billable'] = $data['is_billable'];

        if ($this->editingLog) {
            $this->editingLog->update($data);
        } else {
            $this->project->timeLogs()->create($data);
        }

        session()->flash('success', __('Time log saved successfully'));
        $this->resetForm();
        $this->editingLog = null;
        $this->showModal = false;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingLog = null;
        $this->resetForm();
    }

    public function deleteLog(int $id): void
    {
        $this->authorize('projects.timelogs.manage');
        $log = $this->project->timeLogs()->findOrFail($id);
        $log->delete();
        session()->flash('success', __('Time log deleted successfully'));
    }

    public function resetForm(): void
    {
        $this->reset([
            'task_id', 'hours', 'hourly_rate', 'description'
        ]);
        $this->date = now()->format('Y-m-d');
        $this->employee_id = auth()->id();
        $this->is_billable = true;
    }

    public function render()
    {
        $timeLogs = $this->project->timeLogs()
            ->with(['task', 'employee', 'user'])
            ->orderByRaw('COALESCE(log_date, date) desc')
            ->paginate(15);

        $tasks = $this->project->tasks()->orderBy('title')->get();
        $employees = User::orderBy('name')->get();

        $totalHours = $this->project->timeLogs()->sum('hours');

        // Statistics
        $stats = [
            'total_hours' => $totalHours,
            'billable_hours' => $this->project->timeLogs()->billable()->sum('hours'),
            'non_billable_hours' => $this->project->timeLogs()->nonBillable()->sum('hours'),
            'total_cost' => $this->project->timeLogs()->get()->sum(fn($log) => $log->getCost()),
        ];

        return view('livewire.projects.time-logs', [
            'timeLogs' => $timeLogs,
            'tasks' => $tasks,
            'employees' => $employees,
            'totalHours' => $totalHours,
            'stats' => $stats,
        ]);
    }
}
