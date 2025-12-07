<?php

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportImportController extends Controller
{
    /**
     * Generic method to export data to CSV
     */
    private function exportToCsv(Builder $query, array $headers, callable $rowMapper, string $filenamePrefix): StreamedResponse
    {
        $filename = $filenamePrefix.'_'.now()->format('Ymd_His').'.csv';

        $callback = function () use ($query, $headers, $rowMapper) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            $query->chunk(500, function ($rows) use ($handle, $rowMapper) {
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

    public function exportUnits(Request $request): StreamedResponse
    {
        $query = \App\Models\RentalUnit::query()->with('property');

        return $this->exportToCsv(
            $query,
            ['ID', 'Property', 'Code', 'Type', 'Status', 'Rent', 'Deposit'],
            fn ($row) => [
                $row->id,
                $row->property?->name ?? '',
                $row->code,
                $row->type,
                $row->status,
                $row->rent,
                $row->deposit,
            ],
            'rental_units'
        );
    }

    public function exportTenants(Request $request): StreamedResponse
    {
        $query = \App\Models\Tenant::query();

        return $this->exportToCsv(
            $query,
            ['ID', 'Name', 'Email', 'Phone', 'Address', 'Active'],
            fn ($row) => [
                $row->id,
                $row->name,
                $row->email,
                $row->phone,
                $row->address,
                $row->is_active ? '1' : '0',
            ],
            'rental_tenants'
        );
    }

    public function exportContracts(Request $request): StreamedResponse
    {
        $query = \App\Models\RentalContract::query()->with(['unit.property', 'tenant']);

        return $this->exportToCsv(
            $query,
            ['ID', 'Property', 'Unit', 'Tenant', 'Start date', 'End date', 'Rent', 'Deposit', 'Status'],
            fn ($row) => [
                $row->id,
                $row->unit?->property?->name ?? '',
                $row->unit?->code ?? '',
                $row->tenant?->name ?? '',
                $row->start_date,
                $row->end_date,
                $row->rent,
                $row->deposit,
                $row->status,
            ],
            'rental_contracts'
        );
    }

    /**
     * Generic method to import data from CSV
     */
    private function importFromCsv(Request $request, string $modelClass, callable $dataMapper, int $minColumns, string $successMessage)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        fgetcsv($handle); // skip header

        DB::transaction(function () use ($handle, $modelClass, $dataMapper, $minColumns) {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($data) < $minColumns) {
                    continue;
                }

                $modelClass::updateOrCreate(...$dataMapper($data));
            }
        });

        fclose($handle);

        return back()->with('status', $successMessage);
    }

    public function importUnits(Request $request)
    {
        return $this->importFromCsv(
            $request,
            \App\Models\RentalUnit::class,
            function ($data) {
                [$id, $propertyName, $code, $type, $status, $rent, $deposit] = $data;

                return [
                    ['id' => $id],
                    [
                        'code' => $code,
                        'type' => $type,
                        'status' => $status,
                        'rent' => $rent,
                        'deposit' => $deposit,
                    ],
                ];
            },
            7,
            'Units imported successfully'
        );
    }

    public function importTenants(Request $request)
    {
        return $this->importFromCsv(
            $request,
            \App\Models\Tenant::class,
            function ($data) {
                [$id, $name, $email, $phone, $address, $active] = $data;

                return [
                    ['id' => $id],
                    [
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $address,
                        'is_active' => (bool) $active,
                    ],
                ];
            },
            6,
            'Tenants imported successfully'
        );
    }
}
