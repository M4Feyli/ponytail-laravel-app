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

# "auto" sizes workers off the container's reported CPU count, which in a
# cgroup-limited free-tier instance is often the *host's* core count, not
# what's actually allotted — spawning far more Laravel-booting processes
# than the memory budget survives, which OOM-kills them before they ever
# reach frankenphp_handle_request() (no PHP error, just silent failure).
# Override with OCTANE_WORKERS if a plan has room for more.
#
# Fly.io: fixed port, matches fly.toml's internal_port (8080), no PORT env set.
# Render.com and most other Docker PaaS: PORT is injected at runtime and
# changes between deploys, so it has to be read here, not baked into CMD.
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port="${PORT:-8080}" --workers="${OCTANE_WORKERS:-2}" --max-requests=500
