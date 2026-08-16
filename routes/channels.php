<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Core\Models\Admin;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Modules\Core\Events\UserCapabilitiesChanged — private-core.user.{id}. Only the account owner
// may listen; matches the `private-<module>.<entity>.<id>` naming convention in
// docs/architecture/infrastructure.md.
Broadcast::channel('core.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Modules\Core\Events\AdminPermissionsChanged — private-core.admin.{id}. Only the admin account
// owner may listen. Resolved against the `admin` guard specifically (session-based, same as every
// other admin-dashboard request) via Laravel's per-channel `guards` option — the global
// /broadcasting/auth route runs under `web` middleware only, so without this option
// Broadcaster::retrieveUser() would resolve the default `web` guard's user instead, which is
// never an Admin. See docs/decisions/0008-admin-rbac-live-refresh-via-reverb.md.
Broadcast::channel('core.admin.{id}', function (Admin $admin, $id) {
    return (int) $admin->id === (int) $id;
}, ['guards' => ['admin']]);
