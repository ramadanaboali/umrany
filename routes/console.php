<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// config('sanctum.expiration') (see config/sanctum.php) makes the Sanctum guard itself reject an
// expired token on every request — this just keeps the personal_access_tokens table from growing
// forever. Laravel's built-in command already prunes both expires_at-based and global-expiration-
// based tokens; no custom command needed.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:00');
