<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ERP Module API Routes
|--------------------------------------------------------------------------
| Mounted at /api/v1/erp/* (see app/Providers/RouteServiceProvider.php).
| Add module endpoints here — see docs/modules/erp.md for the entity map
| and .claude/skills/laravel-endpoint for the scaffold-a-new-endpoint flow.
*/

Route::prefix('v1/erp')->name('erp.')->middleware(['auth:sanctum', 'module.entitlement:erp'])->group(function () {
    //
});
