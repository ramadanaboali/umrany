<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    /**
     * @param  array<int, string>|null  $accountTypes
     * @return array<string, mixed>
     */
    private function registrationPayload(?array $accountTypes = null, string $email = 'ahmed@example.com'): array
    {
        return array_filter([
            'name' => 'Ahmed',
            'email' => $email,
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => true,
            'account_types' => $accountTypes,
        ], fn ($value) => $value !== null);
    }

    public function test_can_register_with_email_only(): void
    {
        $response = $this->postJson('/api/v1/core/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user.email', 'ahmed@example.com')
            ->assertJsonPath('data.user.email_verified', false)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'ahmed@example.com']);
        $user = User::where('email', 'ahmed@example.com')->first();
        $this->assertNotNull($user->profile, 'a default profile must be created on registration');
        $this->assertSame('active', $user->status->value);
    }

    public function test_can_register_with_mobile_only_no_email(): void
    {
        $response = $this->postJson('/api/v1/core/auth/register', [
            'name' => 'Fatima',
            'mobile' => '+966500000001',
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.user.mobile', '+966500000001');
        $this->assertDatabaseHas('users', ['mobile' => '+966500000001', 'email' => null]);
    }

    public function test_registration_requires_at_least_one_of_email_or_mobile(): void
    {
        $this->postJson('/api/v1/core/auth/register', [
            'name' => 'Nobody',
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors(['email', 'mobile']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/core/auth/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_terms_acceptance(): void
    {
        $this->postJson('/api/v1/core/auth/register', [
            'name' => 'Ahmed',
            'email' => 'ahmed@example.com',
            'password' => 'Secr3tPass',
            'password_confirmation' => 'Secr3tPass',
            'terms_accepted' => false,
        ])->assertStatus(422)->assertJsonValidationErrors('terms_accepted');
    }

    public function test_omitting_account_types_defaults_to_project_owner(): void
    {
        $response = $this->postJson('/api/v1/core/auth/register', $this->registrationPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.capabilities.account_types', ['project_owner']);

        $user = User::where('email', 'ahmed@example.com')->firstOrFail();
        $this->assertSame(['project_owner'], $user->refresh()->account_types);
    }

    public function test_account_types_provider_only_is_accepted_and_creates_no_provider_row(): void
    {
        $response = $this->postJson(
            '/api/v1/core/auth/register',
            $this->registrationPayload(['provider'], 'provider-only@example.com'),
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.capabilities.account_types', ['provider']);

        $user = User::where('email', 'provider-only@example.com')->firstOrFail();
        $this->assertSame(['provider'], $user->account_types);
        $this->assertDatabaseMissing('providers', ['user_id' => $user->id]);
    }

    public function test_account_types_accepts_multi_select_project_owner_and_provider(): void
    {
        $response = $this->postJson(
            '/api/v1/core/auth/register',
            $this->registrationPayload(['project_owner', 'provider'], 'multi@example.com'),
        );

        $response->assertStatus(201);

        $accountTypes = $response->json('data.capabilities.account_types');
        $this->assertIsArray($accountTypes);
        $this->assertEqualsCanonicalizing(['project_owner', 'provider'], $accountTypes);

        $user = User::where('email', 'multi@example.com')->firstOrFail();
        $this->assertEqualsCanonicalizing(['project_owner', 'provider'], $user->account_types);
    }

    public function test_register_response_never_includes_a_provider_key(): void
    {
        $response = $this->postJson(
            '/api/v1/core/auth/register',
            $this->registrationPayload(['project_owner', 'provider'], 'no-provider-key@example.com'),
        );

        $response->assertStatus(201)->assertJsonMissingPath('data.provider');
    }

    public function test_mfa_enroll_on_register_returns_setup_payload_and_login_still_succeeds_directly(): void
    {
        $payload = $this->registrationPayload(null, 'mfa-enroll@example.com');
        $payload['mfa_enroll'] = true;

        $response = $this->postJson('/api/v1/core/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['mfa' => ['secret', 'otpauth_uri', 'qr_svg']]]);

        // Enrollment was never confirmed via POST .../auth/mfa/confirm, so it must not gate login.
        $login = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'mfa-enroll@example.com',
            'password' => 'Secr3tPass',
        ]);

        $login->assertOk()
            ->assertJsonMissingPath('data.mfa_required');
        $this->assertNotEmpty($login->json('data.token'));
    }
}
