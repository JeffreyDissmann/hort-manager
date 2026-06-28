# Hort-Manager

A management app for a German after-school daycare (*Hort*). It is two-sided —
**Erzieher:innen** (staff) and **Eltern** (parents) — and exists to answer one
question reliably: **when does each child leave each day, and how?**

The UI is in German; code, identifiers and docs are in English.

## Features

- **Tagesboard (Heute)** — the day's departure overview; staff tick each child off.
- **Abholplan / Wochenplan** — the weekly pickup plan per child (Stammplan), adjustable per day.
- **Ausflüge** — excursions with a per-child parent RSVP poll.
- **Tagesprogramm** — lunch, activity and homework times for the week.
- **Sign in with Slack (OIDC)** — passwordless login, restricted to the Hort's workspace.
- **Slack integration** — departure DMs, interactive excursion RSVP (answer in Slack),
  daily reminders, an App Home tab and a `/hort` command. See [`docs/slack-setup.md`](docs/slack-setup.md).
- **User & role management** — admins set roles, manage admins and import workspace members.
- **Data retention** — old day boards, programs and excursions are pruned automatically.
- A user-facing **help page** at `/hilfe`.

## Tech stack

Laravel 13 · PHP 8.4 · Inertia 2 + Vue 3 · Tailwind · SQLite · Laravel Sail (Docker).

## Getting started

Everything runs in Docker via [Laravel Sail](https://laravel.com/docs/sail) — do not run
`php`/`composer`/`npm` on the host (wrong runtimes).

```bash
git clone git@github.com:JeffreyDissmann/hort-manager.git
cd hort-manager
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```

App: <http://localhost>. Demo logins: `erzieher@hort.test` / `eltern@hort.test` (both `password`).

| Task | Command |
|------|---------|
| Dev server (HMR) | `./vendor/bin/sail npm run dev` |
| Run tests | `./vendor/bin/sail artisan test` |
| Format (PHP) | `./vendor/bin/sail pint` |

## Configuration

Key environment variables (see `.env.example` for the full list):

- `SLACK_*` — Sign-in-with-Slack, the bot token, signing secret and workspace (`docs/slack-setup.md`).
- `DATA_RETENTION_WEEKS` — how long to keep operational data (default `4`).

## Deployment

Self-hosted on a NAS via Docker (multi-arch image on GHCR, Cloudflare Tunnel, SQLite).
Full guide: **[`docs/deployment.md`](docs/deployment.md)**. A CalVer tag (`YYYY.MM.DD`)
triggers the release pipeline that publishes `ghcr.io/jeffreydissmann/hort-manager`.

```bash
cp .env.docker.example .env   # edit APP_URL, SLACK_*, CLOUDFLARE_TUNNEL_TOKEN
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

## Production notes

- All Slack sends are **queued** — run a worker (`php artisan queue:work`, e.g. via supervisor/Horizon).
- The daily RSVP reminders and the nightly data cleanup need the scheduler — add the cron:
  `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- Set `APP_URL` to the real HTTPS domain (links inside Slack messages are built from it).

## Contributing

CI (GitHub Actions) runs Pint and the full test suite on every push and pull request;
changes must be green before merging. See [`CHANGELOG.md`](CHANGELOG.md) for history.
