<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Product;
use App\Observers\PriceAuditObserver;
use App\Observers\ProductObserver;
use App\Services\Contracts\ModuleFieldServiceInterface;
use App\Services\Contracts\ProductServiceInterface;
use App\Services\ModuleFieldService;
// Models & observers
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Model;
// Services
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductServiceInterface::class, ProductService::class);
        $this->app->bind(ModuleFieldServiceInterface::class, ModuleFieldService::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        JsonResource::withoutWrapping();

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        if (app()->environment('local')) {
            Model::shouldBeStrict();
            Model::preventSilentlyDiscardingAttributes();
            Model::preventAccessingMissingAttributes();
            Model::preventLazyLoading();
        }

        // Configurable query logging for non-production environments
        if (config('database.query_log.enabled')) {
            DB::listen(function ($query) {
                $threshold = config('database.query_log.slow_threshold', 1000);
                if ($query->time >= $threshold) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time,
                    ]);
                }
            });
        }

        // Observers
        Product::observe(ProductObserver::class);
        Product::observe(PriceAuditObserver::class); // Critical ERP: Price audit trail
    }
}
