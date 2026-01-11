<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetBranchContext Middleware
 *
 * - Loads the Branch model from route param {branch}, header 'X-Branch-Id', or request payload.
 * - Shares it via request attributes AND service container for easy access later.
 * - Adds simple guarding against inactive branches.
 *
 * SECURITY FIX: Validates branch_id consistency between session/header and request payload
 * to prevent "context poisoning" attacks in multi-tab browser scenarios.
 *
 * Usage alias in routes: 'set.branch'
 */
class SetBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Priority: Route param > Request payload (for POST/PUT) > Header
        $routeBranchId = $request->route('branch');
        $payloadBranchId = $request->input('branch_id');
        $headerBranchId = $request->headers->get('X-Branch-Id');

        // Determine the branch ID to use
        $branchId = $routeBranchId ?? $payloadBranchId ?? $headerBranchId;

        if (! $branchId) {
            return $this->error('Branch context is required.', 422);
        }

        // SECURITY FIX: For mutating requests (POST, PUT, PATCH, DELETE),
        // validate that the branch_id in the payload matches the context branch_id
        // This prevents "context poisoning" attacks where a user switches tabs
        // and the session cookie updates but the form data doesn't
        if ($this->isMutatingRequest($request) && $payloadBranchId !== null) {
            $contextBranchId = $routeBranchId ?? $headerBranchId;

            // Validate both values are numeric before comparing
            if ($contextBranchId !== null) {
                $payloadId = is_numeric($payloadBranchId) ? (int) $payloadBranchId : null;
                $contextId = is_numeric($contextBranchId) ? (int) $contextBranchId : null;

                if ($payloadId === null || $contextId === null || $payloadId !== $contextId) {
                    return $this->error(
                        'Branch context mismatch. The form was submitted for a different branch than your current context. Please refresh the page and try again.',
                        409,
                        [
                            'payload_branch_id' => $payloadId,
                            'context_branch_id' => $contextId,
                            'suggestion' => 'This can happen when you have multiple tabs open with different branches. Please ensure you are working on the correct branch.',
                        ]
                    );
                }
            }
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

    /**
     * Check if this is a mutating request that could cause data changes.
     */
    protected function isMutatingRequest(Request $request): bool
    {
        return in_array(strtoupper($request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    protected function error(string $message, int $status, array $meta = []): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'meta' => $meta ?: null,
        ], $status);
    }
}
