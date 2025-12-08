<?php

use App\Console\Commands\ClosePosDay;
use App\Console\Commands\SendScheduledReports;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // WARNING: Trusting all proxies ('*') can allow IP spoofing.
        // In production, configure specific proxy IPs via APP_TRUSTED_PROXIES env var
        $middleware->trustProxies(at: env('APP_TRUSTED_PROXIES', '*'));

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\AutoLogout::class,
        ]);

        $middleware->group('api-core', [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\ValidateJson::class,
            \App\Http\Middleware\RequestId::class,
            \App\Http\Middleware\CorrelationId::class,
            \App\Http\Middleware\RequestLogger::class,
            \App\Http\Middleware\ServerTiming::class,
            \App\Http\Middleware\SentryContext::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ETag::class,
            \App\Http\Middleware\PaginationSanitizer::class,
        ]);

        $middleware->group('api-auth', [
            \App\Http\Middleware\AssignGuard::class.':sanctum',
            \App\Http\Middleware\EnsureBranchAccess::class,
            \App\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\Require2FA::class,
        ]);

        $middleware->group('pos-protected', [
            \App\Http\Middleware\ForceJsonResponse::class,
            \App\Http\Middleware\ValidateJson::class,
            \App\Http\Middleware\RequestId::class,
            \App\Http\Middleware\CorrelationId::class,
            \App\Http\Middleware\RequestLogger::class,
            \App\Http\Middleware\ServerTiming::class,
            \App\Http\Middleware\SentryContext::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\ETag::class,
            \App\Http\Middleware\PaginationSanitizer::class,
            \App\Http\Middleware\AssignGuard::class.':sanctum',
            \App\Http\Middleware\EnsureBranchAccess::class,
            \App\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\VerifyPosOpen::class,
        ]);

        $middleware->alias([
            'impersonate' => \App\Http\Middleware\Impersonate::class,
            'api-branch' => \App\Http\Middleware\SetBranchContext::class,
            'module' => \App\Http\Middleware\SetModuleContext::class,
            'perm' => \App\Http\Middleware\EnsurePermission::class,
            'assign.guard' => \App\Http\Middleware\AssignGuard::class,
            'store.token' => \App\Http\Middleware\AuthenticateStoreToken::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class,
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            '2fa' => \App\Http\Middleware\Require2FA::class,
            'track.session' => \App\Http\Middleware\TrackUserSession::class,
            'recaptcha' => \App\Http\Middleware\ValidateRecaptcha::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withCommands([
        SendScheduledReports::class,
        ClosePosDay::class,
    ])
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reports:send-scheduled')
            ->dailyAt('08:00')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('pos:close-day')
            ->dailyAt('23:59')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('queue:work --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('cache:prune-stale-tags')
            ->hourly();
    })
    ->create();
