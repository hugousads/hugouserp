<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\PropertyStoreRequest;
use App\Http\Requests\PropertyUpdateRequest;
use App\Models\Branch;
use App\Models\Property;
use App\Services\Contracts\RentalServiceInterface as Rental;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function __construct(protected Rental $rental) {}

    public function index(Request $request, Branch $branch)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);

        return $this->ok(Property::where('branch_id', $branch->id)->orderByDesc('id')->paginate($per));
    }

    public function store(PropertyStoreRequest $request, Branch $branch)
    {
        $data = $request->validated();

        return $this->ok($this->rental->createProperty($branch->id, $data), __('Created'), 201);
    }

    public function show(Branch $branch, Property $property)
    {
        // Ensure property belongs to the branch
        abort_if($property->branch_id !== $branch->id, 404);

        return $this->ok($property);
    }

    public function update(PropertyUpdateRequest $request, Branch $branch, Property $property)
    {
        // Ensure property belongs to the branch
        abort_if($property->branch_id !== $branch->id, 404);

        $property->fill($request->validated())->save();

        return $this->ok($property);
    }
}
