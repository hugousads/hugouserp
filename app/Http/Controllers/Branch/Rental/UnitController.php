<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitStatusRequest;
use App\Http\Requests\UnitStoreRequest;
use App\Http\Requests\UnitUpdateRequest;
use App\Models\Branch;
use App\Models\RentalUnit;
use App\Services\Contracts\RentalServiceInterface as Rental;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function __construct(protected Rental $rental) {}

    public function index(Request $request, Branch $branch)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);

        return $this->ok(RentalUnit::forBranch($branch->id)->orderByDesc('id')->paginate($per));
    }

    public function store(UnitStoreRequest $request, Branch $branch)
    {
        $data = $request->validated();

        return $this->ok($this->rental->createUnit($data['property_id'], $data), __('Created'), 201);
    }

    public function show(Branch $branch, RentalUnit $unit)
    {
        // Ensure unit belongs to the branch
        $unit->load('property');
        abort_if($unit->property?->branch_id !== $branch->id, 404);

        return $this->ok($unit);
    }

    public function update(UnitUpdateRequest $request, Branch $branch, RentalUnit $unit)
    {
        // Ensure unit belongs to the branch
        $unit->load('property');
        abort_if($unit->property?->branch_id !== $branch->id, 404);

        $unit->fill($request->validated())->save();

        return $this->ok($unit);
    }

    public function setStatus(UnitStatusRequest $request, Branch $branch, RentalUnit $unit)
    {
        // Ensure unit belongs to the branch
        $unit->load('property');
        abort_if($unit->property?->branch_id !== $branch->id, 404);

        $data = $request->validated();

        return $this->ok($this->rental->setUnitStatus($unit->id, $data['status']));
    }
}
