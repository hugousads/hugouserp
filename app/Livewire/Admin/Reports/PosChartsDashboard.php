<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Reports;

use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

class PosChartsDashboard extends Component
{
    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $branchId = null;

    #[Layout('layouts.app')]
    public function render()
    {
        $user = Auth::user();

        if (! $user || ! $user->can('reports.pos.charts')) {
            abort(403);
        }

        $query = Sale::query();

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        }

        $sales = $query->orderBy('created_at')->get();

        $totalSales = $sales->count();
        $totalRevenue = (float) $sales->sum('grand_total');

        $groupedByDay = $sales->groupBy(function (Sale $sale): string {
            return optional($sale->created_at)->toDateString() ?? '';
        });

        $dayLabels = [];
        $dayValues = [];

        foreach ($groupedByDay as $date => $items) {
            if (! $date) {
                continue;
            }

            $dayLabels[] = $date;
            $dayValues[] = (float) $items->sum('grand_total');
        }

        $groupedByBranch = $sales->groupBy('branch_id');

        $branchLabels = [];
        $branchValues = [];

        foreach ($groupedByBranch as $branchId => $items) {
            $branchLabels[] = $branchId ? ('#'.$branchId) : __('N/A');
            $branchValues[] = (float) $items->sum('grand_total');
        }

        $chartSalesByDay = [
            'labels' => $dayLabels,
            'values' => $dayValues,
        ];

        $chartSalesByBranch = [
            'labels' => $branchLabels,
            'values' => $branchValues,
        ];

        $this->dispatch('pos-charts-update', chartData: [
            'salesByDay' => $chartSalesByDay,
            'salesByBranch' => $chartSalesByBranch,
        ]);

        return view('livewire.admin.reports.pos-charts-dashboard', [
            'totalSales' => $totalSales,
            'totalRevenue' => $totalRevenue,
        ]);
    }
}
