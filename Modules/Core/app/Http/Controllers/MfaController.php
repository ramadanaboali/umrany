<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Http\Requests\Auth\ConfirmMfaRequest;
use Modules\Core\Http\Requests\Auth\DisableMfaRequest;
use Modules\Core\Http\Requests\Auth\EnableMfaRequest;
use Modules\Core\Http\Requests\Auth\RegenerateRecoveryCodesRequest;
use Modules\Core\Http\Resources\MfaSetupResource;
use Modules\Core\Http\Resources\MfaStatusResource;
use Modules\Core\Services\MfaService;

/**
 * Self-service TOTP MFA management — deliberately ungated by `account.verified` (credential/
 * session hygiene must stay reachable regardless of verification status, see
 * docs/decisions/0015-account-verification-gate-policy.md). The login-time challenge exchange
 * lives on AuthController, not here — this controller only ever runs authenticated.
 */
#[Group('Core / Auth', weight: 1)]
final class MfaController extends Controller
{
    public function __construct(
        private readonly MfaService $mfa,
    ) {}

    /**
     * MFA status
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => new MfaStatusResource($this->mfa->status($request->user()))]);
    }

    /**
     * Enable MFA (step 1 — begin enrollment)
     *
     * Generates a new TOTP secret. MFA is not enforced at login until POST .../auth/mfa/confirm
     * succeeds — an abandoned enrollment can never lock the account out.
     */
    public function enable(EnableMfaRequest $request): JsonResponse
    {
        return response()->json(['data' => new MfaSetupResource($this->mfa->beginEnrollment($request->user()))]);
    }

    /**
     * Confirm MFA enrollment (step 2)
     *
     * Activates the pending enrollment and returns the one-time-visible recovery codes.
     */
    public function confirm(ConfirmMfaRequest $request): JsonResponse
    {
        $recoveryCodes = $this->mfa->confirm($request->user(), $request->string('code')->value());

        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }

    /**
     * Disable MFA
     *
     * Requires the current password AND a valid TOTP/recovery code — a stolen session alone must
     * not be able to silently turn MFA off.
     */
    public function disable(DisableMfaRequest $request): JsonResponse
    {
        $this->mfa->disable($request->user(), $request->string('code')->value());

        return response()->json(null, 204);
    }

    /**
     * Regenerate recovery codes
     *
     * Invalidates every previous recovery code.
     */
    public function regenerateRecoveryCodes(RegenerateRecoveryCodesRequest $request): JsonResponse
    {
        $recoveryCodes = $this->mfa->regenerateRecoveryCodes($request->user(), $request->string('code')->value());

        return response()->json(['data' => ['recovery_codes' => $recoveryCodes]]);
    }
}
