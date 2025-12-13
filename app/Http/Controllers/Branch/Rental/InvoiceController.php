<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceCollectRequest;
use App\Http\Requests\InvoicePenaltyRequest;
use App\Models\Branch;
use App\Models\RentalInvoice;
use App\Services\Contracts\RentalServiceInterface as Rental;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(protected Rental $rental) {}

    public function index(Request $request, Branch $branch)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);

        return $this->ok(RentalInvoice::forBranch($branch->id)->orderByDesc('id')->paginate($per));
    }

    public function show(Branch $branch, RentalInvoice $invoice)
    {
        // Ensure invoice belongs to the branch via contract
        $invoice->load('contract');
        abort_if($invoice->contract?->branch_id !== $branch->id, 404);

        return $this->ok($invoice);
    }

    public function runRecurring(Branch $branch)
    {
        return $this->ok(['queued' => $this->rental->runRecurring()], __('Run recurring'));
    }

    public function collectPayment(InvoiceCollectRequest $request, Branch $branch, RentalInvoice $invoice)
    {
        // Ensure invoice belongs to the branch via contract
        $invoice->load('contract');
        abort_if($invoice->contract?->branch_id !== $branch->id, 404);

        $data = $request->validated();

        return $this->ok(
            $this->rental->collectPayment(
                $invoice->id,
                (float) $data['amount'],
                $data['method'] ?? 'cash',
                $data['reference'] ?? null,
                $branch->id
            ),
            __('Collected')
        );
    }

    public function applyPenalty(InvoicePenaltyRequest $request, Branch $branch, RentalInvoice $invoice)
    {
        // Ensure invoice belongs to the branch via contract
        $invoice->load('contract');
        abort_if($invoice->contract?->branch_id !== $branch->id, 404);

        $data = $request->validated();

        return $this->ok($this->rental->applyPenalty($invoice->id, (float) $data['penalty'], $branch->id), __('Penalty applied'));
    }
}
