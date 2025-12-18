<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportsExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        if (! $user || ! $user->can('reports.inventory.export')) {
            abort(403);
        }

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'only_low' => ['nullable', 'boolean'],
            'format' => ['nullable', 'in:web,excel,pdf'],
        ]);

        $format = $validated['format'] ?? 'web';

        $query = Product::query();

        if (! empty($validated['branch_id'])) {
            $query->where('default_branch_id', $validated['branch_id']);
        }

        $products = $query->orderBy('name')->limit(5000)->get();

        $columns = [
            'id' => 'ID',
            'sku' => 'SKU',
            'name' => 'Name',
            'stock_qty' => 'Stock',
            'reorder_level' => 'Reorder Level',
        ];

        $rows = $products->map(function (Product $product) use ($validated) {
            $stock = $product->stock_qty ?? 0;
            $reorder = $product->reorder_level ?? 0;

            if (! empty($validated['only_low']) && $reorder > 0 && $stock > $reorder) {
                return null;
            }

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'stock_qty' => $stock,
                'reorder_level' => $reorder,
            ];
        })->filter()->values()->toArray();

        if ($format === 'excel') {
            $filename = 'inventory_report_'.now()->format('Ymd_His').'.xlsx';
            
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
            $pdf = Pdf::loadView('admin.reports.inventory-export-pdf', [
                'columns' => $columns,
                'rows' => $rows,
            ]);

            return $pdf->download('inventory_report_'.now()->format('Ymd_His').'.pdf');
        }

        return view('admin.reports.inventory-export-web', [
            'columns' => $columns,
            'rows' => $rows,
        ]);
    }
}
