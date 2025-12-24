<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Modules;

use App\Models\Module;
use App\Models\ModuleProductField;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ProductFields extends Component
{
    #[Layout('layouts.app')]
    public ?int $moduleId = null;

    public ?Module $module = null;

    public array $fields = [];

    public array $modules = [];

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $field_key = '';

    public string $field_label = '';

    public string $field_label_ar = '';

    public string $field_type = 'text';

    public array $field_options = [];

    public string $optionsText = '';

    public string $placeholder = '';

    public string $placeholder_ar = '';

    public string $default_value = '';

    public string $validation_rules = '';

    public bool $is_required = false;

    public bool $is_searchable = false;

    public bool $is_filterable = false;

    public bool $show_in_list = true;

    public bool $show_in_form = true;

    public bool $is_active = true;

    public int $sort_order = 0;

    public string $field_group = 'general';

    protected array $fieldTypes = [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'number' => 'Number',
        'decimal' => 'Decimal',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'select' => 'Dropdown',
        'multiselect' => 'Multi-Select',
        'checkbox' => 'Checkbox',
        'radio' => 'Radio Buttons',
        'file' => 'File Upload',
        'color' => 'Color Picker',
        'url' => 'URL',
        'email' => 'Email',
    ];

    protected array $fieldGroups = [
        'general' => 'General',
        'specifications' => 'Specifications',
        'dimensions' => 'Dimensions',
        'pricing' => 'Pricing',
        'inventory' => 'Inventory',
        'custom' => 'Custom',
    ];

    public function mount(?int $moduleId = null): void
    {
        $user = auth()->user();
        if (! $user || ! $user->can('modules.manage')) {
            abort(403);
        }

        $this->modules = Module::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar'])
            ->toArray();

        if ($moduleId) {
            $this->moduleId = $moduleId;
            $this->loadModule();
        } elseif (count($this->modules) > 0) {
            $this->moduleId = $this->modules[0]['id'];
            $this->loadModule();
        }
    }

    public function updatedModuleId(): void
    {
        $this->loadModule();
    }

    protected function loadModule(): void
    {
        if (! $this->moduleId) {
            $this->module = null;
            $this->fields = [];

            return;
        }

        $this->module = Module::find($this->moduleId);
        $this->loadFields();
    }

    protected function loadFields(): void
    {
        if (! $this->moduleId) {
            $this->fields = [];

            return;
        }

        $this->fields = ModuleProductField::where('module_id', $this->moduleId)
            ->orderBy('sort_order')
            ->orderBy('field_label')
            ->get()
            ->toArray();
    }

    public function openModal(?int $fieldId = null): void
    {
        if (! $this->moduleId) {
            $this->addError('moduleId', __('Please select a module before managing fields.'));

            return;
        }

        $this->resetForm();

        if ($fieldId) {
            $field = ModuleProductField::where('module_id', $this->moduleId)->findOrFail($fieldId);
            $this->editingId = $fieldId;
            $this->field_key = $field->field_key;
            $this->field_label = $field->field_label;
            $this->field_label_ar = $field->field_label_ar ?? '';
            $this->field_type = $field->field_type;
            $this->field_options = $field->field_options ?? [];
            $this->optionsText = is_array($field->field_options) ? implode("\n", $field->field_options) : '';
            $this->placeholder = $field->placeholder ?? '';
            $this->placeholder_ar = $field->placeholder_ar ?? '';
            $this->default_value = $field->default_value ?? '';
            $this->validation_rules = $field->validation_rules ?? '';
            $this->is_required = $field->is_required;
            $this->is_searchable = $field->is_searchable;
            $this->is_filterable = $field->is_filterable;
            $this->show_in_list = $field->show_in_list;
            $this->show_in_form = $field->show_in_form;
            $this->is_active = $field->is_active;
            $this->sort_order = $field->sort_order;
            $this->field_group = $field->field_group ?? 'general';
        } else {
            $maxSort = ModuleProductField::where('module_id', $this->moduleId)->max('sort_order') ?? 0;
            $this->sort_order = $maxSort + 10;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->field_key = '';
        $this->field_label = '';
        $this->field_label_ar = '';
        $this->field_type = 'text';
        $this->field_options = [];
        $this->optionsText = '';
        $this->placeholder = '';
        $this->placeholder_ar = '';
        $this->default_value = '';
        $this->validation_rules = '';
        $this->is_required = false;
        $this->is_searchable = false;
        $this->is_filterable = false;
        $this->show_in_list = true;
        $this->show_in_form = true;
        $this->is_active = true;
        $this->sort_order = 0;
        $this->field_group = 'general';
        $this->resetValidation();
    }

    protected function rules(): array
    {
        $keyRule = $this->editingId
            ? 'required|string|max:100|unique:module_product_fields,field_key,'.$this->editingId.',id,module_id,'.$this->moduleId
            : 'required|string|max:100|unique:module_product_fields,field_key,NULL,id,module_id,'.$this->moduleId;

        return [
            'moduleId' => 'required|integer|exists:modules,id',
            'field_key' => $keyRule,
            'field_label' => 'required|string|max:255',
            'field_label_ar' => 'nullable|string|max:255',
            'field_type' => 'required|string|in:'.implode(',', array_keys($this->fieldTypes)),
            'optionsText' => 'nullable|string',
            'placeholder' => 'nullable|string|max:255',
            'placeholder_ar' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'validation_rules' => 'nullable|string|max:500',
            'is_required' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_form' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'field_group' => 'required|string|max:50',
        ];
    }

    public function save(): void
    {
        $this->validate();

        if (! $this->moduleId) {
            $this->addError('moduleId', __('Please select a module before saving a field.'));

            return;
        }

        $options = array_filter(array_map('trim', explode("\n", $this->optionsText)));

        $data = [
            'module_id' => $this->moduleId,
            'field_key' => $this->field_key,
            'field_label' => $this->field_label,
            'field_label_ar' => $this->field_label_ar ?: null,
            'field_type' => $this->field_type,
            'field_options' => ! empty($options) ? $options : null,
            'placeholder' => $this->placeholder ?: null,
            'placeholder_ar' => $this->placeholder_ar ?: null,
            'default_value' => $this->default_value ?: null,
            'validation_rules' => $this->validation_rules ?: null,
            'is_required' => $this->is_required,
            'is_searchable' => $this->is_searchable,
            'is_filterable' => $this->is_filterable,
            'show_in_list' => $this->show_in_list,
            'show_in_form' => $this->show_in_form,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'field_group' => $this->field_group,
        ];

        if ($this->editingId) {
            ModuleProductField::where('id', $this->editingId)->update($data);
            session()->flash('success', __('Field updated successfully'));
        } else {
            ModuleProductField::create($data);
            session()->flash('success', __('Field created successfully'));
        }

        $this->closeModal();
        $this->loadFields();
    }

    public function toggleActive(int $fieldId): void
    {
        $this->authorize('modules.manage');

        $field = ModuleProductField::where('module_id', $this->moduleId)->findOrFail($fieldId);
        $field->update(['is_active' => ! $field->is_active]);
        $this->loadFields();
    }

    public function delete(int $fieldId): void
    {
        $this->authorize('modules.manage');

        ModuleProductField::where('module_id', $this->moduleId)->findOrFail($fieldId)->delete();
        session()->flash('success', __('Field deleted successfully'));
        $this->loadFields();
    }

    public function reorder(array $orderedIds): void
    {
        if (! $this->moduleId) {
            return;
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                ModuleProductField::where('id', $id)
                    ->where('module_id', $this->moduleId)
                    ->update(['sort_order' => ($index + 1) * 10]);
            }
        });
        $this->loadFields();
    }

    public function render()
    {
        return view('livewire.admin.modules.product-fields', [
            'fieldTypes' => $this->fieldTypes,
            'fieldGroups' => $this->fieldGroups,
        ]);
    }
}
