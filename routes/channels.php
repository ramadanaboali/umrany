<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Modules\Core\Events\UserCapabilitiesChanged — private-core.user.{id}. Only the account owner
// may listen; matches the `private-<module>.<entity>.<id>` naming convention in
// docs/architecture/infrastructure.md.
Broadcast::channel('core.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
