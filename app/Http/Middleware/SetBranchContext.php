<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetBranchContext
 *
 * - Loads the Branch model from route param {branch} or header 'X-Branch-Id'.
 * - Shares it via request attributes AND service container for easy access later.
 * - Adds simple guarding against inactive branches.
 *
 * Usage alias in routes: 'set.branch'
 */
class SetBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->route('branch') ?? $request->headers->get('X-Branch-Id');

        if (! $branchId) {
            return $this->error('Branch context is required.', 422);
        }

        /** @var Branch $branch */
        $branch = Branch::query()->whereKey($branchId)->first();

        if (! $branch) {
            throw new ModelNotFoundException('Branch not found.');
        }

        if (method_exists($branch, 'isActive') && ! $branch->isActive()) {
            return $this->error('Branch is inactive.', 423);
        }

        // set into request + container
        $request->attributes->set('branch', $branch);
        $request->attributes->set('branch_id', (int) $branch->getKey());
        app()->instance('req.branch_id', (int) $branch->getKey());

        return $next($request);
    }

    protected function error(string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $status);
    }
}
