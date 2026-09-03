# Laravel + Ponytail + Docker + Fly.io / Render

A fresh Laravel 13 project, wired for:

- **[Ponytail](https://ponytail.dev/)** — an AI-agent coding ruleset ("write the least code that works"). `AGENTS.md` at the project root carries the full instruction set, so Claude Code (or any AGENTS.md-aware agent) picks it up automatically in this repo. No plugin install needed for this to work — `AGENTS.md` is the "works everywhere" delivery method Ponytail itself documents. If you also want the Claude Code slash commands (`/ponytail`, `/ponytail-review`, `/ponytail-audit`, `/ponytail-debt`, `/ponytail-gain`), run inside Claude Code:
  ```
  /plugin marketplace add DietrichGebert/ponytail
  /plugin install ponytail@ponytail
  ```
- **Laravel 13**, PHP 8.4.
- **Docker** for local development (`docker-compose.yml`: app + MySQL + Redis).
- **Laravel Octane on FrankenPHP** as the production app server — one container, no nginx/php-fpm/supervisor stack.
- **Fly.io** deploy config (`fly.toml` + `docker/Dockerfile`) — requires a card/credit on file.
- **Render.com** deploy config (`render.yaml`) — genuinely free tier, no card required. Use this one if you don't want to add billing info anywhere.

Both deploy targets share the same `docker/Dockerfile`; `docker/entrypoint.sh` reads the `PORT` env var at runtime (falling back to 8080) so the same image works on either platform without changes.

## Important: run `composer install` before anything else

This project was scaffolded in a sandboxed environment with no access to Packagist/npm, so every file here was written by hand to match Laravel's official `13.x` skeleton — but `vendor/` was never generated and never committed (as normal for a Laravel repo). Before you do anything else:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Then sanity-check it boots: `php artisan about`.

## Local development

Everything runs in Docker — you don't need PHP/Composer/Node on your host at all (the one-time `composer install` above still needs a local PHP+Composer, or run it via the dev container instead — see below).

```bash
docker compose up -d          # app (PHP built-in server) + mysql + redis
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run dev   # Vite dev server, in a second terminal
```

App: http://localhost:8000
Vite: http://localhost:5173

Config knobs (`.env` / `docker-compose.yml`): `APP_PORT` (default 8000), `VITE_PORT` (default 5173), `DB_*`, `REDIS_*`.

To try the production server (Octane/FrankenPHP) locally instead:

```bash
docker build -f docker/Dockerfile -t ponytail-laravel-prod .
docker run --rm -p 8080:8080 --env-file .env ponytail-laravel-prod
```

## Deploying to Fly.io

1. **Install flyctl** and log in: https://fly.io/docs/flyctl/install/, then `fly auth login`.
2. **Pick a unique app name** — edit `app = "ponytail-laravel-app"` in `fly.toml` (Fly app names are global).
3. **Create the app** (config already exists, so skip the interactive scaffold):
   ```bash
   fly apps create <your-app-name>
   ```
4. **Database** — this repo defaults to Postgres (`DB_CONNECTION=pgsql` in `fly.toml`):
   ```bash
   fly postgres create --name <your-app-name>-db
   fly postgres attach <your-app-name>-db -a <your-app-name>
   ```
   `fly postgres attach` sets the `DATABASE_URL` secret for you; Laravel doesn't read that directly, so either set `DB_URL` to the same value or set the individual `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` secrets from the values `fly postgres attach` prints.
5. **App key and other secrets:**
   ```bash
   fly secrets set APP_KEY=$(php artisan --no-ansi key:generate --show)
   ```
6. **Storage volume** (already declared in `fly.toml`, create it once per region):
   ```bash
   fly volumes create ponytail_storage --region arn --size 1
   ```
7. **Deploy:**
   ```bash
   fly deploy
   ```
   This builds `docker/Dockerfile` (either locally or on Fly's remote builder — no local Docker daemon required), runs `php artisan migrate --force` as the `release_command` before the new machines take traffic, then starts Octane/FrankenPHP on port 8080.
8. **Redis** (optional, only if you want `CACHE_STORE=redis`/`SESSION_DRIVER=redis` instead of the database-backed defaults):
   ```bash
   fly redis create
   ```
   then set `REDIS_URL` from its output and flip the relevant `.env`/`fly.toml` values.

Health check: Laravel's built-in `/up` route (see `bootstrap/app.php`), already wired into `fly.toml`.

## Deploying to Render.com (free, no card)

1. **Push this repo to GitHub** (Render deploys Blueprints from a Git repo, not a local folder):
   ```bash
   git remote add origin https://github.com/<you>/ponytail-laravel-app.git
   git push -u origin main
   ```
2. **Sign up at https://render.com** — no payment method needed for the free tier.
3. In the dashboard: **New → Blueprint**, pick the GitHub repo. Render reads `render.yaml` and proposes a web service (`ponytail-laravel-app`, Docker, free) plus a Postgres database (`ponytail-laravel-db`, free) together.
4. Click **Apply**. Render builds `docker/Dockerfile`, wires the database env vars in automatically (see `render.yaml`'s `fromDatabase` entries), generates `APP_KEY`, and deploys.
5. First request after a build takes a minute (image boot); after that it responds normally until it's had 15 minutes with no traffic, at which point it sleeps and the next request takes 30-50s to wake it back up. That's the free tier's trade-off — fine for trying this out, not for production traffic.

Free-tier limits worth knowing: 750 instance-hours/month for the web service, and the free Postgres database expires 30 days after creation (14-day grace period to upgrade it before it's deleted). Move to a paid Render plan, or back to Fly.io once you've added billing there, when you outgrow that.

## Project layout notes

- `docker/Dockerfile` — production image (multi-stage: composer → node build → FrankenPHP runtime). Shared by Fly and Render.
- `docker/entrypoint.sh` — runs on every container boot: caches config/routes/views, re-links storage, starts Octane on `$PORT` (falls back to 8080), and (if `RUN_MIGRATIONS=true`) migrates. On Fly, migrations normally go through `fly.toml`'s `release_command` instead (once per deploy); Render's free tier has no equivalent hook, so `render.yaml` sets `RUN_MIGRATIONS=true` and it runs on every boot — harmless, since `migrate` is a no-op once there's nothing new to run.
- `docker-compose.yml` + `docker/dev/Dockerfile` — local dev only, not used in production.
- `fly.toml` — Fly.io deploy config (needs a card on file).
- `render.yaml` — Render.com Blueprint (free, no card).
- `config/octane.php` — Octane config; `OCTANE_SERVER=frankenphp` is set by default everywhere.
- `AGENTS.md` — the Ponytail ruleset, read automatically by AGENTS.md-aware coding agents.
