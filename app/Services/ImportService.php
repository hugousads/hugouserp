<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportService
{
    use HandlesServiceErrors;

    protected array $errors = [];
    protected int $successCount = 0;
    protected int $failedCount = 0;

    public function getImportableEntities(): array
    {
        return [
            'products' => [
                'name' => __('Products'),
                'required_columns' => ['name'],
                'optional_columns' => ['sku', 'barcode', 'default_price', 'cost', 'min_stock', 'is_active', 'category_id', 'module_id', 'description', 'unit'],
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'sku' => 'nullable|string|max:100',
                    'barcode' => 'nullable|string|max:100',
                    'default_price' => 'nullable|numeric|min:0',
                    'cost' => 'nullable|numeric|min:0',
                    'min_stock' => 'nullable|integer|min:0',
                    'is_active' => 'nullable|boolean',
                    'description' => 'nullable|string|max:1000',
                    'unit' => 'nullable|string|max:50',
                ],
                'unique_columns' => ['sku', 'barcode'],
            ],
            'customers' => [
                'name' => __('Customers'),
                'required_columns' => ['name'],
                'optional_columns' => ['email', 'phone', 'address', 'city', 'country', 'tax_id', 'credit_limit', 'notes'],
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => 'nullable|string|max:50',
                    'address' => 'nullable|string|max:500',
                    'credit_limit' => 'nullable|numeric|min:0',
                    'notes' => 'nullable|string|max:1000',
                ],
                'unique_columns' => ['email', 'phone'],
            ],
            'suppliers' => [
                'name' => __('Suppliers'),
                'required_columns' => ['name'],
                'optional_columns' => ['email', 'phone', 'address', 'city', 'country', 'tax_id', 'payment_terms', 'notes'],
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => 'nullable|string|max:50',
                    'address' => 'nullable|string|max:500',
                    'notes' => 'nullable|string|max:1000',
                ],
                'unique_columns' => ['email', 'phone'],
            ],
            'employees' => [
                'name' => __('Employees'),
                'required_columns' => ['first_name', 'last_name'],
                'optional_columns' => ['email', 'phone', 'department', 'position', 'hire_date', 'salary', 'address', 'emergency_contact'],
                'validation_rules' => [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'phone' => 'nullable|string|max:50',
                    'department' => 'nullable|string|max:100',
                    'position' => 'nullable|string|max:100',
                    'hire_date' => 'nullable|date',
                    'salary' => 'nullable|numeric|min:0',
                ],
                'unique_columns' => ['email'],
            ],
            'expenses' => [
                'name' => __('Expenses'),
                'required_columns' => ['expense_date', 'amount', 'category'],
                'optional_columns' => ['description', 'reference', 'payment_method', 'notes'],
                'validation_rules' => [
                    'expense_date' => 'required|date',
                    'amount' => 'required|numeric|min:0',
                    'category' => 'required|string|max:255',
                    'description' => 'nullable|string|max:500',
                    'reference' => 'nullable|string|max:100',
                ],
                'unique_columns' => [],
            ],
            'incomes' => [
                'name' => __('Incomes'),
                'required_columns' => ['income_date', 'amount', 'category'],
                'optional_columns' => ['description', 'reference', 'payment_method', 'notes'],
                'validation_rules' => [
                    'income_date' => 'required|date',
                    'amount' => 'required|numeric|min:0',
                    'category' => 'required|string|max:255',
                    'description' => 'nullable|string|max:500',
                    'reference' => 'nullable|string|max:100',
                ],
                'unique_columns' => [],
            ],
            'categories' => [
                'name' => __('Categories'),
                'required_columns' => ['name'],
                'optional_columns' => ['description', 'parent_id', 'is_active'],
                'validation_rules' => [
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string|max:500',
                    'is_active' => 'nullable|boolean',
                ],
                'unique_columns' => ['name'],
            ],
            'sales' => [
                'name' => __('Sales Invoices'),
                'required_columns' => ['date', 'total', 'status'],
                'optional_columns' => ['reference', 'customer', 'subtotal', 'tax', 'discount', 'paid', 'due'],
                'validation_rules' => [
                    'reference' => 'nullable|string|max:50',
                    'date' => 'required|date',
                    'customer' => 'nullable|string|max:255',
                    'total' => 'required|numeric|min:0',
                    'status' => 'required|in:draft,posted,paid,cancelled',
                ],
                'unique_columns' => ['reference'],
            ],
            'purchases' => [
                'name' => __('Purchase Invoices'),
                'required_columns' => ['date', 'total', 'status'],
                'optional_columns' => ['reference', 'supplier', 'subtotal', 'tax', 'discount', 'paid', 'due'],
                'validation_rules' => [
                    'reference' => 'nullable|string|max:50',
                    'date' => 'required|date',
                    'supplier' => 'nullable|string|max:255',
                    'total' => 'required|numeric|min:0',
                    'status' => 'required|in:draft,posted,paid,cancelled',
                ],
                'unique_columns' => ['reference'],
            ],
        ];
    }

    public function getTemplateColumns(string $entityType): array
    {
        $entities = $this->getImportableEntities();
        if (!isset($entities[$entityType])) {
            return [];
        }

        $entity = $entities[$entityType];
        return array_merge($entity['required_columns'], $entity['optional_columns']);
    }

    public function generateTemplate(string $entityType): ?string
    {
        return $this->handleServiceOperation(
            callback: function () use ($entityType) {
                $columns = $this->getTemplateColumns($entityType);
                if (empty($columns)) {
                    return null;
                }

                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Write header row
                $col = 1;
                foreach ($columns as $column) {
                    $sheet->setCellValue([$col, 1], $column);
                    $col++;
                }

                // Style header
                $sheet->getStyle('1:1')->getFont()->setBold(true);

                // Auto-size columns
                foreach (range(1, count($columns)) as $colIndex) {
                    $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
                }

                $filename = "import_template_{$entityType}_" . date('Y-m-d') . '.xlsx';
                $path = 'imports/templates/' . $filename;
                
                Storage::disk('local')->makeDirectory('imports/templates');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save(Storage::disk('local')->path($path));

                return $path;
            },
            operation: 'generateTemplate',
            context: ['entity_type' => $entityType]
        );
    }

    public function import(string $entityType, string $filePath, array $options = []): array
    {
        $this->errors = [];
        $this->successCount = 0;
        $this->failedCount = 0;

        return $this->handleServiceOperation(
            callback: function () use ($entityType, $filePath, $options) {
                $entities = $this->getImportableEntities();
                if (!isset($entities[$entityType])) {
                    throw new \InvalidArgumentException("Unknown entity type: {$entityType}");
                }

                $entityConfig = $entities[$entityType];
                $updateExisting = $options['update_existing'] ?? false;
                $skipDuplicates = $options['skip_duplicates'] ?? true;
                $branchId = $options['branch_id'] ?? auth()->user()?->branch_id;

                $spreadsheet = IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                if (count($rows) < 2) {
                    return [
                        'success' => false,
                        'message' => __('File is empty or has no data rows'),
                        'imported' => 0,
                        'failed' => 0,
                        'errors' => [],
                    ];
                }

                $headers = array_map('strtolower', array_map('trim', $rows[0]));
                unset($rows[0]); // Remove header row

                DB::beginTransaction();

                try {
                    foreach ($rows as $rowNum => $row) {
                        $rowData = [];
                        foreach ($headers as $index => $header) {
                            $rowData[$header] = isset($row[$index]) ? trim((string)$row[$index]) : null;
                        }

                        // Skip empty rows
                        if (empty(array_filter($rowData))) {
                            continue;
                        }

                        // Validate row
                        $validator = Validator::make($rowData, $entityConfig['validation_rules']);
                        if ($validator->fails()) {
                            $this->errors[] = [
                                'row' => $rowNum + 1,
                                'errors' => $validator->errors()->all(),
                            ];
                            $this->failedCount++;
                            continue;
                        }

                        // Import based on entity type
                        try {
                            $result = match ($entityType) {
                                'products' => $this->importProduct($rowData, $branchId, $updateExisting, $skipDuplicates),
                                'customers' => $this->importCustomer($rowData, $branchId, $updateExisting, $skipDuplicates),
                                'suppliers' => $this->importSupplier($rowData, $branchId, $updateExisting, $skipDuplicates),
                                default => false,
                            };

                            if ($result) {
                                $this->successCount++;
                            } else {
                                $this->failedCount++;
                            }
                        } catch (\Exception $e) {
                            $this->errors[] = [
                                'row' => $rowNum + 1,
                                'errors' => [$e->getMessage()],
                            ];
                            $this->failedCount++;
                        }
                    }

                    DB::commit();

                    // Log the import activity
                    activity()
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'entity_type' => $entityType,
                            'imported' => $this->successCount,
                            'failed' => $this->failedCount,
                        ])
                        ->log("Imported {$this->successCount} {$entityType} records");

                    return [
                        'success' => true,
                        'message' => __(':count records imported successfully', ['count' => $this->successCount]),
                        'imported' => $this->successCount,
                        'failed' => $this->failedCount,
                        'errors' => $this->errors,
                    ];
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }
            },
            operation: 'import',
            context: ['entity_type' => $entityType, 'file' => $filePath],
            defaultValue: [
                'success' => false,
                'message' => __('Import failed'),
                'imported' => 0,
                'failed' => 0,
                'errors' => [],
            ]
        );
    }

    protected function importProduct(array $data, ?int $branchId, bool $updateExisting, bool $skipDuplicates): bool
    {
        $query = Product::query();
        
        // Check for existing product by SKU or barcode
        if (!empty($data['sku'])) {
            $existing = $query->where('sku', $data['sku'])->first();
        } elseif (!empty($data['barcode'])) {
            $existing = $query->where('barcode', $data['barcode'])->first();
        } else {
            $existing = null;
        }

        if ($existing) {
            if ($skipDuplicates && !$updateExisting) {
                return false;
            }
            if ($updateExisting) {
                $existing->fill($this->sanitizeProductData($data, $branchId));
                $existing->save();
                return true;
            }
        }

        Product::create($this->sanitizeProductData($data, $branchId));
        return true;
    }

    protected function importCustomer(array $data, ?int $branchId, bool $updateExisting, bool $skipDuplicates): bool
    {
        $existing = null;
        if (!empty($data['email'])) {
            $existing = Customer::where('email', $data['email'])->first();
        } elseif (!empty($data['phone'])) {
            $existing = Customer::where('phone', $data['phone'])->first();
        }

        if ($existing) {
            if ($skipDuplicates && !$updateExisting) {
                return false;
            }
            if ($updateExisting) {
                $existing->fill($this->sanitizeCustomerData($data, $branchId));
                $existing->save();
                return true;
            }
        }

        Customer::create($this->sanitizeCustomerData($data, $branchId));
        return true;
    }

    protected function importSupplier(array $data, ?int $branchId, bool $updateExisting, bool $skipDuplicates): bool
    {
        $existing = null;
        if (!empty($data['email'])) {
            $existing = Supplier::where('email', $data['email'])->first();
        } elseif (!empty($data['phone'])) {
            $existing = Supplier::where('phone', $data['phone'])->first();
        }

        if ($existing) {
            if ($skipDuplicates && !$updateExisting) {
                return false;
            }
            if ($updateExisting) {
                $existing->fill($this->sanitizeSupplierData($data, $branchId));
                $existing->save();
                return true;
            }
        }

        Supplier::create($this->sanitizeSupplierData($data, $branchId));
        return true;
    }

    protected function sanitizeProductData(array $data, ?int $branchId): array
    {
        return [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'default_price' => (float) ($data['default_price'] ?? 0),
            'cost' => (float) ($data['cost'] ?? 0),
            'min_stock' => (int) ($data['min_stock'] ?? 0),
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'branch_id' => $branchId,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'module_id' => !empty($data['module_id']) ? (int) $data['module_id'] : null,
        ];
    }

    protected function sanitizeCustomerData(array $data, ?int $branchId): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'credit_limit' => (float) ($data['credit_limit'] ?? 0),
            'branch_id' => $branchId,
            'is_active' => true,
        ];
    }

    protected function sanitizeSupplierData(array $data, ?int $branchId): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'branch_id' => $branchId,
            'is_active' => true,
        ];
    }
}
