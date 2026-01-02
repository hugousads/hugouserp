<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Traits\HasMultilingualValidation;
use Illuminate\Foundation\Http\FormRequest;

class DocumentUpdateRequest extends FormRequest
{
    use HasMultilingualValidation;

    public function authorize(): bool
    {
        return $this->user()->can('documents.edit');
    }

    public function rules(): array
    {
        return [
            'title' => $this->multilingualString(required: false, max: 255), // 'sometimes' handled automatically
            'description' => $this->unicodeText(required: false),
            'folder' => $this->multilingualString(required: false, max: 255),
            'category' => $this->multilingualString(required: false, max: 100),
            'is_public' => ['boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:document_tags,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => __('Document title is required'),
        ];
    }
}
