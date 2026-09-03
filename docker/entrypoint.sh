#!/bin/sh
# Runs once per container start, before Octane takes over as PID 1's child.
# Migrations run here (not just in fly.toml's release_command) so a plain
# `docker run` of this image is also self-sufficient.
set -e

if [ -z "$APP_KEY" ]; then
    echo "entrypoint: APP_KEY is not set — generating one for this run only." >&2
    echo "entrypoint: set a permanent one with 'fly secrets set APP_KEY=...'" >&2
    php artisan key:generate --force
fi

# The Fly volume mounts over storage/app, which hides the "public" subdir
# baked into the image at build time — recreate it and the symlink here so
# they survive on a fresh volume too. Cheap and idempotent either way.
mkdir -p storage/app/public
php artisan storage:link --force >/dev/null 2>&1 || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
fi

# ---- diagnostics: the worker has been failing to start with zero PHP
# output through several fixes. Render has no free shell access, so print
# what we'd otherwise check by hand — this all runs whether or not Octane
# ends up working, and costs nothing.
echo "=== DIAGNOSTICS ===" >&2
echo "--- id ---" >&2
id >&2 || true
echo "--- php -v ---" >&2
php -v >&2 || true
echo "--- frankenphp version ---" >&2
frankenphp version >&2 || true
echo "--- worker file present? ---" >&2
ls -la public/frankenphp-worker.php >&2 || true
echo "--- php -l (syntax check) on the worker file ---" >&2
php -l public/frankenphp-worker.php >&2 || true
echo "--- running the worker file directly under plain php CLI ---" >&2
echo "    (expected: prints 'FrankenPHP must be in worker mode...' and exits 1 --" >&2
echo "    anything else, e.g. a segfault or a different fatal, is the real clue)" >&2
php public/frankenphp-worker.php >&2 2>&1 || true
echo "=== END DIAGNOSTICS ===" >&2

# "auto" sizes workers off the container's reported CPU count, which in a
# cgroup-limited free-tier instance is often the *host's* core count, not
# what's actually allotted — spawning far more Laravel-booting processes
# than the memory budget survives, which OOM-kills them before they ever
# reach frankenphp_handle_request() (no PHP error, just silent failure).
# Override with OCTANE_WORKERS if a plan has room for more.
#
# octane:frankenphp (not octane:start --server=frankenphp) — Laravel's own
# docs and multiple real deployments use this form; octane:start's generic
# multi-server path wraps FrankenPHP in a way that's been reported to not
# always set FRANKENPHP_WORKER correctly, which is exactly our symptom
# ("worker ... has not reached frankenphp_handle_request()").
#
# Fly.io: fixed port, matches fly.toml's internal_port (8080), no PORT env set.
# Render.com and most other Docker PaaS: PORT is injected at runtime and
# changes between deploys, so it has to be read here, not baked into CMD.
exec php artisan octane:frankenphp --host=0.0.0.0 --port="${PORT:-8080}" --workers="${OCTANE_WORKERS:-2}" --max-requests=500 --log-level=debug
