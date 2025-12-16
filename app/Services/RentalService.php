<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Property;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Services\Contracts\RentalServiceInterface;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;

class RentalService implements RentalServiceInterface
{
    use HandlesServiceErrors;

    public function createProperty(int $branchId, array $payload): Property
    {
        return $this->handleServiceOperation(
            callback: fn () => Property::create([
                'branch_id' => $branchId,
                'name' => $payload['name'],
                'address' => $payload['address'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]),
            operation: 'createProperty',
            context: ['branch_id' => $branchId, 'payload' => $payload]
        );
    }

    public function createUnit(int $propertyId, array $payload): RentalUnit
    {
        return $this->handleServiceOperation(
            callback: fn () => RentalUnit::create([
                'property_id' => $propertyId,
                'code' => $payload['code'],
                'type' => $payload['type'] ?? $payload['unit_type'] ?? null,
                'status' => $payload['status'] ?? 'available',
                'rent' => $payload['rent'] ?? $payload['monthly_rent'] ?? 0,
                'deposit' => $payload['deposit'] ?? 0,
            ]),
            operation: 'createUnit',
            context: ['property_id' => $propertyId, 'payload' => $payload]
        );
    }

    public function setUnitStatus(int $unitId, string $status): RentalUnit
    {
        return $this->handleServiceOperation(
            callback: function () use ($unitId, $status) {
                $u = RentalUnit::findOrFail($unitId);
                $u->status = $status;
                $u->save();

                return $u;
            },
            operation: 'setUnitStatus',
            context: ['unit_id' => $unitId, 'status' => $status]
        );
    }

    public function createTenant(array $payload, ?int $branchId = null): Tenant
    {
        return $this->handleServiceOperation(
            callback: fn () => Tenant::create([
                'branch_id' => $branchId ?? auth()->user()?->branch_id ?? null,
                'name' => $payload['name'],
                'phone' => $payload['phone'] ?? null,
                'email' => $payload['email'] ?? null,
            ]),
            operation: 'createTenant',
            context: ['payload' => $payload, 'branch_id' => $branchId]
        );
    }

    public function archiveTenant(int $tenantId, ?int $branchId = null): Tenant
    {
        return $this->handleServiceOperation(
            callback: function () use ($tenantId, $branchId) {
                $query = Tenant::query();
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
                $t = $query->findOrFail($tenantId);
                $t->is_archived = true;
                $t->save();

                return $t;
            },
            operation: 'archiveTenant',
            context: ['tenant_id' => $tenantId, 'branch_id' => $branchId]
        );
    }

    public function createContract(int $unitId, int $tenantId, array $payload, ?int $branchId = null): RentalContract
    {
        return $this->handleServiceOperation(
            callback: fn () => DB::transaction(function () use ($unitId, $tenantId, $payload, $branchId) {
                if ($branchId !== null) {
                    // Verify unit belongs to branch
                    RentalUnit::whereHas('property', function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    })->findOrFail($unitId);

                    // Verify tenant belongs to branch
                    Tenant::where('branch_id', $branchId)->findOrFail($tenantId);
                }

                $c = RentalContract::create([
                    'branch_id' => $branchId ?? auth()->user()?->branch_id ?? null,
                    'unit_id' => $unitId,
                    'tenant_id' => $tenantId,
                    'start_date' => $payload['start_date'],
                    'end_date' => $payload['end_date'],
                    'rent' => (float) $payload['rent'],
                    'status' => 'active',
                ]);

                return $c;
            }),
            operation: 'createContract',
            context: ['unit_id' => $unitId, 'tenant_id' => $tenantId, 'payload' => $payload, 'branch_id' => $branchId]
        );
    }

    public function renewContract(int $contractId, array $payload, ?int $branchId = null): RentalContract
    {
        return $this->handleServiceOperation(
            callback: function () use ($contractId, $payload, $branchId) {
                $query = RentalContract::query();
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
                $c = $query->findOrFail($contractId);
                $c->end_date = $payload['end_date'];
                $c->rent = (float) $payload['rent'];
                $c->save();

                return $c;
            },
            operation: 'renewContract',
            context: ['contract_id' => $contractId, 'payload' => $payload, 'branch_id' => $branchId]
        );
    }

    public function terminateContract(int $contractId, ?int $branchId = null): RentalContract
    {
        return $this->handleServiceOperation(
            callback: function () use ($contractId, $branchId) {
                $query = RentalContract::query();
                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
                $c = $query->findOrFail($contractId);
                $c->status = 'terminated';
                $c->save();

                return $c;
            },
            operation: 'terminateContract',
            context: ['contract_id' => $contractId, 'branch_id' => $branchId]
        );
    }

