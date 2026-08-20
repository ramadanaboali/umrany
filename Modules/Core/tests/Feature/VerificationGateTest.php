<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Modules\Core\Models\Currency;
use Tests\TestCase;

/**
 * Exercises the `account.verified` gate split documented in Modules/Core/routes/api.php and
 * docs/decisions/0015-account-verification-gate-policy.md: gate everything that consumes
 * resources or produces platform-visible state; never gate identity-repair/credential-hygiene/
 * exit actions. Every route below is copied verbatim from the live route file at the time this
 * test was written — if a route moves between groups, this test (not just the route file) needs
 * updating.
 */
class VerificationGateTest extends TestCase
{
    private const BASE = '/api/v1/core';

    /**
     * @return array{0: User, 1: string}
     */
    private function unverifiedUserToken(): array
    {
        // Matches the existing convention in ProviderTest::test_unverified_account_cannot_
        // activate_a_provider_profile — no email_verified_at, no mobile at all, so
        // User::hasVerifiedIdentity() is false on both channels.
        $user = User::factory()->create(['email_verified_at' => null, 'mobile_verified_at' => null]);
        $user->profile()->create(['full_name' => $user->name]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /**
     * Sends a request as a fresh unverified user (fresh per call so one route's side effects —
     * revoked tokens, deleted account, consumed rate-limit budget — never bleed into the next
     * case in the loop) and returns the response.
     */
    private function sendAsUnverified(string $method, string $uri, array $body = [], bool $multipart = false): TestResponse
    {
        [, $token] = $this->unverifiedUserToken();
        $headers = ['Authorization' => "Bearer {$token}"];
        $url = self::BASE.$uri;

        if ($multipart) {
            // File uploads can't go through *Json() helpers (they'd JSON-encode the UploadedFile);
            // match ProviderTest/ProfileTest's existing convention of a plain post()/put() with a
            // multipart-shaped array instead.
            return match ($method) {
                'POST' => $this->withHeaders($headers)->post($url, $body),
                default => throw new \InvalidArgumentException("Unsupported multipart method {$method}"),
            };
        }

        return match ($method) {
            'GET' => $this->withHeaders($headers)->getJson($url),
            'POST' => $this->withHeaders($headers)->postJson($url, $body),
            'PUT' => $this->withHeaders($headers)->putJson($url, $body),
            'DELETE' => $this->withHeaders($headers)->deleteJson($url, $body),
            default => throw new \InvalidArgumentException("Unsupported method {$method}"),
        };
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<string, mixed>, 3: bool}>
     */
    private function gatedRoutes(): array
    {
        $currency = Currency::create(['code' => 'SAR', 'name_en' => 'Riyal', 'name_ar' => 'ريال', 'symbol' => 'SAR', 'is_active' => true]);

        return [
            ['POST', '/profile/avatar', ['avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200)], true],
            ['DELETE', '/profile/avatar', [], false],
            ['PUT', '/profile/language', ['language' => 'ar'], false],
            ['PUT', '/profile/currency', ['currency_id' => $currency->id], false],
            ['GET', '/me/capabilities', [], false],
            ['GET', '/providers/me', [], false],
            ['POST', '/providers', ['company_name' => 'Acme Co'], false],
            ['PUT', '/providers/me', ['company_name' => 'Acme Co'], false],
            ['POST', '/providers/me/logo', ['logo' => UploadedFile::fake()->image('logo.jpg')], true],
            ['DELETE', '/providers/me/logo', [], false],
            ['POST', '/providers/me/cover', ['cover' => UploadedFile::fake()->image('cover.jpg')], true],
            ['DELETE', '/providers/me/cover', [], false],
            ['POST', '/providers/me/verification', [
                'documents' => [
                    ['type' => 'commercial_registration', 'file' => UploadedFile::fake()->create('cr.pdf', 100, 'application/pdf')],
                ],
            ], true],
            ['GET', '/me/notification-preferences', [], false],
            ['PUT', '/me/notification-preferences', [
                'preferences' => [['event_type' => 'new_device_login', 'in_app' => true, 'email' => true]],
            ], false],
            ['GET', '/me/notifications', [], false],
            ['PUT', '/me/notifications/999999/read', [], false],
            ['POST', '/me/notifications/read-all', [], false],
        ];
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function ungatedRoutes(): array
    {
        return [
            ['POST', '/auth/logout', []],
            ['POST', '/auth/logout-all', []],
            ['POST', '/auth/verify', ['type' => 'email', 'code' => '000000']],
            ['POST', '/auth/resend-code', ['type' => 'email']],
            ['PUT', '/auth/password', ['current_password' => 'password', 'password' => 'NewSecr3t1', 'password_confirmation' => 'NewSecr3t1']],
            ['DELETE', '/auth/account', ['current_password' => 'password']],
            ['GET', '/auth/sessions', []],
            ['DELETE', '/auth/sessions', []],
            ['DELETE', '/auth/sessions/999999999', []],
            ['GET', '/auth/mfa', []],
            ['POST', '/auth/mfa', ['current_password' => 'password']],
            ['POST', '/auth/mfa/confirm', ['code' => '123456']],
            ['DELETE', '/auth/mfa', ['current_password' => 'password', 'code' => '123456']],
            ['POST', '/auth/mfa/recovery-codes', ['current_password' => 'password', 'code' => '123456']],
            ['GET', '/profile', []],
            ['PUT', '/profile', ['full_name' => 'Someone Else']],
        ];
    }

    public function test_unverified_user_is_blocked_from_every_gated_route(): void
    {
        Storage::fake('public');

        foreach ($this->gatedRoutes() as [$method, $uri, $body, $multipart]) {
            $response = $this->sendAsUnverified($method, $uri, $body, $multipart);
            $this->assertSame(403, $response->getStatusCode(), "Expected {$method} {$uri} to be blocked (403) for an unverified user.");
        }
    }

    public function test_unverified_user_is_never_blocked_from_ungated_routes(): void
    {
        foreach ($this->ungatedRoutes() as [$method, $uri, $body]) {
            $status = $this->sendAsUnverified($method, $uri, $body)->getStatusCode();

            $this->assertNotSame(403, $status, "Expected {$method} {$uri} to stay reachable (never 403) for an unverified user, got {$status}.");
        }
    }

    /**
     * Regression guard mirroring ProfileTest::test_changing_email_clears_verified_status_and_
     * requires_reverification, but framed explicitly around the gate: an unverified user must
     * still be able to correct a mistyped email via PUT /profile — that's the one identity-repair
     * path that would otherwise cause an unrecoverable lockout (a typo'd address can never
     * receive a code) if it were ever accidentally gated.
     */
    public function test_unverified_user_can_still_correct_email_via_profile_update(): void
    {
        [$user, $token] = $this->unverifiedUserToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile', ['email' => 'corrected@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'corrected@example.com']);
        $this->assertDatabaseHas('verification_codes', [
            'user_id' => $user->id,
            'type' => 'email',
            'purpose' => 'account_verification',
        ]);
    }
}
