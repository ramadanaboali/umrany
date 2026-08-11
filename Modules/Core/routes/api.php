<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\HealthController;

/*
|--------------------------------------------------------------------------
| Core Module API Routes
|--------------------------------------------------------------------------
| Mounted at /api/v1/core/* (see app/Providers/RouteServiceProvider.php).
| Add module endpoints here — see docs/modules/core.md for the entity map
| and .claude/skills/laravel-endpoint for the scaffold-a-new-endpoint flow.
|
| Health checks stay outside auth/entitlement — load balancers, uptime
| monitors, and container orchestrators must be able to hit them unauthenticated.
*/

Route::prefix('v1/core')->name('core.')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'module' => 'core']))->name('health');
    Route::get('/health/ready', HealthController::class.'@ready')->name('health.ready');
});
