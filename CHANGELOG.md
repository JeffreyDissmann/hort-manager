# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims to
follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **"Keine Hausaufgaben" per day and per weekday**: staff can explicitly mark a day
  (or a whole weekday default) as having no homework, overriding the weekday default
  — a `homework_none` state on `DailyProgram`, with the effective slot centralised in
  `DailyProgram::effectiveHomework`. On Heute the homework window renders as a side
  bar when a pickup overlaps it, otherwise a horizontal card.
- **Abholplan „Ganze Woche" timeline**: the weekly plan now shows a full timetable of
  every child's effective pickup times for the picked week, with each day's lunch,
  activity, homework and excursions folded in — homework/excursion render as bands
  spanning their time slots, and staff can edit a pickup straight from the timeline.
- **Homework time on the daily board**: the homework window shows inline in the Heute
  pickup list (grouped by time), so pickups landing inside it stand out.
- **"Was ist neu?" in-app release notes**: a curated, parent-facing popup with
  history arrows, separate from this technical changelog.
- **Installable PWA + web push notifications**: the app can be added to the home
  screen (manifest, service worker, install banner) and parents can opt in to push
  notifications for departures, excursion RSVP reminders and new excursions — via
  VAPID (`laravel-notification-channels/webpush`), with no third-party service. See
  [`docs/deployment.md`](docs/deployment.md).
- **Self-hosted Docker deployment**: a multi-arch (amd64 + arm64) FrankenPHP image
  published to GHCR on a CalVer tag, a `docker-compose.prod.yml` stack (app, queue,
  scheduler, Cloudflare Tunnel) backed by a single SQLite volume, and a deployment
  guide ([`docs/deployment.md`](docs/deployment.md)).

### Fixed

- Wayfinder now generates **relative** route URLs, so the published image is
  domain-agnostic and no instance domain is baked into the repo or the image.

## [0.1.0] — 2026-06-28

Initial release.

### Added

- **Core daycare features**: children + Stammplan, role-based parent↔child links,
  Wochenplan/Abholplan, the daily departure board (Tagesboard), the excursion
  participation poll, the Tagesprogramm (lunch/activity/homework) and birthdays
  surfaced across the app.
- **Sign in with Slack** (OpenID Connect), restricted to the configured workspace,
  with passwordless auto-provisioning of parents; self-registration disabled.
- **Slack integration**: departure DMs to guardians, interactive excursion RSVP
  (answer Ja/Nein in Slack, kept in sync across both guardians), daily RSVP
  reminders, an App Home tab, the `/hort` slash command and one-tap deep links.
- **User & role management** (admin only): set roles, manage other admins, import
  Slack workspace members, and delete users.
- **Parents' Slack profile pictures** in the nav and guardian picker.
- **Data retention**: `hort:prune-old-data` removes day boards, programs and
  excursions older than `DATA_RETENTION_WEEKS` (default 4), silently in Slack.
- A user-facing **help page** at `/hilfe`, linked from the welcome, login and
  dashboard screens and the user menu.
- **CI pipeline** (GitHub Actions): Pint + the full test suite on every push and PR.

### Security

- SSO fails closed unless the workspace matches; accounts already linked to another
  Slack id cannot be taken over.
- `role`/`is_admin` are not mass-assignable; `auth.user` is whitelisted to the client;
  Slack `response_url` callbacks are restricted to `hooks.slack.com`.

### Changed

- `declare(strict_types=1)` enforced across the codebase (Pint rule).
- Slack sends are queued; authorization is standardized on policies.
