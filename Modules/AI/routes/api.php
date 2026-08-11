<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Module API Routes
|--------------------------------------------------------------------------
| Mounted at /api/v1/ai/* (see app/Providers/RouteServiceProvider.php).
| Add module endpoints here — see docs/modules/ai.md for the entity map
| and .claude/skills/laravel-endpoint for the scaffold-a-new-endpoint flow.
*/

Route::prefix('v1/ai')->name('ai.')->middleware(['auth:sanctum', 'module.entitlement:ai'])->group(function () {
    //
});
