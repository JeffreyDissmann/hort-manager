# Deployment — self-hosted NAS via Docker

The app ships as a multi-arch image (amd64 + arm64) on GHCR, built and pushed by
the release pipeline on a CalVer tag. It runs as a small `docker compose` stack
behind a Cloudflare Tunnel (TLS at the edge). **SQLite is the only datastore.**

- Image: `ghcr.io/jeffreydissmann/hort-manager` (`:latest` or a `:YYYY.MM.DD` tag)
- Compose: [`docker-compose.prod.yml`](../docker-compose.prod.yml)
- Env template: [`.env.docker.example`](../.env.docker.example)

## 0. Make the image pullable

GHCR packages are private by default. To pull on the NAS without logging in:

- GitHub → your avatar → **Packages** → `hort-manager` → **Package settings** →
  **Change visibility** → **Public**.
- *Or* keep it private and run `docker login ghcr.io -u <user>` on the NAS with a
  Personal Access Token that has the `read:packages` scope.

## 1. Cloudflare Tunnel (TLS termination)

1. Cloudflare **Zero Trust → Networks → Tunnels → Create a tunnel** (type *Cloudflared*).
2. Name it and save, then **copy the tunnel token** (the long string after `--token`
   in the shown install command). It goes into `.env` as `CLOUDFLARE_TUNNEL_TOKEN`.
3. **Public Hostname → Add a public hostname**:
   - Subdomain/domain: e.g. `hort.example.com`
   - Service **Type**: `HTTP`, **URL**: `app:8080`
     (`app` is the compose service name; the `cloudflared` container reaches it on
     the internal Docker network — no ports are published on the host.)

## 2. Configure the environment

You only need **two files** on the NAS — the app image is pulled from GHCR, so no
`git clone` is required. Grab them into a folder (e.g. `~/hort-manager`):

```bash
mkdir -p ~/hort-manager && cd ~/hort-manager
curl -fLO https://raw.githubusercontent.com/JeffreyDissmann/hort-manager/main/docker-compose.prod.yml
curl -fLO https://raw.githubusercontent.com/JeffreyDissmann/hort-manager/main/.env.docker.example
cp .env.docker.example .env
```

(No SSH? Download those two raw files via the NAS file manager and rename the copy
to `.env`. On Synology you can also use **Container Manager → Project → Create**
pointed at this folder. Keep `.env` on the NAS only — it holds the tunnel token.)

Edit `.env`:

- `APP_URL=https://hort.example.com` — your Cloudflare hostname (links inside Slack are built from this).
- `CLOUDFLARE_TUNNEL_TOKEN=…`
- `SLACK_*` — see **§4** and [`slack-setup.md`](slack-setup.md).
- *(optional)* `ADMIN_EMAIL=you@example.com`.
- Leave **`APP_KEY` commented out** — the container generates one and persists it
  on the volume. (Never leave an empty `APP_KEY=` line — it 500s every request.)

## 3. Run

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml ps          # app healthy; queue/scheduler/cloudflared up
docker compose -f docker-compose.prod.yml logs -f app # watch the first-boot migrate
```

On first boot the `app` container generates the key, creates the SQLite file,
migrates, and builds caches. Once `app` is healthy the tunnel serves your domain.

## 4. Connect Slack

Follow [`slack-setup.md`](slack-setup.md) — same steps, but every callback URL uses
your **production domain** (`APP_URL`), not a tunnel preview URL:

| Purpose | Slack setting | URL |
|---|---|---|
| SSO login | OAuth & Permissions → Redirect URLs | `https://hort.example.com/auth/slack/callback` |
| RSVP buttons | Interactivity & Shortcuts | `…/slack/interactions` |
| `/hort` command | Slash Commands | `…/slack/commands` |
| App Home | Event Subscriptions | `…/slack/events` |

Bot scopes: `chat:write`, `commands`, `users:read`, `users:read.email`. Put the
bot token, signing secret, client id/secret, `SLACK_TEAM_ID` and `SLACK_WORKSPACE`
into `.env`, then `docker compose -f docker-compose.prod.yml up -d` to reload.

## 5. First admin

Sign in once via Slack (creates your parent account), then promote yourself:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan hort:make-admin you@example.com
```

(Or set `ADMIN_EMAIL` in `.env` — the entrypoint promotes that user on each boot,
once they've signed in at least once.)

## Upgrades

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

A new `:latest` rolls forward; the entrypoint migrates and rebuilds caches on boot.
Pin a specific build by setting `APP_IMAGE=ghcr.io/jeffreydissmann/hort-manager:2026.06.28`.

## Backup & restore — the entire DR story

All data is the one SQLite file in the named volume (compose prefixes it with the
project name; find it via `docker volume ls | grep hort`).

```bash
# Back up the volume (offline-consistent enough for SQLite with WAL):
docker run --rm -v <project>_hort_storage:/d -v "$PWD":/b alpine \
  tar czf /b/hort-backup-$(date +%F).tgz -C /d .

# Or a live, consistent SQLite snapshot:
docker compose -f docker-compose.prod.yml exec app \
  sqlite3 /app/storage/app/database/database.sqlite ".backup '/app/storage/app/backup.sqlite'"
```

Restore: stop the stack, replace the volume contents from the archive, start again.

## One-off commands

```bash
docker compose -f docker-compose.prod.yml exec app php artisan <command>
docker compose -f docker-compose.prod.yml exec app php artisan tinker
```

## What runs (4 services, one image)

| Service | Role | Notes |
|---|---|---|
| `app` | web (FrankenPHP) | serves HTTP :8080, runs migrations on boot |
| `queue` | `queue:work` | sends the Slack DMs/RSVP/Home off-request |
| `scheduler` | `schedule:work` | daily RSVP reminders + nightly data prune |
| `cloudflared` | tunnel | terminates TLS, forwards to `app:8080` |

## Releasing a new image

Pushing a CalVer tag (`git tag YYYY.MM.DD && git push origin YYYY.MM.DD`) runs the
release pipeline → multi-arch image on GHCR (`:tag` + `:latest`).

> **The image is built for one domain.** Wayfinder bakes absolute in-app link URLs
> from `APP_URL` at build time (like Ziggy), so the build reads the **`APP_URL`
> repository variable** (`gh variable set APP_URL --body https://your-domain`). If
> the domain ever changes, update that variable and cut a new tag — otherwise every
> in-app link points at the old host.