    public function runRecurring(?string $forDate = null): int
    {
        return $this->handleServiceOperation(
            callback: function () use ($forDate) {
                $forDate = $forDate ?: now()->toDateString();
                dispatch_sync(new \App\Jobs\GenerateRecurringInvoicesJob($forDate));

                return 1;
            },
            operation: 'runRecurring',
            context: ['for_date' => $forDate]
        );
    }

    public function collectPayment(int $invoiceId, float $amount, ?string $method = 'cash', ?string $reference = null, ?int $branchId = null): RentalInvoice
    {
        return $this->handleServiceOperation(
            callback: function () use ($invoiceId, $amount, $method, $reference, $branchId) {
                $query = RentalInvoice::query();

                // Scope by branch if provided
                if ($branchId !== null) {
                    $query->whereHas('contract', function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    });
                }

                $i = $query->findOrFail($invoiceId);

                // Get branch_id from contract, ensure it's set
                $invoiceBranchId = $i->contract->branch_id ?? null;
                abort_if(! $invoiceBranchId, 422, __('Branch context is required'));

                // Validate payment amount
                $remainingDue = max(0, ($i->amount ?? 0) - ($i->paid_total ?? 0));
                if ($amount <= 0) {
                    abort(422, __('Payment amount must be positive'));
                }
                if ($amount > $remainingDue) {
                    abort(422, __('Payment amount (:amount) exceeds remaining due (:due)', [
                        'amount' => number_format($amount, 2),
                        'due' => number_format($remainingDue, 2),
                    ]));
                }

                // Create payment record
                \App\Models\RentalPayment::create([
                    'invoice_id' => $i->id,
                    'contract_id' => $i->contract_id,
                    'branch_id' => $invoiceBranchId,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'paid_at' => now(),
                    'created_by' => auth()->id(),
                ]);

                $i->paid_total = round(($i->paid_total ?? 0) + $amount, 2);
                $i->status = $i->paid_total >= $i->amount ? 'paid' : 'unpaid';
                $i->save();

                return $i;
            },
            operation: 'collectPayment',
            context: ['invoice_id' => $invoiceId, 'amount' => $amount, 'method' => $method, 'branch_id' => $branchId]
        );
    }

    public function applyPenalty(int $invoiceId, float $penalty, ?int $branchId = null): RentalInvoice
    {
        return $this->handleServiceOperation(
            callback: function () use ($invoiceId, $penalty, $branchId) {
                $query = RentalInvoice::query();

                // Scope by branch if provided
                if ($branchId !== null) {
                    $query->whereHas('contract', function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId);
                    });
                }

                $i = $query->findOrFail($invoiceId);
                $i->amount = round($i->amount + max($penalty, 0.0), 2);
                $i->save();

                return $i;
            },
            operation: 'applyPenalty',
            context: ['invoice_id' => $invoiceId, 'penalty' => $penalty, 'branch_id' => $branchId]
        );
    }

    /**
     * Generate recurring invoices for active contracts
     */
    public function generateRecurringInvoicesForMonth(?int $branchId = null, ?\Carbon\Carbon $forMonth = null): array
    {
        $forMonth = $forMonth ?? now();
        $period = $forMonth->format('Y-m');

        $query = RentalContract::where('status', 'active')
            ->where('start_date', '<=', $forMonth->copy()->endOfMonth())
            ->where(function ($q) use ($forMonth) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $forMonth->copy()->startOfMonth());
            })
            ->with(['unit', 'tenant', 'rentalPeriod']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $contracts = $query->get();
        $generated = [];
        $skipped = [];
        $errors = [];

        foreach ($contracts as $contract) {
            try {
                // Check if invoice already exists for this period
                $existingInvoice = RentalInvoice::where('contract_id', $contract->id)
                    ->where('period', $period)
                    ->first();

                if ($existingInvoice) {
                    $skipped[] = [
                        'contract_id' => $contract->id,
                        'reason' => 'Invoice already exists for this period',
                        'invoice_id' => $existingInvoice->id,
                    ];

                    continue;
                }

                // Generate invoice code and create invoice atomically to prevent race conditions
                $invoice = DB::transaction(function () use ($contract, $period, $forMonth) {
                    $lastInvoice = RentalInvoice::lockForUpdate()->orderBy('id', 'desc')->first();
                    $nextNumber = $lastInvoice ? (intval(substr($lastInvoice->code, -6)) + 1) : 1;
                    $code = 'RI-'.str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);

                    // Calculate due date (typically start of month + grace period)
                    $dueDate = $forMonth->copy()->startOfMonth()->addDays(7);

                    // Create invoice
                    return RentalInvoice::create([
                        'contract_id' => $contract->id,
                        'code' => $code,
                        'period' => $period,
                        'due_date' => $dueDate,
                        'amount' => $contract->rent,
                        'status' => 'pending',
                    ]);
                });

                $generated[] = $invoice;
            } catch (\Exception $e) {
                $errors[] = [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_contracts' => $contracts->count(),
            'success_count' => count($generated),
            'skipped_count' => count($skipped),
            'error_count' => count($errors),
        ];
    }

    /**
     * Get occupancy statistics for a branch
     */
    public function getOccupancyStatistics(?int $branchId = null): array
    {
        $query = RentalUnit::query();

        if ($branchId) {
            $query->whereHas('property', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_units,
            COUNT(CASE WHEN status = ? THEN 1 END) as occupied_units,
            COUNT(CASE WHEN status = ? THEN 1 END) as vacant_units,
            COUNT(CASE WHEN status = ? THEN 1 END) as maintenance_units
        ', ['occupied', 'vacant', 'maintenance'])
            ->first();

        $totalUnits = $stats->total_units ?? 0;
        $occupiedUnits = $stats->occupied_units ?? 0;

        $occupancyRate = $totalUnits > 0
            ? round(($occupiedUnits / $totalUnits) * 100, 2)
            : 0;

        return [
            'total_units' => $totalUnits,
            'occupied_units' => $occupiedUnits,
            'vacant_units' => $stats->vacant_units ?? 0,
            'maintenance_units' => $stats->maintenance_units ?? 0,
            'occupancy_rate' => $occupancyRate,
        ];
    }

    /**
     * Get contracts expiring soon
     */
    public function getExpiringContracts(?int $branchId = null, int $daysAhead = 30): array
    {
        $today = now()->toDateString();
        $futureDate = now()->addDays($daysAhead)->toDateString();

        $query = RentalContract::where('status', 'active')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $futureDate])
            ->with(['unit', 'tenant', 'rentalPeriod'])
            ->orderBy('end_date');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $contracts = $query->get();

        return $contracts->map(function ($contract) {
            $daysRemaining = now()->diffInDays($contract->end_date, false);

            return [
                'contract_id' => $contract->id,
                'unit' => $contract->unit->name ?? '',
                'tenant' => $contract->tenant->name ?? '',
                'end_date' => $contract->end_date->format('Y-m-d'),
                'days_remaining' => $daysRemaining,
                'urgency' => $this->getExpiryUrgency($daysRemaining),
                'rent' => $contract->rent,
            ];
        })->toArray();
    }

    /**
     * Get urgency level based on days remaining
     */
    private function getExpiryUrgency(int $daysRemaining): string
    {
        if ($daysRemaining <= 7) {
            return 'critical';
        } elseif ($daysRemaining <= 14) {
            return 'high';
        } elseif ($daysRemaining <= 30) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices(?int $branchId = null): array
    {
        $today = now()->toDateString();

        $query = RentalInvoice::where('status', 'pending')
            ->where('due_date', '<', $today)
            ->with(['contract.unit', 'contract.tenant'])
            ->orderBy('due_date');

        if ($branchId) {
            $query->whereHas('contract', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $invoices = $query->get();

        return $invoices->map(function ($invoice) {
            $daysOverdue = $invoice->due_date->diffInDays(now(), false);

            return [
                'invoice_id' => $invoice->id,
                'invoice_code' => $invoice->code,
                'contract_id' => $invoice->contract_id,
                'unit' => $invoice->contract->unit->name ?? '',
                'tenant' => $invoice->contract->tenant->name ?? '',
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'amount' => $invoice->amount,
                'days_overdue' => $daysOverdue,
                'period' => $invoice->period,
            ];
        })->toArray();
    }

    /**
     * Get rental revenue statistics
     */
    public function getRevenueStatistics(?int $branchId = null, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $query = RentalInvoice::whereBetween('due_date', [$startDate, $endDate]);

        if ($branchId) {
            $query->whereHas('contract', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_invoices,
            SUM(amount) as total_amount,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as collected_amount,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as pending_amount,
            COUNT(CASE WHEN status = ? THEN 1 END) as collected_count,
            COUNT(CASE WHEN status = ? THEN 1 END) as pending_count
        ', ['paid', 'pending', 'paid', 'pending'])
            ->first();

        $totalAmount = $stats->total_amount ?? 0;
        $collectedAmount = $stats->collected_amount ?? 0;

        $collectionRate = $totalAmount > 0
            ? round(($collectedAmount / $totalAmount) * 100, 2)
            : 0;

        return [
            'total_invoices' => $stats->total_invoices ?? 0,
            'total_amount' => $totalAmount,
            'collected_amount' => $collectedAmount,
            'pending_amount' => $stats->pending_amount ?? 0,
            'collected_count' => $stats->collected_count ?? 0,
            'pending_count' => $stats->pending_count ?? 0,
            'collection_rate' => $collectionRate,
        ];
    }
}
