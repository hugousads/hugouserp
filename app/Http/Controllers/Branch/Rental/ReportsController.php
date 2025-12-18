<?php

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function occupancy(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'property_id' => 'nullable|integer|exists:properties,id',
        ]);

        $model = '\\App\\Models\\RentalUnit';

        if (! class_exists($model)) {
            abort(500, 'RentalUnit model not found');
        }

        $query = $model::query()->with('property');

        if (!empty($validated['property_id'])) {
            $query->where('property_id', $validated['property_id']);
        }

        $filename = 'rental_occupancy_'.now()->format('Ymd_His').'.xlsx';

        $callback = function () use ($query) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Property', 'Code', 'Type', 'Status', 'Rent', 'Deposit'];
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Style header row
            $headerStyle = $sheet->getStyle('1:1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            // Add data
            $rowNum = 2;
            $query->chunk(500, function ($rows) use ($sheet, &$rowNum) {
                foreach ($rows as $row) {
                    $sheet->setCellValueByColumnAndRow(1, $rowNum, optional($row->property)->name ?? '');
                    $sheet->setCellValueByColumnAndRow(2, $rowNum, $row->code);
                    $sheet->setCellValueByColumnAndRow(3, $rowNum, $row->type);
                    $sheet->setCellValueByColumnAndRow(4, $rowNum, $row->status);
                    $sheet->setCellValueByColumnAndRow(5, $rowNum, $row->rent);
                    $sheet->setCellValueByColumnAndRow(6, $rowNum, $row->deposit);
                    $rowNum++;
                }
            });

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function expiringContracts(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = $validated['days'] ?? 30;
        $threshold = now()->addDays($days)->toDateString();

        $model = '\\App\\Models\\RentalContract';

        if (! class_exists($model)) {
            abort(500, 'RentalContract model not found');
        }

        $query = $model::query()
            ->with(['unit.property', 'tenant'])
            ->where('status', 'active')
            ->whereDate('end_date', '<=', $threshold);

        $filename = 'rental_expiring_contracts_'.now()->format('Ymd_His').'.xlsx';

        $callback = function () use ($query) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $headers = ['Property', 'Unit', 'Tenant', 'Start date', 'End date', 'Rent', 'Deposit', 'Status'];
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Style header row
            $headerStyle = $sheet->getStyle('1:1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            // Add data
            $rowNum = 2;
            $query->chunk(500, function ($rows) use ($sheet, &$rowNum) {
                foreach ($rows as $row) {
                    $sheet->setCellValueByColumnAndRow(1, $rowNum, optional(optional($row->unit)->property)->name ?? '');
                    $sheet->setCellValueByColumnAndRow(2, $rowNum, optional($row->unit)->code ?? '');
                    $sheet->setCellValueByColumnAndRow(3, $rowNum, optional($row->tenant)->name ?? '');
                    $sheet->setCellValueByColumnAndRow(4, $rowNum, $row->start_date);
                    $sheet->setCellValueByColumnAndRow(5, $rowNum, $row->end_date);
                    $sheet->setCellValueByColumnAndRow(6, $rowNum, $row->rent);
                    $sheet->setCellValueByColumnAndRow(7, $rowNum, $row->deposit);
                    $sheet->setCellValueByColumnAndRow(8, $rowNum, $row->status);
                    $rowNum++;
                }
            });

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
