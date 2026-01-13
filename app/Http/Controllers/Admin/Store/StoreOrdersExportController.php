<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Models\StoreOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreOrdersExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user || Gate::denies('store.reports.dashboard')) {
            abort(403);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:191'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'format' => ['required', 'string', 'in:web,excel,pdf'],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', 'max:50'],
        ]);

        $columns = $validated['columns'] ?? [];
        $allowedColumns = [
            'external_order_id',
            'source',
            'status',
            'total',
            'discount_total',
            'shipping_total',
            'tax_total',
            'created_at',
            'branch_id',
        ];

        if (empty($columns)) {
            $columns = $allowedColumns;
        } else {
            $columns = array_values(array_intersect($columns, $allowedColumns));
        }

        if (empty($columns)) {
            $columns = $allowedColumns;
        }

        $query = StoreOrder::query();

        // Apply branch scoping - default to authenticated user's branch if not specified
        $branchId = $validated['branch_id'] ?? $user->branch_id;
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        if (! empty($validated['source'])) {
            $query->where('payload->meta->source', $validated['source']);
        }

        $orders = $query->orderByDesc('created_at')->get();

        $rows = $orders->map(function (StoreOrder $order) use ($columns): array {
            $row = [];

            foreach ($columns as $column) {
                switch ($column) {
                    case 'source':
                        $row[$column] = $order->source ?? 'unknown';
                        break;
                    case 'created_at':
                        $row[$column] = optional($order->created_at)->toDateTimeString();
                        break;
                    case 'branch_id':
                        $row[$column] = $order->branch_id;
                        break;
                    default:
                        $row[$column] = $order->{$column};
                        break;
                }
            }

            return $row;
        })->all();

        $format = $validated['format'];

        if ($format === 'web') {
            return view('admin.store.orders-export-web', [
                'columns' => $columns,
                'rows' => $rows,
            ]);
        }

        if ($format === 'excel') {
            $filename = 'store_orders_'.now()->format('Ymd_His').'.xlsx';

            $spreadsheet = new Spreadsheet;
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
                foreach ($columns as $column) {
                    $value = $row[$column] ?? '';
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

        // PDF export
        if (class_exists(Pdf::class)) {
            $pdf = Pdf::loadView('admin.store.orders-export-pdf', [
                'columns' => $columns,
                'rows' => $rows,
            ]);

            return $pdf->download('store_orders_'.now()->format('Ymd_His').'.pdf');
        }

        // Fallback: web view if PDF library is not installed
        return view('admin.store.orders-export-web', [
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }
}
