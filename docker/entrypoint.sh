#!/bin/sh
# Container entrypoint. Runs first-boot housekeeping as root, then drops to
# www-data for the actual process. Role is selected via APP_ROLE (web|queue|scheduler).
set -e

ROLE="${APP_ROLE:-web}"
KEY_FILE=/app/storage/app/.app-key
DB_FILE="${DB_DATABASE:-/app/storage/app/database/database.sqlite}"

# Run an artisan command as the unprivileged runtime user.
artisan() { gosu www-data php artisan "$@"; }

# ── APP_KEY ──────────────────────────────────────────────────────────────────
# /app is image-resident, so .env does not survive container recreation. Persist
# the key on the *mounted* volume. NOTE: never ship an empty `APP_KEY=` line —
# Laravel's Dotenv treats OS env vars as immutable, so an empty value would
# override the generated key and 500 every request.
if [ -z "${APP_KEY:-}" ]; then
    if [ ! -f "$KEY_FILE" ]; then
        mkdir -p "$(dirname "$KEY_FILE")"
        php artisan key:generate --show > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

# ── SQLite file ──────────────────────────────────────────────────────────────
# A fresh named volume is empty; migrate errors out if the file is missing.
mkdir -p "$(dirname "$DB_FILE")"
[ -f "$DB_FILE" ] || : > "$DB_FILE"

# Volume mounts arrive root-owned; hand the writable paths to the runtime user.
chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Re-discover packages every boot: a persisted bootstrap/cache can shadow the
# build-time manifest and silently skip a newly-added provider after an upgrade.
artisan package:discover --ansi

case "$ROLE" in
    web)
        # config:clear first so newly-added env vars are seen this boot, then
        # migrate, then (re)build caches *late*.
        artisan config:clear
        artisan migrate --force
        artisan storage:link --force

        # Optional, idempotent: promote an existing user to admin. They must have
        # signed in via Slack first; never fail the boot if they don't exist yet.
        if [ -n "${ADMIN_EMAIL:-}" ]; then
            artisan hort:make-admin "$ADMIN_EMAIL" || true
        fi

        artisan config:cache
        artisan route:cache
        artisan view:cache

        exec gosu www-data "$@"
        ;;
    queue)
        artisan config:cache
        exec gosu www-data php artisan queue:work --tries=3 --sleep=3 --max-time=3600
        ;;
    scheduler)
        artisan config:cache
        exec gosu www-data php artisan schedule:work
        ;;
    *)
        # Unknown role: run whatever was passed (e.g. `docker compose run app sh`).
        exec "$@"
        ;;
esac
