<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\QueryPerformanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for performance monitoring and optimization.
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(QueryPerformanceService::class, function ($app) {
            return new QueryPerformanceService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only enable in development or when explicitly enabled
        if (!$this->shouldEnablePerformanceMonitoring()) {
            return;
        }

        $this->registerQueryListener();
    }

    /**
     * Check if performance monitoring should be enabled.
     */
    protected function shouldEnablePerformanceMonitoring(): bool
    {
        return app()->environment('local') || 
               config('settings.advanced.enable_query_logging', false);
    }

    /**
     * Register database query listener for slow query logging.
     */
    protected function registerQueryListener(): void
    {
        $threshold = config('settings.advanced.slow_query_threshold', 100);

        DB::listen(function ($query) use ($threshold) {
            if ($query->time > $threshold) {
                Log::channel('slow-queries')->warning('Slow query detected', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                    'connection' => $query->connectionName,
                ]);
            }
        });
    }
}
