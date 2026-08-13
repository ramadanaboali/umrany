#!/bin/sh
set -e

# All four services (app, horizon, reverb, scheduler) share this same image and
# start concurrently on `docker compose up`, so migrate+seed must not run four
# times in a race. `flock` on a file inside the shared bind-mounted volume
# serializes it: whichever container starts first does the work, the others
# block until it's done, then all four proceed to their own `command:`.
#
# Both steps are safe to run on every boot: `migrate --isolated` is a no-op if
# nothing is pending, and every seeder in this project is required to be
# idempotent (updateOrCreate/firstOrCreate) — see docs/architecture/infrastructure.md.
LOCK_FILE="/var/www/html/storage/framework/.boot.lock"
mkdir -p "$(dirname "$LOCK_FILE")"

(
    flock 9
    php artisan migrate --force --isolated
    php artisan db:seed --force
) 9>"$LOCK_FILE"

exec "$@"
