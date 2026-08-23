<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Modules\Core\Enums\Language;
use Modules\Core\Notifications\PasswordChangedNotification;
use Tests\TestCase;

/**
 * Covers Modules\Core\Http\Middleware\SetLocaleFromRequest (Accept-Language header, falling back
 * to the authenticated user's own stored preference) and the resulting localized notification
 * content — see docs/decisions/0029-centralized-notification-service-and-api-locale.md.
 */
class ApiLocaleTest extends TestCase
{
    public function test_accept_language_header_produces_arabic_notification_content(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept-Language', 'ar')
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'Passw0rd1!',
                'password' => 'NewPassw0rd1!',
                'password_confirmation' => 'NewPassw0rd1!',
            ])
            ->assertOk();

        $notification = $user->notifications()->where('type', PasswordChangedNotification::class)->firstOrFail();

        $this->assertSame(__('core::notifications.password_changed.in_app', [], 'ar'), $notification->data['message']);
    }

    public function test_a_non_matching_accept_language_header_falls_back_to_the_users_stored_preference(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);
        $user->profile()->create(['full_name' => $user->name, 'preferred_language' => Language::Arabic]);

        $token = $user->createToken('test')->plainTextToken;

        // "fr" isn't one of Language::values() — this must fall through to the stored preference
        // rather than blindly defaulting to whichever locale happens to be listed first.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept-Language', 'fr')
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'Passw0rd1!',
                'password' => 'NewPassw0rd1!',
                'password_confirmation' => 'NewPassw0rd1!',
            ])
            ->assertOk();

        $notification = $user->notifications()->where('type', PasswordChangedNotification::class)->firstOrFail();

        $this->assertSame(__('core::notifications.password_changed.in_app', [], 'ar'), $notification->data['message']);
    }

    public function test_accept_language_header_overrides_the_users_stored_preference(): void
    {
        $user = User::factory()->create(['email_verified_at' => now(), 'password' => 'Passw0rd1!']);
        $user->profile()->create(['full_name' => $user->name, 'preferred_language' => Language::Arabic]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Accept-Language', 'en')
            ->putJson('/api/v1/core/auth/password', [
                'current_password' => 'Passw0rd1!',
                'password' => 'NewPassw0rd1!',
                'password_confirmation' => 'NewPassw0rd1!',
            ])
            ->assertOk();

        $notification = $user->notifications()->where('type', PasswordChangedNotification::class)->firstOrFail();

        $this->assertSame(__('core::notifications.password_changed.in_app', [], 'en'), $notification->data['message']);
    }
}
