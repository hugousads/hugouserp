<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PosReportsExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user || ! $user->can('reports.pos.export')) {
            abort(403);
        }

        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:50'],
            'channel' => ['nullable', 'string', 'max:50'],
            'min_total' => ['nullable', 'numeric'],
            'format' => ['nullable', 'in:web,excel,pdf'],
        ]);

        $format = $validated['format'] ?? 'web';

        $query = Sale::query()->posted();

        if (! empty($validated['date_from'])) {
            $query->whereDate('posted_at', '>=', $validated['date_from']);
        }

        if (! empty($validated['date_to'])) {
            $query->whereDate('posted_at', '<=', $validated['date_to']);
        }

        if (! empty($validated['branch_id'])) {
            $query->where('branch_id', $validated['branch_id']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        if (! empty($validated['min_total'])) {
            $query->where('grand_total', '>=', $validated['min_total']);
        }

        $sales = $query->with('branch')->orderBy('posted_at')->limit(5000)->get();

        $columns = [
            'id' => 'ID',
            'posted_at' => 'Date',
            'branch_name' => 'Branch',
            'status' => 'Status',
            'channel' => 'Channel',
            'grand_total' => 'Total',
            'paid_total' => 'Paid',
            'due_total' => 'Due',
        ];

        $rows = $sales->map(function (Sale $sale) {
            return [
                'id' => $sale->id,
                'posted_at' => optional($sale->posted_at ?? $sale->created_at)->format('Y-m-d H:i'),
                'branch_name' => optional($sale->branch)->name ?? '-',
                'status' => $sale->status,
                'channel' => $sale->channel ?? null,
                'grand_total' => $sale->grand_total,
                'paid_total' => $sale->paid_total,
                'due_total' => $sale->due_total,
            ];
        })->toArray();

        if ($format === 'excel') {
            $filename = 'pos_report_'.now()->format('Ymd_His').'.xlsx';
            
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $col = 1;
            foreach ($columns as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Set data rows
            $rowNum = 2;
            foreach ($rows as $row) {
                $col = 1;
                foreach (array_keys($columns) as $key) {
                    $value = $row[$key] ?? '';
                    $sheet->setCellValueByColumnAndRow($col, $rowNum, is_scalar($value) ? $value : json_encode($value));
                    $col++;
                }
                $rowNum++;
            }

            // Auto-size columns
            foreach (range(1, count($columns)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            // Style header row
            $headerStyle = $sheet->getStyle('1:1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            $response = new StreamedResponse(function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            });

            $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');
            $response->headers->set('Cache-Control', 'max-age=0');

            return $response;
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.reports.pos-export-pdf', [
                'columns' => $columns,
                'rows' => $rows,
            ]);

            return $pdf->download('pos_report_'.now()->format('Ymd_His').'.pdf');
        }

        return view('admin.reports.pos-export-web', [
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }
}
