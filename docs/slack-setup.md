# Slack integration — production setup

Everything you need to wire the Hort's Slack workspace to the app in production.
The app talks to Slack in three directions:

- **Sign in with Slack** (OIDC) — how parents/staff log in.
- **Outbound DMs** — departure notices, excursion RSVP messages, reminders, App Home (bot token).
- **Inbound callbacks** — interactive buttons, the `/hort` command, and Home-tab events (signature-verified).

All callback URLs below must be **public HTTPS** on your real domain. Locally we use
`./vendor/bin/sail share` (Expose) and its temporary URL; in production use `APP_URL`.

> Set **`APP_URL`** to the production `https://…` domain. Links the app puts inside
> Slack messages are generated from it (`URL::forceRootUrl`), so a wrong `APP_URL`
> sends users to the wrong place.

## 1. Create the app
<https://api.slack.com/apps> → **Create New App** → *From scratch* → pick the Hort workspace.

## 2. Basic Information
- **App icon**: upload `slack-app-icon.png` (1024×1024).
- **Background color**: `#223E55`.
- Copy the **Signing Secret** → `SLACK_SIGNING_SECRET`.

## 3. Sign in with Slack (OIDC login)
- **OAuth & Permissions → Redirect URLs**: add `https://YOUR_DOMAIN/auth/slack/callback` → Save.
- The OIDC user scopes (`openid`, `email`, `profile`) are requested by the app at runtime — nothing to set here.
- Copy **Client ID** / **Client Secret** (Basic Information) → `SLACK_CLIENT_ID` / `SLACK_CLIENT_SECRET`.

## 4. Bot token (outbound messages)
- **OAuth & Permissions → Bot Token Scopes**: add
  - `chat:write` — send & update DMs (departures, RSVP, reminders, cancellations)
  - `commands` — the `/hort` slash command
  - `users:read` + `users:read.email` — import workspace members (Benutzer → „Aus Slack importieren", or `php artisan hort:sync-slack-users`)
- **Install to Workspace** → copy the **Bot User OAuth Token** (`xoxb-…`) → `SLACK_BOT_USER_OAUTH_TOKEN`.
- Reinstall whenever you change scopes.

## 5. Interactivity (RSVP buttons)
**Interactivity & Shortcuts** → On → **Request URL**: `https://YOUR_DOMAIN/slack/interactions` → Save.

## 6. Slash command `/hort`
**Slash Commands** → **Create New Command**:
- Command: `/hort`
- Request URL: `https://YOUR_DOMAIN/slack/commands`
- Description: „Hort-Manager öffnen" → Save → reinstall if prompted.

## 7. App Home (sidebar entry)
- **App Home** → enable **Home Tab**.
- **Event Subscriptions** → On → **Request URL**: `https://YOUR_DOMAIN/slack/events`
  (Slack sends a one-time verification challenge — the endpoint answers it automatically.)
- **Subscribe to bot events**: add `app_home_opened` → Save → reinstall if prompted.

## 8. Restrict to the Hort workspace
- `SLACK_TEAM_ID` — the workspace/team id (`T…`); the SSO callback rejects logins from any other workspace.
- `SLACK_WORKSPACE` — the workspace subdomain (e.g. `hort-manager`), so the sign-in screen skips the "which workspace" picker.

## 9. Scheduler + queue worker
All Slack DMs (departures, RSVP announce/sync, reminders, App Home) are **queued**,
and `QUEUE_CONNECTION=database` in production — so a worker must be running, or
nothing gets sent:

```
php artisan queue:work        # keep alive via supervisor / Horizon
```

The daily reminder (`excursions:remind-rsvps`, 08:00) needs the scheduler. Add one cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

> Excursion **cancellation** DMs and the **Aus Slack importieren** action run
> synchronously (the former must read the tracked messages before they cascade
> away on delete; the latter returns an immediate count) — no worker needed for those.

## Environment variables

| Variable | Where it comes from |
|---|---|
| `APP_URL` | Production `https://…` domain |
| `SLACK_CLIENT_ID` | Basic Information |
| `SLACK_CLIENT_SECRET` | Basic Information |
| `SLACK_REDIRECT_URI` | `${APP_URL}/auth/slack/callback` (must match step 3) |
| `SLACK_SIGNING_SECRET` | Basic Information → Signing Secret |
| `SLACK_BOT_USER_OAUTH_TOKEN` | OAuth & Permissions → Bot token (`xoxb-…`) |
| `SLACK_TEAM_ID` | Workspace/team id (`T…`) |
| `SLACK_WORKSPACE` | Workspace subdomain |

## Callback URLs (all on `APP_URL`)

| Purpose | Slack setting | Path |
|---|---|---|
| SSO login | OAuth & Permissions → Redirect URLs | `/auth/slack/callback` |
| RSVP buttons | Interactivity & Shortcuts | `/slack/interactions` |
| `/hort` command | Slash Commands | `/slack/commands` |
| Home tab events | Event Subscriptions | `/slack/events` |

> If a callback ever fails with a signature error, the **Signing Secret** in `.env`
> is stale — recopy it from Basic Information. If buttons link to the wrong host,
> check `APP_URL`.
