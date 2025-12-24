<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Project $project = null;
    public ?int $projectId = null;

    // Form fields
    public ?int $branch_id = null;
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public ?int $client_id = null;
    public ?int $project_manager_id = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public string $status = 'planning';
    public float $budget_amount = 0;
    public ?string $notes = null;
    public ?string $currency = null;

    public function mount(?int $id = null): void
    {
        $user = auth()->user();
        $allowedBranches = $this->getUserBranchIds();

        if ($id) {
            $this->authorize('projects.edit');
            $this->project = Project::query()
                ->whereIn('branch_id', $allowedBranches)
                ->find($id);

            if (! $this->project) {
                throw new HttpException(403);
            }

            if (! in_array($this->project->branch_id, $allowedBranches, true)) {
                throw new HttpException(403);
            }

            $this->projectId = $id;
            $this->fill($this->project->only([
                'branch_id', 'name', 'code', 'description', 'client_id', 'project_manager_id',
                'start_date', 'end_date', 'status', 'budget_amount', 'notes', 'currency',
            ]));
        } else {
            $this->authorize('projects.create');
            $this->branch_id = $user?->branch_id;
        }
    }

    /**
     * Get array of branch IDs accessible by the current user.
     */
    protected function getUserBranchIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        $branchIds = [];

        // Check if branches relation exists and is loaded
        if (method_exists($user, 'branches')) {
            // Force load the relation if not already loaded
            if (! $user->relationLoaded('branches')) {
                $user->load('branches');
            }
            $branchIds = $user->branches->pluck('id')->toArray();
        }

        if ($user->branch_id && ! in_array($user->branch_id, $branchIds)) {
            $branchIds[] = $user->branch_id;
        }

        return $branchIds;
    }

    public function rules(): array
    {
        $userBranchIds = $this->getUserBranchIds();

        return [
            'branch_id' => [
                'required',
                Rule::exists('branches', 'id'),
                Rule::in($userBranchIds),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:projects,code,' . $this->project?->id],
            'description' => ['required', 'string'],
            'client_id' => [
                'nullable',
                Rule::exists('customers', 'id')->whereIn('branch_id', $userBranchIds),
            ],
            'project_manager_id' => [
                'nullable',
                Rule::exists('users', 'id')->whereIn('branch_id', $userBranchIds),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'in:planning,active,on_hold,completed,cancelled'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'currency' => [
                'nullable',
                'string',
                'max:3',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
        ];
    }

    public function save(): void
    {
        $this->start_date = $this->coerceDateInput($this->start_date);
        $this->end_date = $this->coerceDateInput($this->end_date);
        $this->validate();

        // Server-side enforcement: ensure branch_id is within user's branches
        $userBranchIds = $this->getUserBranchIds();
        if (! in_array($this->branch_id, $userBranchIds)) {
            abort(403, 'You are not authorized to create/edit projects in this branch.');
        }

        if ($this->project) {
            $this->project->update($this->payloadWithNormalizedDates());
            session()->flash('success', __('Project updated successfully'));
        } else {
            Project::create(array_merge(
                $this->payloadWithNormalizedDates(),
                ['created_by' => auth()->id()]
            ));
            session()->flash('success', __('Project created successfully'));
        }

        $this->redirect(route('app.projects.index'));
    }

    protected function payloadWithNormalizedDates(): array
    {
        return array_merge(
            $this->only([
                'branch_id', 'name', 'code', 'description', 'client_id', 'project_manager_id',
                'status', 'budget_amount', 'notes', 'currency'
            ]),
            [
                'start_date' => $this->normalizeDate($this->start_date, 'start_date'),
                'end_date' => $this->normalizeDate($this->end_date, 'end_date'),
            ]
        );
    }

    protected function normalizeDate(?string $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function coerceDateInput($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return is_string($value) ? $value : null;
    }

    public function render()
    {
        $userBranchIds = $this->getUserBranchIds();

        // BUG-002 FIX: Scope customers and managers to user's branches
        $clients = Customer::whereIn('branch_id', $userBranchIds)
            ->orderBy('name')
            ->get();

        $managers = User::whereIn('branch_id', $userBranchIds)
            ->orderBy('name')
            ->get();

        // BUG-010 FIX: Get available currencies for validation
        $currencies = Currency::active()->ordered()->get();

        return view('livewire.projects.form', [
            'clients' => $clients,
            'managers' => $managers,
            'currencies' => $currencies,
        ]);
    }
}
