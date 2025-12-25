<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Branches;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use HandlesErrors;

    public ?int $branchId = null;

    /**
     * @var array<string,mixed>
     */
    public array $form = [
        'name' => '',
        'code' => '',
        'address' => '',
        'phone' => '',
        'timezone' => '',
        'currency' => 'EGP',
        'is_active' => true,
        'is_main' => false,
    ];

    /**
     * @var array<int,array<string,mixed>>
     */
    public array $schema = [];

    public function mount(?int $branch = null): void
    {
        $user = Auth::user();

        // Check appropriate permission based on create/edit mode
        $requiredPermission = $branch ? 'branches.edit' : 'branches.create';
        if (! $user || ! $user->can($requiredPermission)) {
            abort(403, __('Unauthorized access'));
        }

        $this->branchId = $branch;
        $branchModel = $branch ? Branch::query()->findOrFail($branch) : null;

        // Get available timezones and currencies for dropdowns
        $timezones = \DateTimeZone::listIdentifiers();
        $currencies = \App\Models\Currency::active()->ordered()->pluck('code', 'code')->toArray();

        $this->schema = [
            ['name' => 'name',      'label' => __('Name'),      'type' => 'text'],
            ['name' => 'code',      'label' => __('Code'),      'type' => 'text'],
            ['name' => 'address',   'label' => __('Address'),   'type' => 'textarea'],
            ['name' => 'phone',     'label' => __('Phone'),     'type' => 'text'],
            ['name' => 'timezone',  'label' => __('Timezone'),  'type' => 'select', 'options' => array_combine($timezones, $timezones)],
            ['name' => 'currency',  'label' => __('Currency'),  'type' => 'select', 'options' => $currencies],
        ];

        if ($branchModel) {
            $this->form['name'] = $branchModel->name;
            $this->form['code'] = $branchModel->code ?? '';
            $this->form['address'] = $branchModel->address ?? '';
            $this->form['phone'] = $branchModel->phone ?? '';
            $this->form['timezone'] = $branchModel->timezone ?? config('app.timezone');
            $this->form['currency'] = $branchModel->currency ?? 'EGP';
            $this->form['is_active'] = (bool) $branchModel->is_active;
            $this->form['is_main'] = (bool) $branchModel->is_main;
        } else {
            $this->form['timezone'] = config('app.timezone');
        }
    }

    #[On('dynamic-form-updated')]
    public function syncForm(array $data): void
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $this->form)) {
                $this->form[$key] = $value;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('branches', 'code')->ignore($this->branchId),
            ],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.phone' => ['nullable', 'string', 'max:50'],
            'form.timezone' => ['required', 'string', 'max:64'],
            'form.currency' => ['required', 'string', 'max:10'],
            'form.is_active' => ['boolean'],
            'form.is_main' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $data = $this->form;
        $branchId = $this->branchId;

        $this->handleOperation(
            operation: function () use ($data, $branchId) {
                if ($branchId) {
                    $branch = Branch::findOrFail($branchId);
                } else {
                    $branch = new Branch;
                }

                $branch->name = $data['name'];
                $branch->code = $data['code'];
                $branch->address = $data['address'] ?: null;
                $branch->phone = $data['phone'] ?: null;
                $branch->timezone = $data['timezone'];
                $branch->currency = $data['currency'];
                $branch->is_active = (bool) $data['is_active'];
                $branch->is_main = (bool) $data['is_main'];

                $branch->save();
            },
            successMessage: $this->branchId
                ? __('Branch updated successfully.')
                : __('Branch created successfully.'),
            redirectRoute: 'admin.branches.index'
        );
    }

    public function render()
    {
        return view('livewire.admin.branches.form');
    }
}
