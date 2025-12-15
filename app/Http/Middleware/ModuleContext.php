<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ModuleContext Middleware
 *
 * Manages UI-level module context for filtering views and navigation.
 * Works alongside SetModuleContext which handles API/route-level module keys.
 *
 * This middleware:
 * - Maintains session-based module context for UI filtering
 * - Allows context switching via query parameter
 * - Compatible with existing SetModuleContext for API routes
 *
 * Alias: Can be registered as 'module.ui' to differentiate from 'module'
 */
class ModuleContext
{
    /**
     * Handle an incoming request and ensure module context is set.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure module_context exists in session
        if (! session()->has('module_context')) {
            session(['module_context' => 'all']);
        }

        // Allow changing context via query parameter
        if ($request->has('module_context')) {
            $context = $request->get('module_context');
            $validContexts = [
                'all',
                'inventory',
                'pos',
                'sales',
                'purchases',
                'accounting',
                'warehouse',
                'manufacturing',
                'hrm',
                'rental',
                'fixed_assets',
                'banking',
                'projects',
                'documents',
                'helpdesk',
            ];

            if (in_array($context, $validContexts, true)) {
                session(['module_context' => $context]);
            }
        }

        // If SetModuleContext has set a module key via route/header,
        // and we're in "all" context, optionally switch to that module
        $routeModuleKey = $request->attributes->get('module.key');
        if ($routeModuleKey && session('module_context') === 'all') {
            // Map route module keys to UI context keys if needed
            $moduleKeyMap = [
                'inventory' => 'inventory',
                'pos' => 'pos',
                'sales' => 'sales',
                'purchases' => 'purchases',
                'accounting' => 'accounting',
                'warehouse' => 'warehouse',
                'manufacturing' => 'manufacturing',
                'hrm' => 'hrm',
                'rental' => 'rental',
                'fixed-assets' => 'fixed_assets',
                'banking' => 'banking',
                'projects' => 'projects',
                'documents' => 'documents',
                'helpdesk' => 'helpdesk',
            ];

            if (isset($moduleKeyMap[$routeModuleKey])) {
                // Store as hint but don't override explicit user selection
                $request->attributes->set('module.ui_hint', $moduleKeyMap[$routeModuleKey]);
            }
        }

        return $next($request);
    }
}
