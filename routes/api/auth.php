<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Public auth routes (no auth middleware here)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('auth.login')
        ->middleware('throttle:5,1');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
});

// Authenticated self-service (require auth) — parent group in api.php يضيف api-core
Route::middleware(['api-auth', 'impersonate'])->prefix('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('change-password', [AuthController::class, 'changePassword'])->name('auth.changePassword')
        ->middleware('perm:auth.password.change');
    Route::post('revoke-other-sessions', [AuthController::class, 'revokeOtherSessions'])->name('auth.revokeOtherSessions')
        ->middleware('perm:auth.sessions.revoke');
});
