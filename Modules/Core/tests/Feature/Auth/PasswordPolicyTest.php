<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Proves `config('core.password_policy.*')` genuinely drives the shared `Password::defaults()`
 * rule built in `Modules\Core\Providers\CoreServiceProvider::boot()` — not a hardcoded rule
 * literal — by overriding config at runtime and confirming validation behavior changes
 * accordingly. Exercised through POST /auth/register since RegisterRequest applies
 * Password::defaults() directly with no other password-shaped rule alongside it. See
 * docs/decisions/0013-config-driven-password-policy-and-history.md.
 */
class PasswordPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // /auth/register is throttled by the named 'login' limiter (5/minute, keyed by the
        // "login" input + IP) — several tests below make more than 5 registration attempts to
        // exercise both sides of a config toggle, so the limiter must not interfere.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(string $email, string $password): array
    {
        return [
            'name' => 'Ahmed',
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
            'terms_accepted' => true,
        ];
    }

    public function test_a_password_shorter_than_the_default_min_length_is_rejected(): void
    {
        // 7 characters — otherwise satisfies mixed case + a number, so length is the only thing
        // this can be failing on.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('short@example.com', 'Ab1cd12'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'short@example.com']);
    }

    public function test_overriding_min_length_rejects_a_password_that_previously_passed(): void
    {
        // 10 characters — passes the default min_length of 8.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('ten-chars@example.com', 'Abcdef1234'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'ten-chars@example.com']);

        config(['core.password_policy.min_length' => 12]);

        // The exact same 10-character password now fails against a different account.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('still-ten-chars@example.com', 'Abcdef1234'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'still-ten-chars@example.com']);

        // A 12-character password satisfies the tightened policy.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('twelve-chars@example.com', 'Abcdefgh1234'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'twelve-chars@example.com']);
    }

    public function test_require_mixed_case_rejects_a_password_missing_it(): void
    {
        // All-lowercase + digits, 12 characters — satisfies length and numbers, missing case mix.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('no-mixed-case@example.com', 'lowercase123'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'no-mixed-case@example.com']);

        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('mixed-case@example.com', 'Lowercase123'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'mixed-case@example.com']);
    }

    public function test_require_numbers_rejects_a_password_missing_a_digit(): void
    {
        // Mixed case, no digit at all.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('no-numbers@example.com', 'NoNumbersHere'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'no-numbers@example.com']);

        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('has-numbers@example.com', 'HasNumbers1'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'has-numbers@example.com']);
    }

    public function test_toggling_require_symbols_on_starts_rejecting_a_password_with_no_symbol(): void
    {
        // require_symbols defaults to false — a password with no symbol passes as-is.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('no-symbol-default@example.com', 'Abcdefgh12'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'no-symbol-default@example.com']);

        config(['core.password_policy.require_symbols' => true]);

        // The same style of password (still no symbol) now fails against a different account.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('no-symbol-required@example.com', 'Abcdefgh13'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'no-symbol-required@example.com']);

        // Adding a symbol satisfies the tightened policy.
        $this->postJson('/api/v1/core/auth/register', $this->registrationPayload('has-symbol@example.com', 'Abcdefgh1!'))
            ->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'has-symbol@example.com']);
    }
}
