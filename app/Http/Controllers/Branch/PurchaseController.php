<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseApproveRequest;
use App\Http\Requests\PurchaseCancelRequest;
use App\Http\Requests\PurchasePayRequest;
use App\Http\Requests\PurchaseReceiveRequest;
use App\Http\Requests\PurchaseReturnRequest;
use App\Http\Requests\PurchaseStoreRequest;
use App\Http\Requests\PurchaseUpdateRequest;
use App\Models\Purchase;
use App\Services\Contracts\PurchaseServiceInterface as Purchases;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(protected Purchases $purchases) {}

    protected function requireBranchId(Request $request): int
    {
        $branchId = $request->attributes->get('branch_id');

        abort_if($branchId === null, 400, __('Branch context is required.'));

        return (int) $branchId;
    }

    public function index(Request $request)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);
        $branchId = $this->requireBranchId($request);
        $rows = Purchase::query()
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->paginate($per);

        return $this->ok($rows);
    }

    public function store(PurchaseStoreRequest $request)
    {
        $payload = $request->validated();
        $payload['branch_id'] = $this->requireBranchId($request);

        $p = $this->purchases->create($payload);

        return $this->ok($p, __('Created'), 201);
    }

    public function show(Request $request, Purchase $purchase)
    {
        // Defense-in-depth: Verify purchase belongs to current branch
        $branchId = $this->requireBranchId($request);
        abort_if($purchase->branch_id !== $branchId, 404, 'Purchase not found in this branch');

        return $this->ok($purchase->load('items'));
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase)
    {
        // Defense-in-depth: Verify purchase belongs to current branch
        $branchId = $this->requireBranchId($request);
        abort_if($purchase->branch_id !== $branchId, 404, 'Purchase not found in this branch');

        $purchase->fill($request->validated())->save();

        return $this->ok($purchase);
    }

    public function approve(PurchaseApproveRequest $request, int $purchase)
    {
        $this->requireBranchId($request);

        // Prevent self-approval: verify purchase was not created by the current user
        $purchaseModel = Purchase::findOrFail($purchase);
        if ($purchaseModel->created_by === auth()->id()) {
            abort(403, __('You cannot approve your own request.'));
        }

        return $this->ok($this->purchases->approve($purchase), __('Approved'));
    }

    public function receive(PurchaseReceiveRequest $request, int $purchase)
    {
        $this->requireBranchId($request);

        return $this->ok($this->purchases->receive($purchase), __('Received'));
    }

    public function pay(PurchasePayRequest $request, int $purchase)
    {
        $data = $request->validated();
        $this->requireBranchId($request);

        return $this->ok($this->purchases->pay($purchase, (float) $data['amount']), __('Paid'));
    }

    public function handleReturn(PurchaseReturnRequest $request, int $purchase)
    {
        $this->requireBranchId($request);

        // No payload yet, but request handles authorization
        return $this->ok(['purchase_id' => $purchase], __('Return handled'));
    }

    public function cancel(PurchaseCancelRequest $request, int $purchase)
    {
        $this->requireBranchId($request);

        return $this->ok($this->purchases->cancel($purchase), __('Cancelled'));
    }
}
