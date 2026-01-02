<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trait for exporting data to CSV and XLSX formats
 *
 * This trait provides reusable methods for streaming exports
 * from Eloquent queries to avoid memory issues with large datasets.
 */
trait ExportsCsv
{
    /**
     * Export query results to CSV using streaming for memory efficiency
     *
     * @param  Builder  $query  The Eloquent query to export
     * @param  array  $headers  Column headers for the CSV file
     * @param  callable  $rowMapper  Function to map each row to an array of values
     * @param  string  $filenamePrefix  Prefix for the generated filename
     * @param  int  $chunkSize  Number of rows to process at once (default: 500)
     */
    protected function exportToCsv(
        Builder $query,
        array $headers,
        callable $rowMapper,
        string $filenamePrefix,
        int $chunkSize = 500
    ): StreamedResponse {
        $filename = $filenamePrefix.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($query, $headers, $rowMapper, $chunkSize) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            $query->chunk($chunkSize, function ($rows) use ($handle, $rowMapper) {
                foreach ($rows as $row) {
                    fputcsv($handle, $rowMapper($row));
                }
            });

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export query results to XLSX using streaming for memory efficiency
     *
     * @param  Builder  $query  The Eloquent query to export
     * @param  array  $headers  Column headers for the XLSX file
     * @param  callable  $rowMapper  Function to map each row to an array of values
     * @param  string  $filenamePrefix  Prefix for the generated filename
     * @param  int  $chunkSize  Number of rows to process at once (default: 500)
     */
    protected function exportToXlsx(
        Builder $query,
        array $headers,
        callable $rowMapper,
        string $filenamePrefix,
        int $chunkSize = 500
    ): StreamedResponse {
        $filename = $filenamePrefix.'_'.now()->format('Ymd_His').'.xlsx';

        $callback = function () use ($query, $headers, $rowMapper, $chunkSize) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $col = 1;
            foreach ($headers as $header) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue($colLetter.'1', $header);
                $col++;
            }

            // Style header row
            $headerStyle = $sheet->getStyle('1:1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            // Add data rows
            $rowNum = 2;
            $query->chunk($chunkSize, function ($rows) use ($sheet, $rowMapper, &$rowNum) {
                foreach ($rows as $row) {
                    $data = $rowMapper($row);
                    $col = 1;
                    foreach ($data as $value) {
                        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                        $sheet->setCellValue($colLetter.$rowNum, is_scalar($value) ? $value : json_encode($value));
                        $col++;
                    }
                    $rowNum++;
                }
            });

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
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
