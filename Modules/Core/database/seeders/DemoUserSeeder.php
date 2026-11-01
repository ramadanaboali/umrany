<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Enums\AccountType;
use Modules\Core\Enums\GovernmentVerificationStatus;
use Modules\Core\Enums\NotificationEvent;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\MfaRecoveryCode;
use Modules\Core\Models\NotificationPreference;
use Modules\Core\Models\PasswordHistory;
use Modules\Core\Models\Provider;
use Modules\Core\Models\ProviderCategory;
use Modules\Core\Models\ProviderSubcategory;
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
                'commercial_registration_number' => 'CR-DEMO-1001',
            ],
        );

        $provider->verification()->updateOrCreate(
            ['provider_id' => $provider->id],
            ['status' => ProviderVerificationStatus::Approved, 'submitted_at' => now(), 'reviewed_at' => now()],
        );

        // FR-PROVIDER-003: exercise the categories pivot too, not just the schema — the demo
        // provider offers Contractor + Supplier services (ProviderCategorySeeder must run before
        // this seeder — see CoreDatabaseSeeder's ordering).
        $demoCategorySlugs = ['contractor', 'supplier'];
        $provider->categories()->sync(
            ProviderCategory::query()->whereIn('slug', $demoCategorySlugs)->pluck('id')->all(),
        );

        // FR-PROVIDER-003 (root CLAUDE.md Rule 6): exercise the (always optional) subcategory
        // pivot too — see docs/decisions/0032-provider-subcategories.md.
        $demoSubcategorySlugs = ['general-contracting', 'building-materials'];
        $provider->subcategories()->sync(
            ProviderSubcategory::query()->whereIn('slug', $demoSubcategorySlugs)->pluck('id')->all(),
        );

        // FR-PROVIDER-006/007: exercise the portfolio/certificate schema too, not just categories
        // (root CLAUDE.md Rule 6 — a new Provider sub-entity needs a realistic seeded example).
        $provider->portfolios()->updateOrCreate(
            ['provider_id' => $provider->id, 'title' => 'Villa Renovation — Al Nakheel District'],
            [
                'provider_category_id' => ProviderCategory::query()->where('slug', 'contractor')->value('id'),
                'description' => 'Full interior and structural renovation of a 4-bedroom villa, completed on schedule.',
                'completion_date' => now()->subMonths(6)->toDateString(),
                'project_location' => 'Riyadh, Saudi Arabia',
            ],
        );

        $provider->certificates()->updateOrCreate(
            ['provider_id' => $provider->id, 'name' => 'ISO 9001:2015 Quality Management'],
            ['issuer' => 'Saudi Standards, Metrology and Quality Organization (SASO)', 'issued_date' => now()->subYears(2)->toDateString()],
        );

        // FR-PROVIDER-004 (root CLAUDE.md Rule 6): exercise the mock government-verification
        // schema too — a completed, unapplied check, so the admin "apply to profile" action has a
        // real example to demonstrate. See docs/decisions/0031-government-verification-mock-
        // integration.md.
        $provider->governmentVerificationRequests()->updateOrCreate(
            ['provider_id' => $provider->id, 'commercial_registration_number' => 'CR-DEMO-1001'],
            [
                'status' => GovernmentVerificationStatus::Completed,
                'source' => 'mock',
                'imported_data' => [
                    'legal_business_name' => 'Registered Business CR-DEMO-1001',
                    'registration_status' => 'active',
                    'establishment_date' => '2015-01-01',
                    'business_activities' => ['General Contracting', 'Construction Consultancy'],
                    'registered_address' => 'Mock Registered Address, Riyadh, Saudi Arabia',
                ],
                'requested_at' => now()->subDay(),
                'completed_at' => now()->subDay(),
            ],
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
