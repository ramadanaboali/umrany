<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Core\Enums\ProviderStatus;
use Modules\Core\Enums\ProviderVerificationStatus;
use Modules\Core\Models\Provider;

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

        $plainUser = User::query()->updateOrCreate(
            ['email' => 'demo.user@umrany.test'],
            [
                'name' => 'Demo Registered User',
                'password' => 'Password123',
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
                'status' => 'active',
            ],
        );

        $plainUser->profile()->updateOrCreate(
            ['user_id' => $plainUser->id],
            ['full_name' => 'Demo Registered User', 'preferred_language' => 'en'],
        );
    }
}
