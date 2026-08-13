<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deliberately not Laravel's built-in `verified` middleware / MustVerifyEmail contract — those
 * are link-based and email-only. This platform verifies via a single-use OTP code that covers
 * both email and mobile (FR-AUTH-001/002, docs/modules/core.md § Auth), and either channel being
 * verified satisfies "the account is verified" — see App\Models\User::hasVerifiedIdentity().
 */
final class EnsureAccountIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasVerifiedIdentity()) {
            abort(403, 'Please verify your email or mobile number before continuing.');
        }

        return $next($request);
    }
}
