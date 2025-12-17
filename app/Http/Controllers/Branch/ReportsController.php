<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Services\Contracts\ReportServiceInterface as Reports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct(protected Reports $reports) {}

    public function branchSummary(Request $request)
    {
        $branchId = (int) $request->attributes->get('branch_id');

        return $this->ok($this->reports->financeSummary($branchId, $request->input('from'), $request->input('to')));
    }

    public function moduleSummary()
    {
        return $this->ok(['modules' => (array) config('modules.available')]);
    }

    public function topProducts(Request $request)
    {
        $branchId = (int) $request->attributes->get('branch_id');
        $limit = min(max((int) $request->integer('limit', 10), 1), 100);

        return $this->ok($this->reports->topProducts($branchId, $limit));
    }

    public function stockAging(Request $request)
    {
        $branchId = (int) $request->attributes->get('branch_id');
        $asOf = $request->input('as_of', now()->toDateString());

        $dateExpr = DB::getDriverName() === 'pgsql'
            ? 'MIN(DATE(m.created_at))'
            : 'MIN(DATE(m.created_at))';

        $rows = DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->select('p.id', 'p.name')
            ->selectRaw('SUM(m.qty) as qty')
            ->selectRaw("{$dateExpr} as first_move")
            ->where('m.branch_id', $branchId)
            ->whereDate('m.created_at', '<=', $asOf)
            ->groupBy('p.id', 'p.name')
            ->orderBy('first_move')
            ->get();

        return $this->ok(['as_of' => $asOf, 'items' => $rows]);
    }

    public function pnl(Request $request)
    {
        $b = (int) $request->attributes->get('branch_id');
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $sales = DB::table('sales')
            ->where('branch_id', $b)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('grand_total');
        $purchases = DB::table('purchases')
            ->where('branch_id', $b)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('grand_total');

        return $this->ok(['period' => [$from, $to], 'pnl' => round($sales - $purchases, 2)]);
    }

    public function cashflow(Request $request)
    {
        $b = (int) $request->attributes->get('branch_id');
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());
        $in = DB::table('sales')
            ->where('branch_id', $b)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('paid_total');
        $out = DB::table('purchases')
            ->where('branch_id', $b)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->sum('paid_total');

        return $this->ok(['period' => [$from, $to], 'inflow' => round($in, 2), 'outflow' => round($out, 2), 'net' => round($in - $out, 2)]);
    }
}
