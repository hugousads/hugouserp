<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Motorcycle;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Branch\Concerns\RequiresBranchContext;
use App\Http\Requests\VehicleStoreRequest;
use App\Http\Requests\VehicleUpdateRequest;
use App\Models\Vehicle;
use App\Services\Contracts\MotorcycleServiceInterface as Motos;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    use RequiresBranchContext;

    public function __construct(protected Motos $motos) {}

    public function index()
    {
        return $this->ok($this->motos->vehicles());
    }

    public function store(VehicleStoreRequest $request)
    {
        $data = $request->validated();
        $row = Vehicle::create($data + ['branch_id' => $this->requireBranchId($request)]);

        return $this->ok($row, __('Created'), 201);
    }

    public function show(Vehicle $vehicle)
    {
        // Defense-in-depth: Verify vehicle belongs to current branch
        $branchId = $this->requireBranchId(request());
        abort_if($vehicle->branch_id !== $branchId, 404, 'Vehicle not found in this branch');
        
        return $this->ok($vehicle);
    }

    public function update(VehicleUpdateRequest $request, Vehicle $vehicle)
    {
        // Defense-in-depth: Verify vehicle belongs to current branch
        $branchId = $this->requireBranchId($request);
        abort_if($vehicle->branch_id !== $branchId, 404, 'Vehicle not found in this branch');
        
        $vehicle->fill($request->validated())->save();

        return $this->ok($vehicle);
    }
}
