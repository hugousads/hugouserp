<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class ProjectTaskRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return $this->user()->can('projects.tasks.manage');
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'milestone_id' => ['nullable', 'exists:project_milestones,id'],
            'title' => $this->multilingualString(required: true, max: 255),
            'description' => $this->unicodeText(required: false),
            'assigned_to' => ['nullable', 'exists:users,id'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['required', 'in:low,medium,high,critical'],
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'actual_hours' => ['nullable', 'numeric', 'min:0'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }

        if (!$this->has('priority')) {
            $this->merge([
                'priority' => 'medium',
            ]);
        }
    }
}
