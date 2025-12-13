<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Rental;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantArchiveRequest;
use App\Http\Requests\TenantStoreRequest;
use App\Http\Requests\TenantUpdateRequest;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\Contracts\RentalServiceInterface as Rental;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(protected Rental $rental) {}

    public function index(Request $request, Branch $branch)
    {
        $per = min(max($request->integer('per_page', 20), 1), 100);

        return $this->ok(Tenant::forBranch($branch->id)->orderByDesc('id')->paginate($per));
    }

    public function store(TenantStoreRequest $request, Branch $branch)
    {
        $data = $request->validated();

        return $this->ok($this->rental->createTenant($data, $branch->id), __('Created'), 201);
    }

    public function show(Branch $branch, Tenant $tenant)
    {
        // Ensure tenant belongs to the branch
        abort_if($tenant->branch_id !== $branch->id, 404);

        return $this->ok($tenant);
    }

    public function update(TenantUpdateRequest $request, Branch $branch, Tenant $tenant)
    {
        // Ensure tenant belongs to the branch
        abort_if($tenant->branch_id !== $branch->id, 404);

        $tenant->fill($request->validated())->save();

        return $this->ok($tenant);
    }

    public function archive(TenantArchiveRequest $request, Branch $branch, Tenant $tenant)
    {
        // Ensure tenant belongs to the branch
        abort_if($tenant->branch_id !== $branch->id, 404);

        return $this->ok($this->rental->archiveTenant($tenant->id, $branch->id));
    }
}
