<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\City;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    private function actingUser(): array
    {
        $user = User::factory()->create();
        $user->profile()->create(['full_name' => $user->name]);

        return [$user, $user->createToken('test')->plainTextToken];
    }

    public function test_can_view_own_profile(): void
    {
        [$user, $token] = $this->actingUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/core/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_can_update_full_name_and_address(): void
    {
        [, $token] = $this->actingUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile', ['full_name' => 'New Name', 'address' => 'New Address'])
            ->assertOk()
            ->assertJsonPath('data.full_name', 'New Name')
            ->assertJsonPath('data.address', 'New Address');
    }

    public function test_changing_email_clears_verified_status_and_requires_reverification(): void
    {
        [$user, $token] = $this->actingUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile', ['email' => 'new-email@example.com'])
            ->assertOk()
            ->assertJsonPath('data.email_verified', false);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new-email@example.com', 'email_verified_at' => null]);
        $this->assertDatabaseHas('verification_codes', ['user_id' => $user->id, 'type' => 'email', 'purpose' => 'account_verification']);
    }

    public function test_cannot_clear_both_email_and_mobile(): void
    {
        [$user] = $this->actingUser();
        $user->forceFill(['mobile' => null])->save(); // email-only account
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile', ['email' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_city_must_belong_to_the_selected_country(): void
    {
        [, $token] = $this->actingUser();

        $countryA = Country::create(['code' => 'AA', 'name_en' => 'A', 'name_ar' => 'A', 'is_active' => true]);
        $countryB = Country::create(['code' => 'BB', 'name_en' => 'B', 'name_ar' => 'B', 'is_active' => true]);
        $cityInB = City::create(['country_id' => $countryB->id, 'name_en' => 'CityB', 'name_ar' => 'CityB', 'is_active' => true]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile', ['country_id' => $countryA->id, 'city_id' => $cityInB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');
    }

    public function test_can_set_language_preference(): void
    {
        [, $token] = $this->actingUser();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile/language', ['language' => 'ar'])
            ->assertOk()
            ->assertJsonPath('data.preferred_language', 'ar');
    }

    public function test_can_set_currency_preference(): void
    {
        [, $token] = $this->actingUser();
        $currency = Currency::create(['code' => 'SAR', 'name_en' => 'Riyal', 'name_ar' => 'ريال', 'symbol' => 'SAR', 'is_active' => true]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/v1/core/profile/currency', ['currency_id' => $currency->id])
            ->assertOk()
            ->assertJsonPath('data.preferred_currency.code', 'SAR');
    }

    public function test_avatar_upload_is_resized_and_reencoded_to_the_configured_format(): void
    {
        Storage::fake(config('core.avatar.disk'));
        [$user, $token] = $this->actingUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/core/profile/avatar', [
                'avatar' => UploadedFile::fake()->image('avatar.jpg', 2000, 2000),
            ]);

        $response->assertOk()->assertJsonPath('data.avatar_url', fn ($url) => $url !== null);

        $path = $user->profile()->first()->avatar_path;
        $this->assertNotNull($path);
        $this->assertStringEndsWith('.webp', $path);

        $disk = Storage::disk(config('core.avatar.disk'));
        $this->assertTrue($disk->exists($path));

        [$width, $height, $type] = getimagesize($disk->path($path));
        $this->assertSame((int) config('core.avatar.width'), $width);
        $this->assertSame((int) config('core.avatar.height'), $height);
        $this->assertSame(IMAGETYPE_WEBP, $type);
    }
}
