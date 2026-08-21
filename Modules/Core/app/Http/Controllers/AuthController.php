<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Http\Requests\Auth\ChangePasswordRequest;
use Modules\Core\Http\Requests\Auth\DeleteAccountRequest;
use Modules\Core\Http\Requests\Auth\ForgotPasswordRequest;
use Modules\Core\Http\Requests\Auth\LoginRequest;
use Modules\Core\Http\Requests\Auth\MfaChallengeRequest;
use Modules\Core\Http\Requests\Auth\RegisterRequest;
use Modules\Core\Http\Requests\Auth\ResendVerificationCodeRequest;
use Modules\Core\Http\Requests\Auth\ResendVerificationPublicRequest;
use Modules\Core\Http\Requests\Auth\ResetPasswordRequest;
use Modules\Core\Http\Requests\Auth\VerifyAccountPublicRequest;
use Modules\Core\Http\Requests\Auth\VerifyAccountRequest;
use Modules\Core\Http\Resources\AuthPayloadResource;
use Modules\Core\Http\Resources\MfaSetupResource;
use Modules\Core\Http\Resources\UserResource;
use Modules\Core\Repositories\Contracts\UserRepositoryInterface;
use Modules\Core\Services\AuthService;
use Modules\Core\Services\MfaService;

#[Group('Core / Auth', weight: 1)]
final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRepositoryInterface $users,
        private readonly MfaService $mfa,
    ) {}

    /**
     * Register
     *
     * Create an account with a mobile number or email address (at least one is required).
     * Issues a verification code to whichever identifier was provided and returns an API token
     * immediately, usable right away to complete verification (POST .../auth/verify) — but the
     * account cannot sign in again via POST .../auth/login until it's verified. If this token is
     * lost before verifying, POST .../auth/resend-verification and .../auth/verify-account are
     * the recovery path (no session required). See docs/decisions/0026-block-login-until-account-
     * verified.md.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $this->auth->register($request->validated());

        $data = (new AuthPayloadResource($payload))->resolve($request);

        if ($request->boolean('mfa_enroll')) {
            $data['mfa'] = (new MfaSetupResource($this->mfa->beginEnrollment($payload->user)))->toArray($request);
        }

        return response()->json(['data' => $data], 201);
    }

    /**
     * Login
     *
     * Authenticate with either the registered mobile number or email address.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->string('login')->value(),
            $request->string('password')->value(),
            $request->string('device_name')->value() ?: null,
            $request->ip(),
            $request->userAgent(),
        );

        if ($result['mfa_required']) {
            return response()->json(['data' => [
                'mfa_required' => true,
                'challenge_token' => $result['challenge_token'],
                'expires_in' => $result['expires_in'],
            ]]);
        }

        return response()->json(['data' => (new AuthPayloadResource($result['payload']))->resolve($request)]);
    }

    /**
     * Complete MFA login challenge
     *
     * Exchanges a challenge_token from a "mfa_required" login response, plus a TOTP or recovery
     * code, for the same session shape a direct login would return.
     */
    public function mfaChallenge(MfaChallengeRequest $request): JsonResponse
    {
        $payload = $this->auth->consumeMfaChallenge(
            $request->string('challenge_token')->value(),
            $request->string('code')->value(),
            $request->userAgent(),
        );

        if ($payload === null) {
            return response()->json(['message' => 'This challenge is invalid or has expired.'], 422);
        }

        return response()->json(['data' => (new AuthPayloadResource($payload))->resolve($request)]);
    }

    /**
     * Logout
     *
     * Revokes the token used to authenticate the current request (this device/session only —
     * see .../auth/sessions to revoke others).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    /**
     * Logout everywhere
     *
     * Revokes every session token, including the one used for this request.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();

        return response()->json(null, 204);
    }

    /**
     * Resend verification code
     *
     * Rate-limited to one request per minute per account — see docs/api/conventions.md.
     */
    public function resendCode(ResendVerificationCodeRequest $request): JsonResponse
    {
        $user = $request->user();
        $type = VerificationCodeType::from($request->string('type')->value());

        if ($type === VerificationCodeType::Email && $user->email === null) {
            return response()->json(['message' => 'This account has no email address on file.'], 422);
        }

        if ($type === VerificationCodeType::Mobile && $user->mobile === null) {
            return response()->json(['message' => 'This account has no mobile number on file.'], 422);
        }

        $alreadyVerified = $type === VerificationCodeType::Email
            ? $user->email_verified_at !== null
            : $user->mobile_verified_at !== null;

        if ($alreadyVerified) {
            return response()->json(['message' => 'This channel is already verified.'], 422);
        }

        $this->auth->resendVerificationCode($user, $type);

        return response()->json(['message' => 'Verification code sent.']);
    }

    /**
     * Verify account
     */
    public function verify(VerifyAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $type = VerificationCodeType::from($request->string('type')->value());

        $verified = $this->auth->verifyAccount($user, $type, $request->string('code')->value());

        if (! $verified) {
            return response()->json(['message' => 'This code is invalid or has expired.'], 422);
        }

        return response()->json(['data' => ['user' => new UserResource($user->refresh())]]);
    }

    /**
     * Resend verification code (no session required)
     *
     * Recovery path for an account that lost its only session before ever verifying — since
     * .../auth/login rejects an unverified account, this is otherwise the only way back in. Always
     * responds with the same generic message regardless of whether the identifier matches a real,
     * not-yet-verified account, so this can't be used to enumerate registered emails/mobiles.
     */
    public function resendVerificationPublic(ResendVerificationPublicRequest $request): JsonResponse
    {
        $login = $request->string('login')->value();
        $user = $this->findUserByLogin($login);

        if ($user !== null) {
            $type = $this->channelFor($user, $login);
            $alreadyVerified = $type === VerificationCodeType::Email
                ? $user->email_verified_at !== null
                : $user->mobile_verified_at !== null;

            if (! $alreadyVerified) {
                $this->auth->resendVerificationCode($user, $type);
            }
        }

        return response()->json(['message' => 'If that account exists and needs verification, a code has been sent.']);
    }

    /**
     * Verify account (no session required)
     *
     * Same recovery path as above, completing verification with the code just sent. On success,
     * sign in normally via POST .../auth/login — this endpoint doesn't itself issue a session.
     */
    public function verifyAccountPublic(VerifyAccountPublicRequest $request): JsonResponse
    {
        $login = $request->string('login')->value();
        $user = $this->findUserByLogin($login);

        if ($user === null) {
            // Same message as the real failure path below — no distinction leaks account existence.
            return response()->json(['message' => 'This code is invalid or has expired.'], 422);
        }

        $verified = $this->auth->verifyAccount($user, $this->channelFor($user, $login), $request->string('code')->value());

        if (! $verified) {
            return response()->json(['message' => 'This code is invalid or has expired.'], 422);
        }

        return response()->json(['message' => 'Account verified. Please sign in.']);
    }

    /**
     * Forgot password
     *
     * Always responds with a generic success message whether or not the identifier matches an
     * account, so this endpoint can never be used to enumerate registered emails/mobiles.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $login = $request->string('login')->value();
        $user = $this->findUserByLogin($login);

        if ($user !== null) {
            $this->auth->requestPasswordReset($user, $this->channelFor($user, $login));
        }

        return response()->json(['message' => 'If that account exists, a reset code has been sent.']);
    }

    /**
     * Reset password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $login = $request->string('login')->value();
        $user = $this->findUserByLogin($login);

        if ($user === null) {
            // Same message as the real failure path below — no distinction leaks account existence.
            return response()->json(['message' => 'This code is invalid or has expired.'], 422);
        }

        $reset = $this->auth->resetPassword(
            $user,
            $this->channelFor($user, $login),
            $request->string('code')->value(),
            $request->string('password')->value(),
        );

        if (! $reset) {
            return response()->json(['message' => 'This code is invalid or has expired.'], 422);
        }

        return response()->json(['message' => 'Password has been reset. Please sign in again.']);
    }

    /**
     * Change password
     *
     * Authenticated equivalent of reset-password — for a user who knows their current password
     * and wants to set a new one, rather than the unauthenticated OTP-based recovery flow above.
     * Revokes every *other* session's token (the one used for this request stays valid). Sends
     * the same email confirmation as a completed reset.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->string('password')->value());

        return response()->json(['message' => 'Password changed.']);
    }

    /**
     * Delete account
     *
     * Soft-deletes the account (password-confirmed) and revokes every session token. The
     * account's email/mobile become available for a new registration — see docs/decisions/
     * 0014-user-soft-deletes-and-partial-unique-indexes.md.
     */
    public function destroyAccount(DeleteAccountRequest $request): JsonResponse
    {
        $this->auth->deleteAccount($request->user());

        return response()->json(null, 204);
    }

    private function findUserByLogin(string $login): ?User
    {
        return $this->users->findByLoginIdentifier($login);
    }

    private function channelFor(User $user, string $login): VerificationCodeType
    {
        return Str::lower($user->email ?? '') === Str::lower($login)
            ? VerificationCodeType::Email
            : VerificationCodeType::Mobile;
    }
}
