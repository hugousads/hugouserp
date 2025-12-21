<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentShareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('documents.share');
    }

    public function rules(): array
    {
        return [
            'shared_with_user_id' => ['required', 'exists:users,id'],
            'permission' => ['required', 'in:view,download,edit,manage'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'shared_with_user_id.required' => __('Please select a user to share with'),
            'shared_with_user_id.exists' => __('Selected user does not exist'),
            'permission.required' => __('Please select a permission level'),
            'permission.in' => __('Invalid permission level'),
            'expires_at.after' => __('Expiration date must be in the future'),
        ];
    }
}
