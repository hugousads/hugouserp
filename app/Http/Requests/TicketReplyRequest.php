<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class TicketReplyRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return $this->user()->can('helpdesk.reply');
    }

    public function rules(): array
    {
        return [
            'message' => $this->unicodeText(required: true, min: 1),
            'is_internal' => ['boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'], // 10MB max per file
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => __('Reply message is required'),
            'message.min' => __('Reply message cannot be empty'),
            'attachments.*.max' => __('Each attachment must be less than 10MB'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set user_id
        $this->merge([
            'user_id' => $this->user()->id,
            'created_by' => $this->user()->id,
        ]);

        // Set default is_internal to false if not provided
        if (!$this->has('is_internal')) {
            $this->merge([
                'is_internal' => false,
            ]);
        }
    }
}
