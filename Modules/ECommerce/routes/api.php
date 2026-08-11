<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ECommerce Module API Routes
|--------------------------------------------------------------------------
| Mounted at /api/v1/ecommerce/* (see app/Providers/RouteServiceProvider.php).
| Add module endpoints here — see docs/modules/ecommerce.md for the entity map
| and .claude/skills/laravel-endpoint for the scaffold-a-new-endpoint flow.
*/

Route::prefix('v1/ecommerce')->name('ecommerce.')->middleware(['auth:sanctum', 'module.entitlement:ecommerce'])->group(function () {
    //
});
