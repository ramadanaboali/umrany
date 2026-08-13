<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class RegisterTest extends TestCase
{
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
}
