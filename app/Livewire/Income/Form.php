<?php

declare(strict_types=1);

namespace App\Livewire\Income;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;
    use WithFileUploads;

    public ?Income $income = null;

    public bool $editMode = false;

    public string $category_id = '';

    public string $reference_number = '';

    public string $income_date = '';

    public float $amount = 0;

    public string $payment_method = 'cash';

    public string $description = '';

    public $attachment;

    protected function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:income_categories,id',
            'reference_number' => 'nullable|string|max:100',
            'income_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt|mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation,text/csv,text/plain',
        ];
    }

    public function mount(?Income $income = null): void
    {
        $this->authorize('income.manage');

        $this->income_date = now()->format('Y-m-d');

        if ($income && $income->exists) {
            $this->income = $income;
            $this->editMode = true;
            $this->category_id = (string) ($income->category_id ?? '');
            $this->reference_number = $income->reference_number ?? '';
            $this->income_date = $income->income_date->format('Y-m-d');
            $this->amount = (float) $income->amount;
            $this->payment_method = $income->payment_method ?? 'cash';
            $this->description = $income->description ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['branch_id'] = auth()->user()->branch_id ?? auth()->user()->branches()->first()?->id;
        $validated['created_by'] = auth()->id();

        if ($this->attachment) {
            $validated['attachment'] = $this->attachment->store('incomes', 'public');
        }

        $this->handleOperation(
            operation: function () use ($validated) {
                if ($this->editMode) {
                    $this->income->update($validated);
                } else {
                    Income::create($validated);
                }
            },
            successMessage: $this->editMode ? __('Income updated successfully') : __('Income created successfully'),
            redirectRoute: 'app.income.index'
        );
    }

    public function render()
    {
        $categories = IncomeCategory::active()->get();

        return view('livewire.income.form', [
            'categories' => $categories,
        ])->layout('layouts.app', ['title' => $this->editMode ? __('Edit Income') : __('Add Income')]);
    }
}
