<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Reports;

use App\Models\Module;
use App\Services\BranchAccessService;
use App\Services\ReportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ModuleReport extends Component
{
    use AuthorizesRequests;

    public Module $module;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $selectedBranchId = null;

    public array $reportData = [];

    public array $summary = [];

    protected ReportService $reportService;

    protected BranchAccessService $branchAccessService;

    public function boot(ReportService $reportService, BranchAccessService $branchAccessService): void
    {
        $this->reportService = $reportService;
        $this->branchAccessService = $branchAccessService;
    }

    public function mount(Module $module): void
    {
        $this->authorize('reports.view');
        $this->module = $module;
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');

        $user = auth()->user();
        if (! $user->hasAnyRole(['Super Admin', 'super-admin'])) {
            $branches = $this->branchAccessService->getUserBranches($user);
            $this->selectedBranchId = $branches->first()?->id;
        }

        $this->generateReport();
    }

    public function generateReport(): void
    {
        $this->authorize('reports.view');

        $filters = [
            'branch_id' => $this->selectedBranchId,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];

        $result = $this->reportService->getModuleReport($this->module->id, $filters, auth()->user());

        $this->reportData = $result['inventory']['items']->toArray();
        $this->summary = $result['inventory']['summary'] ?? [];
    }

    public function render()
    {
        $user = auth()->user();

        $isSuperAdmin = $user->hasAnyRole(['Super Admin', 'super-admin']);
        $branches = $isSuperAdmin
            ? \App\Models\Branch::active()->get()
            : $this->branchAccessService->getUserBranches($user);

        return view('livewire.admin.reports.module-report', [
            'branches' => $branches,
            'isSuperAdmin' => $isSuperAdmin,
        ])->layout('layouts.app');
    }
}
