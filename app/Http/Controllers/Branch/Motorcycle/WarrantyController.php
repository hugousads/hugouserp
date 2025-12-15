<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Motorcycle;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Branch\Concerns\RequiresBranchContext;
use App\Http\Requests\WarrantyStoreRequest;
use App\Http\Requests\WarrantyUpdateRequest;
use App\Models\Warranty;
use App\Services\Contracts\MotorcycleServiceInterface as Motos;

class WarrantyController extends Controller
{
    use RequiresBranchContext;

    public function __construct(protected Motos $motos) {}

    public function index()
    {
        $per = min(max(request()->integer('per_page', 20), 1), 100);

        return $this->ok(Warranty::query()->orderByDesc('id')->paginate($per));
    }

    public function store(WarrantyStoreRequest $request)
    {
        $data = $request->validated();

        return $this->ok($this->motos->upsertWarranty($data['vehicle_id'], $data), __('Saved'));
    }

    public function show(Warranty $warranty)
    {
        // Defense-in-depth: Verify warranty's vehicle belongs to current branch
        $branchId = $this->requireBranchId(request());
        $warranty->load('vehicle');
        abort_if($warranty->vehicle?->branch_id !== $branchId, 404, 'Warranty not found in this branch');
        
        return $this->ok($warranty);
    }

    public function update(WarrantyUpdateRequest $request, Warranty $warranty)
    {
        // Defense-in-depth: Verify warranty's vehicle belongs to current branch
        $branchId = $this->requireBranchId($request);
        $warranty->load('vehicle');
        abort_if($warranty->vehicle?->branch_id !== $branchId, 404, 'Warranty not found in this branch');
        
        $warranty->fill($request->validated())->save();

        return $this->ok($warranty);
    }
}
