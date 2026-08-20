<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Notifications\PasswordChangedNotification;
use Tests\TestCase;

class ChangePasswordTest extends TestCase
{
    public function test_can_change_password_with_correct_current_password(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => 'OldPassword1']);
        $token = $user->createToken('device-a');
        $otherToken = $user->createToken('device-b');

        $this->withHeader('Authorization', "Bearer {$token->plainTextToken}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'OldPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('NewPassword1', $user->refresh()->password));
        Notification::assertSentTo($user, PasswordChangedNotification::class);

        // The token used to make this request stays valid; every other token is revoked.
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldPassword1']);
        $token = $user->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'WrongPassword',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('OldPassword1', $user->refresh()->password));
    }

    public function test_password_changed_notification_has_no_mail_channel_for_a_mobile_only_account(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => null, 'mobile' => '+966500000099', 'password' => 'OldPassword1']);
        $token = $user->createToken('device-a')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'OldPassword1',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ])
            ->assertOk();

        // In-app notifications never depend on having an email on file — only the mail channel
        // is unreachable for a mobile-only account.
        Notification::assertSentTo(
            $user,
            PasswordChangedNotification::class,
            fn ($notification, array $channels) => in_array('database', $channels, true) && ! in_array('mail', $channels, true),
        );
    }
}
