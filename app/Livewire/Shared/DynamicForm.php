<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use Livewire\Component;
use Livewire\WithFileUploads;

class DynamicForm extends Component
{
    use WithFileUploads;

    public array $schema = [];

    public array $data = [];

    public string $submitLabel = '';

    public string $cancelLabel = '';

    public ?string $cancelRoute = null;

    public bool $showCancel = true;

    public string $layout = 'vertical';

    public int $columns = 1;

    public bool $loading = false;

    private array $defaultFileMimes = [
        'pdf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    protected $listeners = ['resetForm' => 'resetFormData'];

    public function mount(
        array $schema = [],
        array $data = [],
        string $submitLabel = '',
        string $cancelLabel = '',
        ?string $cancelRoute = null,
        bool $showCancel = true,
        string $layout = 'vertical',
        int $columns = 1
    ): void {
        $this->schema = $schema;
        $this->submitLabel = $submitLabel ?: __('Save');
        $this->cancelLabel = $cancelLabel ?: __('Cancel');
        $this->cancelRoute = $cancelRoute;
        $this->showCancel = $showCancel;
        $this->layout = $layout;
        $this->columns = $columns;

        foreach ($this->schema as $field) {
            $name = $field['name'] ?? '';
            if ($name && ! isset($data[$name])) {
                $data[$name] = $field['default'] ?? '';
            }
        }
        $this->data = $data;
    }

    public function updated($propertyName): void
    {
        $this->dispatch('dynamic-form-updated', data: $this->data);
    }

    public function submit(): void
    {
        $this->loading = true;

        try {
            $rules = $this->buildValidationRules();
            if (! empty($rules)) {
                $this->validate($rules);
            }

            $this->processFileUploads();

            $this->dispatch('formSubmitted', data: $this->data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('formError', errors: $e->errors());
            throw $e;
        } finally {
            $this->loading = false;
        }
    }

    protected function processFileUploads(): void
    {
        foreach ($this->schema as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';

            if ($type === 'file' && $name && isset($this->data[$name])) {
                $file = $this->data[$name];
                if ($file && method_exists($file, 'store')) {
                    $disk = $field['disk'] ?? 'local';
                    $path = $file->store('dynamic-uploads', $disk);
                    $this->data[$name] = $path;
                }
            }
        }
    }

    public function resetFormData(): void
    {
        foreach ($this->schema as $field) {
            $name = $field['name'] ?? '';
            if ($name) {
                $this->data[$name] = $field['default'] ?? '';
            }
        }
    }

    protected function buildValidationRules(): array
    {
        $rules = [];
        foreach ($this->schema as $field) {
            $name = $field['name'] ?? '';
            if (!$name) {
                continue;
            }

            $fieldRules = $this->normalizeRules($field['rules'] ?? []);

            if (($field['type'] ?? 'text') === 'file') {
                $fieldRules = $this->augmentFileRules($fieldRules, $field);
            }

            if (! empty($fieldRules)) {
                $rules["data.{$name}"] = $fieldRules;
            }
        }

        return $rules;
    }

    private function normalizeRules(string|array $rules): array
    {
        if (is_string($rules)) {
            return array_filter(explode('|', $rules));
        }

        return $rules;
    }

    private function augmentFileRules(array $rules, array $field): array
    {
        $rules[] = 'file';
        $maxSize = $field['max'] ?? 10240;
        $rules[] = "max:{$maxSize}";

        $mimes = $field['mimes'] ?? $this->defaultFileMimes;
        if (! empty($mimes)) {
            $rules[] = 'mimes:' . implode(',', $mimes);
        }

        return array_values(array_unique($rules));
    }

    protected function validationAttributes(): array
    {
        $attributes = [];
        foreach ($this->schema as $field) {
            $name = $field['name'] ?? '';
            if ($name) {
                $attributes["data.{$name}"] = $field['label'] ?? $name;
            }
        }

        return $attributes;
    }

    public function render()
    {
        return view('livewire.shared.dynamic-form');
    }
}
