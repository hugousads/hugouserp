<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class ProjectUpdateRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return $this->user()->can('projects.edit');
    }

    public function rules(): array
    {
        $projectId = $this->route('project') ? $this->route('project')->id : 'NULL';

        return [
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:projects,code,' . $projectId],
            'name' => $this->multilingualString(required: false, max: 255), // 'sometimes' handled automatically
            'description' => $this->unicodeText(required: false),
            'client_id' => ['nullable', 'exists:customers,id'],
            'manager_id' => ['sometimes', 'required', 'exists:users,id'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'status' => ['sometimes', 'required', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high,critical'],
            'actual_cost' => ['nullable', 'numeric', 'min:0'],
            'progress_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
