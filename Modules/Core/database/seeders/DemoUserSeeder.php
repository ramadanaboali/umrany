<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AccountType;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\NotificationPreference;
use Modules\Core\Models\PasswordHistory;
use Modules\Core\Models\Provider;
use Modules\Core\Models\UserDevice;
use Modules\Core\Models\UserMfaSetting;

/**
 * Demonstrates the platform's core architectural claim end-to-end: one account can simultaneously
 * be an ordinary registered user AND hold a verified Provider identity with ERP + E-Commerce
 * entitlement — see docs/business/personas.md "One Account, Multiple Roles". Once
 * Modules/Projects exists, this same account (demo.owner@umrany.test) is the natural one to also
 * seed a demo Project onto, to complete the "same account, many hats" demonstration.
 *
 * Dev/staging only — never called in production (see CoreDatabaseSeeder).
 */
final class DemoUserSeeder extends Seeder
{
    /**
     * A fixed (not randomly generated) base32 TOTP secret so this account's MFA can actually be
     * enrolled in a real authenticator app for manual dev testing, and so Pest tests can compute a
     * valid code for it deterministically instead of driving the enrollment flow first.
     */
    private const DEMO_MFA_SECRET = 'JBSWY3DPEHPK3PXP';

    /**
     * @var array<int, string>
     */
    private const DEMO_RECOVERY_CODES = [
        'demo0-code0', 'demo1-code1', 'demo2-code2', 'demo3-code3',
        'demo4-code4', 'demo5-code5', 'demo6-code6', 'demo7-code7',
    ];

    public function run(): void
    {
        $multiHat = User::query()->updateOrCreate(
            ['email' => 'demo.owner@umrany.test'],
            [
                'name' => 'Demo Multi-Hat Account',
                'password' => 'Password123',
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'status' => 'active',
                'account_types' => [AccountType::ProjectOwner->value, AccountType::Provider->value],
            ],
        );

        $multiHat->profile()->updateOrCreate(
            ['user_id' => $multiHat->id],
            ['full_name' => 'Demo Multi-Hat Account', 'preferred_language' => 'en'],
        );

        $provider = Provider::query()->updateOrCreate(
            ['user_id' => $multiHat->id],
            [
                'company_name' => 'Demo Contracting Co.',
                'status' => ProviderStatus::Published,
                'has_ecommerce_access' => true,
                'has_erp_access' => true,
            ],
        );

        $provider->verification()->updateOrCreate(
            ['provider_id' => $provider->id],
            ['status' => ProviderVerificationStatus::Approved, 'submitted_at' => now(), 'reviewed_at' => now()],
        );

        $this->seedMfa($multiHat);
        $this->seedDevice($multiHat, 'Demo MacBook Pro');
        $this->seedNotificationPreferences($multiHat);
        $this->seedPasswordHistory($multiHat);

        $plainUser = User::query()->updateOrCreate(
            ['email' => 'demo.user@umrany.test'],
            [
                'name' => 'Demo Registered User',
                'password' => 'Password123',
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'status' => 'active',
                'account_types' => [AccountType::ProjectOwner->value],
            ],
        );

        $plainUser->profile()->updateOrCreate(
            ['user_id' => $plainUser->id],
            ['full_name' => 'Demo Registered User', 'preferred_language' => 'en'],
        );

        $this->seedDevice($plainUser, 'Demo iPhone');
        $this->seedPasswordHistory($plainUser);
    }

    /**
     * Confirmed MFA with a known secret + 8 known recovery codes — exercises the "account has MFA
     * enabled" path (two-step login, capability responses) without every test having to drive the
     * enroll→confirm flow first.
     */
    private function seedMfa(User $user): void
    {
        UserMfaSetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['secret' => self::DEMO_MFA_SECRET, 'confirmed_at' => now(), 'last_used_timestamp' => null],
        );

        MfaRecoveryCode::query()->where('user_id', $user->id)->delete();

        foreach (self::DEMO_RECOVERY_CODES as $code) {
            MfaRecoveryCode::query()->create(['user_id' => $user->id, 'code_hash' => Hash::make($code)]);
        }
    }

    private function seedDevice(User $user, string $deviceName): void
    {
        UserDevice::query()->updateOrCreate(
            ['user_id' => $user->id, 'fingerprint' => hash('sha256', "seeded-demo-agent|{$deviceName}")],
            [
                'device_name' => $deviceName,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'seeded-demo-agent',
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * One non-default row (email disabled for the non-mandatory "new device login" event) so the
     * preferences matrix demonstrably differs from an all-defaults account.
     */
    private function seedNotificationPreferences(User $user): void
    {
        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'event_type' => NotificationEvent::NewDeviceLogin->value],
            ['in_app' => true, 'email' => false],
        );
    }

    private function seedPasswordHistory(User $user): void
    {
        PasswordHistory::query()->firstOrCreate(
            ['authenticatable_type' => $user->getMorphClass(), 'authenticatable_id' => $user->id],
            ['password_hash' => $user->password],
        );
    }
}
