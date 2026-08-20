<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Readiness check for the dependencies Octane workers actually need to serve
 * a request: the database and Redis (cache + queue). Distinct from Laravel's
 * built-in liveness probe at /up, which only proves the app booted.
 */
#[Group('Core / Health', weight: 6)]
final class HealthController
{
    /**
     * Liveness check
     */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'module' => 'core']);
    }

    /**
     * Readiness check
     */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => (bool) DB::connection()->getPdo()),
            'redis' => $this->check(fn () => Redis::connection()->ping() !== false),
            'cache' => $this->check(function () {
                $key = 'umrany:core:health-check';
                Cache::put($key, true, 5);

                return Cache::pull($key) === true;
            }),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /**
     * @param  callable(): bool  $probe
     * @return array{ok: bool, error?: string}
     */
    private function check(callable $probe): array
    {
        try {
            return ['ok' => $probe()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
