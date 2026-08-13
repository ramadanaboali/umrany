<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Core\Enums\VerificationCodeType;
use Modules\Core\Http\Requests\Auth\ChangePasswordRequest;
use Modules\Core\Http\Requests\Auth\ForgotPasswordRequest;
use Modules\Core\Http\Requests\Auth\LoginRequest;
use Modules\Core\Http\Requests\Auth\RegisterRequest;
use Modules\Core\Http\Requests\Auth\ResendVerificationCodeRequest;
use Modules\Core\Http\Requests\Auth\ResetPasswordRequest;
use Modules\Core\Http\Requests\Auth\VerifyAccountRequest;
use Modules\Core\Http\Resources\UserResource;
use Modules\Core\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /**
     * Register
     *
     * Create an account with a mobile number or email address (at least one is required).
     * Issues a verification code to whichever identifier was provided and returns an API token
     * immediately — the account can authenticate right away, but protected endpoints stay
     * gated behind `account.verified` until the code is confirmed via POST .../auth/verify.
     *
     * @group Core / Auth
     *
     * @bodyParam name string required Full name. Example: Ahmed Al-Otaibi
     * @bodyParam email string Email address. Required if mobile is omitted. Example: ahmed@example.com
     * @bodyParam mobile string Mobile number. Required if email is omitted. Example: +966501234567
     * @bodyParam password string required Min 8 chars, mixed case, numbers. Example: Secr3tPass
     * @bodyParam password_confirmation string required Example: Secr3tPass
     * @bodyParam terms_accepted boolean required Must be true. Example: true
     *
     * @response 201 scenario="success" {"data": {"user": {"id": 1, "name": "Ahmed Al-Otaibi", "email": "ahmed@example.com", "mobile": null, "email_verified": false, "mobile_verified": false, "status": "active"}, "token": "1|abcdef..."}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->register($request->validated());

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
        ], 201);
    }

    /**
     * Login
     *
     * Authenticate with either the registered mobile number or email address.
     *
     * @group Core / Auth
     *
     * @bodyParam login string required Email or mobile number. Example: ahmed@example.com
     * @bodyParam password string required Example: Secr3tPass
     * @bodyParam device_name string Optional label for this session/device. Example: iPhone 15
     *
     * @response 200 scenario="success" {"data": {"user": {"id": 1}, "token": "1|abcdef..."}}
     * @response 422 scenario="invalid credentials" {"message": "The given data was invalid.", "errors": {"login": ["These credentials do not match our records."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->login(
            $request->string('login')->value(),
            $request->string('password')->value(),
            $request->string('device_name')->value() ?: null,
            $request->ip(),
        );

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token->plainTextToken,
            ],
        ]);
    }

    /**
     * Logout
     *
     * Revokes the token used to authenticate the current request (this device/session only —
     * see .../auth/sessions to revoke others).
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @response 204
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    /**
     * Resend verification code
     *
     * Rate-limited to one request per minute per account — see docs/api/conventions.md.
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @bodyParam type string required "email" or "mobile" — must match an identifier already on the account. Example: email
     *
     * @response 200 scenario="success" {"message": "Verification code sent."}
     * @response 422 scenario="already verified or channel not on account" {"message": "This account has no email address on file."}
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
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @bodyParam type string required "email" or "mobile". Example: email
     * @bodyParam code string required The 6-digit code just received. Example: 483920
     *
     * @response 200 scenario="success" {"data": {"user": {"id": 1, "email_verified": true}}}
     * @response 422 scenario="invalid or expired" {"message": "This code is invalid or has expired."}
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
     * Forgot password
     *
     * Always responds with a generic success message whether or not the identifier matches an
     * account, so this endpoint can never be used to enumerate registered emails/mobiles.
     *
     * @group Core / Auth
     *
     * @bodyParam login string required Email or mobile number on the account. Example: ahmed@example.com
     *
     * @response 200 scenario="always" {"message": "If that account exists, a reset code has been sent."}
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
     *
     * @group Core / Auth
     *
     * @bodyParam login string required Same identifier used to request the code. Example: ahmed@example.com
     * @bodyParam code string required Example: 483920
     * @bodyParam password string required Example: NewSecr3t1
     * @bodyParam password_confirmation string required Example: NewSecr3t1
     *
     * @response 200 scenario="success" {"message": "Password has been reset. Please sign in again."}
     * @response 422 scenario="invalid or expired" {"message": "This code is invalid or has expired."}
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
     *
     * @group Core / Auth
     *
     * @authenticated
     *
     * @bodyParam current_password string required Example: OldSecr3t1
     * @bodyParam password string required Example: NewSecr3t1
     * @bodyParam password_confirmation string required Example: NewSecr3t1
     *
     * @response 200 scenario="success" {"message": "Password changed."}
     * @response 422 scenario="wrong current password" {"message": "The given data was invalid.", "errors": {"current_password": ["The password is incorrect."]}}
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->string('password')->value());

        return response()->json(['message' => 'Password changed.']);
    }

    private function findUserByLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('mobile', $login)
            ->first();
    }

    private function channelFor(User $user, string $login): VerificationCodeType
    {
        return Str::lower($user->email ?? '') === Str::lower($login)
            ? VerificationCodeType::Email
            : VerificationCodeType::Mobile;
    }
}
