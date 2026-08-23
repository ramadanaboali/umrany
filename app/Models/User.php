<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Core\Enums\AccountType;
use Modules\Core\Models\Admin;
use Modules\Core\Models\Provider;
use Modules\Core\Models\UserDevice;
use Modules\Core\Models\UserMfaSetting;
use Modules\Core\Models\UserProfile;
use Modules\Core\Models\VerificationCode;

/**
 * The single end-user identity for the whole platform. Deliberately does NOT use Spatie's
 * HasRoles trait — end-user capability (Project Owner / Supplier / ERP User / Customer) is
 * computed dynamically from Provider + subscription state, never a stored role on this model.
 * See Modules\Core\Contracts\UserCapabilityResolver and docs/architecture/module-boundaries.md.
 */
#[Fillable(['name', 'email', 'mobile', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
            'account_types' => 'array',
            'password' => 'hashed',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * @return HasOne<Provider, $this>
     */
    public function provider(): HasOne
    {
        return $this->hasOne(Provider::class);
    }

    /**
     * @return HasMany<VerificationCode, $this>
     */
    public function verificationCodes(): HasMany
    {
        return $this->hasMany(VerificationCode::class);
    }

    /**
     * @return HasOne<UserMfaSetting, $this>
     */
    public function mfaSetting(): HasOne
    {
        return $this->hasOne(UserMfaSetting::class);
    }

    /**
     * @return HasMany<UserDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /**
     * The admin who suspended this account, if it's currently suspended — see
     * docs/decisions/0027-admin-user-suspend-reactivate.md.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'suspended_by_admin_id');
    }

    /**
     * FR-AUTH-002: "only verified accounts may access protected services." Registration accepts
     * either mobile or email (FR-AUTH-001), so verification of either channel satisfies this —
     * there is no requirement that both be verified.
     */
    public function hasVerifiedIdentity(): bool
    {
        return $this->email_verified_at !== null || $this->mobile_verified_at !== null;
    }

    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }

    /**
     * True only once MFA enrollment has been confirmed with a real code — an unconfirmed
     * (abandoned) enrollment must never gate login. See Modules\Core\Services\MfaService.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfaSetting !== null && $this->mfaSetting->isConfirmed();
    }

    /**
     * `account_types` is captured intent from registration only — never an authorization source.
     * See Modules\Core\Enums\AccountType.
     */
    public function hasAccountType(AccountType $type): bool
    {
        return in_array($type->value, $this->account_types ?? [], true);
    }
}
