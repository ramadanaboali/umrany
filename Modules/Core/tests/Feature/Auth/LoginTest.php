<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Models\Provider;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_can_log_in_with_email(): void
    {
        $user = User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'ahmed@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_can_log_in_with_mobile(): void
    {
        User::factory()->create(['mobile' => '+966500000002', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => '+966500000002',
            'password' => 'Secr3tPass',
        ])->assertOk();
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        User::factory()->create(['email' => 'ahmed@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'ahmed@example.com',
            'password' => 'WrongPassword',
        ])->assertStatus(422)->assertJsonValidationErrors('login');
    }

    public function test_unknown_login_is_rejected_with_the_same_generic_message_as_wrong_password(): void
    {
        $unknown = $this->postJson('/api/v1/core/auth/login', [
            'login' => 'nobody@example.com',
            'password' => 'WhateverPass1',
        ]);
        $wrongPassword = $this->postJson('/api/v1/core/auth/login', [
            'login' => (User::factory()->create(['email' => 'real@example.com', 'password' => 'RealPass123']))->email,
            'password' => 'NotRealPass',
        ]);

        // Same message for "no such account" and "wrong password" — anti-enumeration.
        $this->assertSame(
            $unknown->json('errors.login.0'),
            $wrongPassword->json('errors.login.0'),
        );
    }

    public function test_suspended_account_cannot_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => 'Secr3tPass',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'suspended@example.com',
            'password' => 'Secr3tPass',
        ])->assertStatus(422)->assertJsonValidationErrors('login');
    }

    /**
     * Regression test for docs/decisions/0026-block-login-until-account-verified.md — an
     * unverified account (whatever its status — this one is explicitly Active to isolate the
     * check from status entirely) must be rejected at login with a distinct message, not the
     * generic anti-enumeration one, since the password is genuinely correct at this point.
     */
    public function test_unverified_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => 'Secr3tPass',
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'unverified@example.com',
            'password' => 'Secr3tPass',
        ])->assertStatus(422)->assertJsonPath('errors.login.0', 'Please verify your account before signing in.');
    }

    public function test_verified_account_with_pending_verification_status_can_log_in_normally(): void
    {
        // status is only a byproduct of registration defaulting to PendingVerification — a
        // verified identity is what actually gates login, independent of status.
        User::factory()->create([
            'email' => 'edge-case@example.com',
            'password' => 'Secr3tPass',
            'status' => UserStatus::PendingVerification,
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'edge-case@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test');

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/v1/core/auth/logout')
            ->assertStatus(204);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_response_includes_provider_key_when_account_has_an_active_provider(): void
    {
        $user = User::factory()->create(['email' => 'provider-login@example.com', 'password' => 'Secr3tPass']);
        Provider::forceCreate([
            'user_id' => $user->id,
            'company_name' => 'Acme',
            'status' => ProviderStatus::Published,
            'has_ecommerce_access' => true,
            'has_erp_access' => false,
        ]);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'provider-login@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk()
            ->assertJsonPath('data.provider.company_name', 'Acme')
            ->assertJsonPath('data.capabilities.is_provider', true);
    }

    public function test_login_response_omits_provider_key_for_a_plain_non_provider_user(): void
    {
        User::factory()->create(['email' => 'plain-login@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'plain-login@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk()->assertJsonMissingPath('data.provider');
    }

    public function test_login_response_includes_capabilities_structure(): void
    {
        User::factory()->create(['email' => 'capabilities-login@example.com', 'password' => 'Secr3tPass']);

        $this->postJson('/api/v1/core/auth/login', [
            'login' => 'capabilities-login@example.com',
            'password' => 'Secr3tPass',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['capabilities' => ['is_provider', 'is_project_owner', 'account_types']]]);
    }
}
