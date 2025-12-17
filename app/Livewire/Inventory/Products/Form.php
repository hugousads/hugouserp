<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Products;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Currency;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductFieldValue;
use App\Services\ModuleProductService;
use App\Services\ProductService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;
    use WithFileUploads;

    public ?int $productId = null;

    public ?int $selectedModuleId = null;

    public $thumbnailFile;

    public array $form = [
        'name' => '',
        'sku' => '',
        'barcode' => '',
        'price' => 0.0,
        'cost' => 0.0,
        'price_currency' => '',
        'cost_currency' => '',
        'status' => 'active',
        'type' => 'stock',
        'branch_id' => 0,
        'module_id' => null,
        'category_id' => null,
        'unit_id' => null,
    ];

    public array $dynamicSchema = [];

    public array $dynamicData = [];
    
    public array $availableCurrencies = [];

    protected ModuleProductService $moduleProductService;
    protected ProductService $productService;

    public function boot(ModuleProductService $moduleProductService, ProductService $productService): void
    {
        $this->moduleProductService = $moduleProductService;
        $this->productService = $productService;
    }

    public function mount(?int $product = null): void
    {
        $this->authorize('inventory.products.view');

        $user = Auth::user();
        $this->productId = $product;
        $this->form['branch_id'] = (int) ($user?->branch_id ?? 1);
        
        // Load currencies once and cache in component property
        $this->availableCurrencies = Currency::active()->ordered()->get(['code', 'name', 'symbol'])->toArray();
        
        // Set default currency from base currency
        $baseCurrency = Currency::getBaseCurrency();
        $defaultCurrency = $baseCurrency?->code ?? 'USD';
        $this->form['price_currency'] = $defaultCurrency;
        $this->form['cost_currency'] = $defaultCurrency;

        if ($this->productId) {
            $p = Product::with(['fieldValues.field'])->findOrFail($this->productId);

            $this->form['name'] = (string) $p->name;
            $this->form['sku'] = $p->sku ?? '';
            $this->form['barcode'] = $p->barcode ?? '';
            $this->form['price'] = (float) ($p->default_price ?? $p->price ?? 0);
            $this->form['cost'] = (float) ($p->standard_cost ?? $p->cost ?? 0);
            $this->form['price_currency'] = $p->price_currency ?? $defaultCurrency;
            $this->form['cost_currency'] = $p->cost_currency ?? $defaultCurrency;
            $this->form['status'] = (string) ($p->status ?? 'active');
            $this->form['type'] = (string) ($p->type ?? 'stock');
            $this->form['branch_id'] = (int) ($p->branch_id ?? $this->form['branch_id']);
            $this->form['module_id'] = $p->module_id;
            $this->form['category_id'] = $p->category_id;
            $this->form['unit_id'] = $p->unit_id;
            $this->form['thumbnail'] = $p->thumbnail ?? '';
            $this->selectedModuleId = $p->module_id;

            if ($p->module_id) {
                $this->loadModuleFields($p->module_id);

                foreach ($p->fieldValues as $fv) {
                    if ($fv->field) {
                        $this->dynamicData[$fv->field->field_key] = $fv->field_value;
                    }
                }
            }

            $legacyData = (array) ($p->extra_attributes ?? []);
            $this->dynamicData = array_merge($legacyData, $this->dynamicData);
        }
    }

    public function updatedSelectedModuleId($value): void
    {
        $this->form['module_id'] = $value ? (int) $value : null;

        if ($value) {
            $this->loadModuleFields((int) $value);
            $module = Module::find($value);
            if ($module) {
                $this->form['type'] = $module->is_service ? 'service' : 'stock';
            }
        } else {
            $this->dynamicSchema = [];
            $this->dynamicData = [];
        }
    }

    protected function loadModuleFields(int $moduleId): void
    {
        $fields = $this->moduleProductService->getModuleFields($moduleId, true);

        $this->dynamicSchema = $fields->map(function ($field) {
            return [
                'id' => $field->id,
                'key' => $field->field_key,
                'name' => $field->field_key,
                'label' => app()->getLocale() === 'ar' && $field->field_label_ar
                    ? $field->field_label_ar
                    : $field->field_label,
                'type' => $this->mapFieldType($field->field_type),
                'options' => $field->field_options ?? [],
                'required' => $field->is_required,
                'placeholder' => app()->getLocale() === 'ar' && $field->placeholder_ar
                    ? $field->placeholder_ar
                    : $field->placeholder,
                'default' => $field->default_value,
                'validation' => $field->validation_rules,
                'group' => $field->field_group,
            ];
        })->toArray();

        foreach ($this->dynamicSchema as $field) {
            if (! isset($this->dynamicData[$field['key']])) {
                $this->dynamicData[$field['key']] = $field['default'] ?? null;
            }
        }
    }

    protected function mapFieldType(string $type): string
    {
        return match ($type) {
            'textarea' => 'textarea',
            'number', 'decimal' => 'number',
            'date' => 'date',
            'datetime' => 'datetime-local',
            'select' => 'select',
            'multiselect' => 'multiselect',
            'checkbox' => 'checkbox',
            'radio' => 'radio',
            'file' => 'file',
            'image' => 'file',
            'color' => 'color',
            'url' => 'url',
            'email' => 'email',
            'phone' => 'tel',
            default => 'text',
        };
    }

    protected function rules(): array
    {
        $id = $this->productId;
        
        // Use cached currencies from mount
        $validCurrencies = array_column($this->availableCurrencies, 'code');
        if (empty($validCurrencies)) {
            $validCurrencies = ['USD', 'EUR', 'GBP']; // Fallback if no currencies in DB
        }

        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($id),
            ],
            'form.barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($id),
            ],
            'form.price' => ['required', 'numeric', 'min:0'],
            'form.cost' => ['nullable', 'numeric', 'min:0'],
            'form.price_currency' => ['required', 'string', Rule::in($validCurrencies)],
            'form.cost_currency' => ['required', 'string', Rule::in($validCurrencies)],
            'form.status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'form.type' => ['required', 'string', Rule::in(['stock', 'service'])],
            'form.branch_id' => ['required', 'integer'],
            'form.module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'form.category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'form.unit_id' => ['nullable', 'integer', 'exists:units_of_measure,id'],
            'thumbnailFile' => ['nullable', 'image', 'max:2048'],
        ];

        foreach ($this->dynamicSchema as $field) {
            $fieldRules = [];

            if ($field['required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            if (! empty($field['validation'])) {
                $fieldRules = array_merge($fieldRules, explode('|', $field['validation']));
            }

            $rules["dynamicData.{$field['key']}"] = $fieldRules;
        }

        return $rules;
    }

    #[On('dynamic-form-updated')]
    public function handleDynamicFormUpdated(array $data): void
    {
        $this->dynamicData = $data;
    }

    public function save(): void
    {
        $this->validate();

        try {
            // Prepare data for service
            $data = [
                'name' => $this->form['name'],
                'sku' => $this->form['sku'] ?: null,
                'barcode' => $this->form['barcode'] ?: null,
                'price' => $this->form['price'],
                'cost' => $this->form['cost'] ?? 0,
                'price_currency' => $this->form['price_currency'],
                'cost_currency' => $this->form['cost_currency'],
                'status' => $this->form['status'],
                'type' => $this->form['type'],
                'branch_id' => $this->form['branch_id'],
                'category_id' => $this->form['category_id'] ?: null,
                'unit_id' => $this->form['unit_id'] ?: null,
                'custom_fields' => $this->dynamicData,
            ];

            if ($this->productId) {
                // Update existing product
                $product = Product::findOrFail($this->productId);
                $this->productService->updateProductForModule(
                    $product,
                    $data,
                    $this->thumbnailFile
                );
            } else {
                // Create new product - require module selection
                if (!$this->form['module_id']) {
                    $this->addError('form.module_id', __('Please select a module for this product'));
                    return;
                }

                $module = Module::findOrFail($this->form['module_id']);
                
                // Verify module supports items
                if (!$module->supportsItems()) {
                    $this->addError('form.module_id', __('Selected module does not support items/products'));
                    return;
                }

                $this->productService->createProductForModule(
                    $module,
                    $data,
                    $this->thumbnailFile
                );
            }

            session()->flash('status', $this->productId
                ? __('Product updated successfully.')
                : __('Product created successfully.')
            );

            $this->redirectRoute('inventory.products.index', navigate: true);
        } catch (\Exception $e) {
            $this->addError('save', $e->getMessage());
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();
        $branchId = $user?->branch_id;

        $modules = collect();

        if ($branchId) {
            $enabledModuleIds = \App\Models\BranchModule::where('branch_id', $branchId)
                ->where('enabled', true)
                ->pluck('module_id')
                ->toArray();

            if (! empty($enabledModuleIds)) {
                // BUG FIX: Only show modules that support items/products
                // Filter by supports_items=true to prevent selecting invalid modules
                $modules = Module::where('is_active', true)
                    ->where('supports_items', true)
                    ->whereIn('id', $enabledModuleIds)
                    ->orderBy('sort_order')
                    ->get();
            }
        }
        
        // Fallback: If no branch-specific modules found (or user has no branch), 
        // show all active modules that support items
        // BUG FIX: Added supports_items filter to prevent empty module list issue
        if ($modules->isEmpty()) {
            $modules = Module::where('is_active', true)
                ->where('supports_items', true)
                ->orderBy('sort_order')
                ->get();
        }
        
        // Use cached currencies loaded in mount()
        return view('livewire.inventory.products.form', [
            'modules' => $modules,
            'currencies' => $this->availableCurrencies,
            'categories' => \App\Models\ProductCategory::active()->orderBy('name')->get(['id', 'name']),
            'units' => \App\Models\UnitOfMeasure::active()->orderBy('name')->get(['id', 'name', 'symbol']),
        ]);
    }
}
