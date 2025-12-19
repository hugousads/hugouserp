<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Tasks extends Component
{
    use AuthorizesRequests;

    public Project $project;
    public ?ProjectTask $editingTask = null;

    // Form fields
    public string $name = '';
    public ?string $description = null;
    public ?int $assigned_to = null;
    public ?int $parent_task_id = null;
    public string $priority = 'medium';
    public string $status = 'pending';
    public ?string $start_date = null;
    public ?string $due_date = null;
    public float $estimated_hours = 0;
    public int $progress = 0;

    public array $selectedDependencies = [];

    public function mount(int $projectId): void
    {
        $this->authorize('projects.tasks.view');
        $this->project = Project::findOrFail($projectId);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'parent_task_id' => ['nullable', 'exists:project_tasks,id'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'in:pending,in_progress,review,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['required', 'numeric', 'min:0'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function createTask(): void
    {
        $this->authorize('projects.tasks.manage');
        $this->resetForm();
        $this->editingTask = null;
    }

    public function editTask(int $id): void
    {
        $this->authorize('projects.tasks.manage');
        $this->editingTask = ProjectTask::findOrFail($id);
        $this->fill($this->editingTask->only([
            'name', 'description', 'assigned_to', 'parent_task_id',
            'priority', 'status', 'start_date', 'due_date',
            'estimated_hours', 'progress'
        ]));
        $this->parent_task_id = $this->editingTask->parent_id;
        $this->selectedDependencies = $this->editingTask->dependencies()->pluck('dependency_id')->toArray();
    }

    public function save(): void
    {
        $this->authorize('projects.tasks.manage');
        $this->validate();

        $data = $this->only([
            'name', 'description', 'assigned_to', 'parent_task_id',
            'priority', 'status', 'start_date', 'due_date',
            'estimated_hours', 'progress'
        ]);

        $data['parent_id'] = $data['parent_task_id'];
        unset($data['parent_task_id']);

        if ($this->editingTask) {
            $this->editingTask->update($data);
            $task = $this->editingTask;
        } else {
            $task = $this->project->tasks()->create(array_merge(
                $data,
                ['created_by' => auth()->id()]
            ));
        }

        // Sync dependencies
        $task->dependencies()->sync($this->selectedDependencies);

        session()->flash('success', __('Task saved successfully'));
        $this->resetForm();
        $this->editingTask = null;
    }

    public function deleteTask(int $id): void
    {
        $this->authorize('projects.tasks.manage');
        $task = ProjectTask::findOrFail($id);
        $task->delete();
        session()->flash('success', __('Task deleted successfully'));
    }

    public function resetForm(): void
    {
        $this->reset([
            'name', 'description', 'assigned_to', 'parent_task_id',
            'priority', 'status', 'start_date', 'due_date',
            'estimated_hours', 'progress', 'selectedDependencies'
        ]);
    }

    public function render()
    {
        $tasks = $this->project->tasks()
            ->with(['assignedTo', 'parentTask', 'dependencies'])
            ->orderBy('created_at', 'desc')
            ->get();

        $users = User::orderBy('name')->get();
        $availableTasks = $this->project->tasks()
            ->when($this->editingTask, fn($q) => $q->where('id', '!=', $this->editingTask->id))
            ->orderBy('name')
            ->get();

        return view('livewire.projects.tasks', [
            'tasks' => $tasks,
            'users' => $users,
            'availableTasks' => $availableTasks,
        ]);
    }
}
