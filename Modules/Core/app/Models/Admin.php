<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Enums\AdminStatus;
use Spatie\Permission\Traits\HasRoles;

/**
 * A platform operator account — deliberately distinct from `App\Models\User` (see
 * docs/modules/core.md § Admin). Authenticates via the `admin` session guard
 * (config/auth.php), never via a Sanctum API token, and is the only model in the
 * application using Spatie's HasRoles trait.
 */
#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Explicit — HasRoles otherwise falls back to config('auth.defaults.guard'), which is
     * `web` (App\Models\User's guard), not `admin`. Every role/permission created for admins
     * must be seeded with guard_name = 'admin' to match.
     */
    protected string $guard_name = 'admin';

    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAuthenticate(): bool
    {
        return $this->status->canAuthenticate();
    }
}
