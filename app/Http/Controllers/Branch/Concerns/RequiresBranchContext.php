<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch\Concerns;

use Illuminate\Http\Request;

trait RequiresBranchContext
{
    /**
     * Get and validate the branch ID from the request context.
     *
     * @param Request $request
     * @return int
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function requireBranchId(Request $request): int
    {
        $branchId = $request->attributes->get('branch_id');

        abort_if($branchId === null, 400, __('Branch context is required.'));

        return (int) $branchId;
    }
}
