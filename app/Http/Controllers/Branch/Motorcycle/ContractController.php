<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Motorcycle;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Branch\Concerns\RequiresBranchContext;
use App\Models\VehicleContract;
use App\Services\Contracts\MotorcycleServiceInterface as Motos;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    use RequiresBranchContext;

    public function __construct(protected Motos $motos) {}

    public function index()
    {
        $per = min(max(request()->integer('per_page', 20), 1), 100);

        return $this->ok(VehicleContract::query()->orderByDesc('id')->paginate($per));
    }

    public function store(Request $request)
    {
        $data = $this->validate($request, [
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ]);

        return $this->ok($this->motos->createContract($data['vehicle_id'], $data['customer_id'], $data['start_date'], $data['end_date']), __('Created'), 201);
    }

    public function show(VehicleContract $contract)
    {
        // Defense-in-depth: Verify contract's vehicle belongs to current branch
        $branchId = $this->requireBranchId(request());
        $contract->load('vehicle');
        abort_if($contract->vehicle?->branch_id !== $branchId, 404, 'Contract not found in this branch');
        
        return $this->ok($contract);
    }

    public function update(Request $request, VehicleContract $contract)
    {
        // Defense-in-depth: Verify contract's vehicle belongs to current branch
        $branchId = $this->requireBranchId($request);
        $contract->load('vehicle');
        abort_if($contract->vehicle?->branch_id !== $branchId, 404, 'Contract not found in this branch');
        
        $contract->fill($request->only(['start_date', 'end_date', 'status']))->save();

        return $this->ok($contract);
    }

    public function deliver(VehicleContract $contract)
    {
        return $this->ok($this->motos->deliverContract($contract->id), __('Delivered'));
    }
}
