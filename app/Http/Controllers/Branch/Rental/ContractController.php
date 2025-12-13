<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContractRenewRequest;
use App\Http\Requests\ContractStoreRequest;
use App\Http\Requests\ContractTerminateRequest;
use App\Http\Requests\ContractUpdateRequest;
use App\Models\Branch;
use App\Models\RentalContract;
use App\Services\Contracts\RentalServiceInterface as Rental;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function __construct(protected Rental $rental) {}

    public function index(Request $request, Branch $branch)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);

        return $this->ok(RentalContract::forBranch($branch->id)->orderByDesc('id')->paginate($per));
    }

    public function store(ContractStoreRequest $request, Branch $branch)
    {
        $data = $request->validated();

        return $this->ok($this->rental->createContract($data['unit_id'], $data['tenant_id'], $data, $branch->id), __('Created'), 201);
    }

    public function show(Branch $branch, RentalContract $contract)
    {
        // Ensure contract belongs to the branch
        abort_if($contract->branch_id !== $branch->id, 404);

        return $this->ok($contract);
    }

    public function update(ContractUpdateRequest $request, Branch $branch, RentalContract $contract)
    {
        // Ensure contract belongs to the branch
        abort_if($contract->branch_id !== $branch->id, 404);

        $contract->fill($request->validated())->save();

        return $this->ok($contract);
    }

    public function renew(ContractRenewRequest $request, Branch $branch, RentalContract $contract)
    {
        // Ensure contract belongs to the branch
        abort_if($contract->branch_id !== $branch->id, 404);

        return $this->ok($this->rental->renewContract($contract->id, $request->validated(), $branch->id), __('Renewed'));
    }

    public function terminate(ContractTerminateRequest $request, Branch $branch, RentalContract $contract)
    {
        // Ensure contract belongs to the branch
        abort_if($contract->branch_id !== $branch->id, 404);

        return $this->ok($this->rental->terminateContract($contract->id, $branch->id), __('Terminated'));
    }
}
