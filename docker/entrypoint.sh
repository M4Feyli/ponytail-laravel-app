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

# Two ways to serve, chosen by FRANKENPHP_CLASSIC_MODE:
#
# Worker mode (default, Octane) boots Laravel once and keeps it resident in
# memory between requests — fast, but FrankenPHP's worker supervisor only
# gives the boot a handful of retries with a short exponential backoff
# before giving up permanently ("too many consecutive failures"). On
# Render's free tier, GOMAXPROCS is throttled down to 1 (confirmed via
# platform logs — "using minimum allowed GOMAXPROCS" vs. 16 locally), which
# starves that boot of enough real CPU time inside the retry budget even
# though the exact same image boots and serves fine locally with full CPU.
# We proved this by running the identical production image locally: it
# started and served correctly, so the crash is a Render-CPU-throttling
# artifact, not a bug in the image.
#
# Classic mode (frankenphp php-server) has no such gate: it boots PHP fresh
# per request, like traditional php-fpm, so there's nothing to time out
# during startup. Slower per request, but completely insensitive to this
# specific CPU-throttling failure. Set FRANKENPHP_CLASSIC_MODE=true (done
# in render.yaml) to use it. --root public/ replicates Octane/Laravel's own
# documented Caddyfile block ("root public/; php_server") — static assets
# are served directly, everything else falls through to public/index.php.
if [ "$FRANKENPHP_CLASSIC_MODE" = "true" ]; then
    echo "entrypoint: FRANKENPHP_CLASSIC_MODE=true -- serving via 'frankenphp php-server' (no Octane worker)" >&2
    id >&2 || true
    frankenphp version >&2 || true
    exec frankenphp php-server --listen "0.0.0.0:${PORT:-8080}" --root public/
fi

# ---- diagnostics: only relevant to the worker-mode path below, which has
# been failing to start with zero PHP output through several fixes. Render
# has no free shell access, so print what we'd otherwise check by hand —
# this runs whether or not the worker ends up booting, and costs nothing.
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
