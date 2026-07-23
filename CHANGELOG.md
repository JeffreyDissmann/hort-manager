# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims to
follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Buchhaltung (accounting) — admin-only bookkeeping**: a full income/expense ledger
  for the Hort. Bank statements are imported from CSV — now with a **flexible
  column-mapping step**: after upload the columns (Buchungsdatum, Wertstellung,
  Verwendungszweck, Betrag, Währung) are auto-detected from the header and shown for
  confirmation or adjustment, with a live preview, before any drafts are created;
  encoding (UTF-16/UTF-8), delimiter, date format and amount style are detected
  automatically, so it's no longer tied to one bank's layout. Duplicate rows are skipped
  and surfaced for one-tap confirmation. A local **AI model** (Ollama via `laravel/ai`)
  suggests a category and counterparty per row, which staff review and confirm. Bookings
  carry a category (a nestable income/expense tree), a counterparty (child / user / free
  text), and transfers between accounts are booked as a linked pair. Two evaluations:
  the **Auswertung** (month × category pivot of the confirmed ledger) and **Einnahmen je
  Kind** (a child × month contributions matrix per income stream, flagging months a child
  hasn't paid and contributions not linked to a child). Both, and the filtered bookings
  list, **export to CSV and Excel (XLSX)**.
- **Child enrolment period (Aktivitätszeitraum)**: each child now has an `active_from`
  (start) and optional `active_until` (leave date; empty = still enrolled), so a child who
  leaves the Hort disappears from the day-to-day views without erasing their history.
  Filtering is always date-relative: the Hort side (board, Wochenplan, Stammplan,
  birthdays, excursion invites…) shows a child only on dates they're enrolled; the
  accounting side scopes to the selected year (the contributions matrix, the booking
  child pickers and the AI suggester only offer children enrolled that year, and payments
  attributed to a child not enrolled that year are surfaced separately so totals still
  reconcile). The Kinder page lists active children by default with an „Ehemalige" toggle
  for leavers; creating a child asks for the start date.
- **Notification settings + weekly digest**: a „Benachrichtigungen" page (avatar menu)
  with a per-category × per-channel opt-out matrix — each notification type (departures,
  excursions, companion, missing plan, weekly digest) toggles independently for Slack
  and Push. Stored as a default-on
  `notification_preferences` JSON column and enforced in both send paths (the
  `SlackNotification::via()` classes and the direct `SlackRsvp`/`SlackCompanion`
  services). The Slack column disables (and shows off) without a linked Slack account,
  and the device-push master now lives here instead of on the profile page. Plus a new
  Monday 12:00 **weekly digest** DM (`weekly:digest`, scheduled): the week's Hort-wide
  program (food, activities, homework) and each parent's per-child summary — pickup per
  day, absences and excursions — on Slack and/or push, gated by the digest preference.

- **Any-day Heute board** — 💛 **thanks to Julia**, whose idea this was: the daily board
  is now navigable to any
  weekday via `?date=` — a `DayNav` with prev/next arrows and a jump-to-any-day date
  picker. Today stays live (staff marking); future weekdays are an editable preview
  computed read-only from the Stammplan (`EffectivePlan`, no `DailyDeparture` rows
  persisted on view); past days are read-only history, and the same-day `board.mark` /
  `board.override` endpoints now 403 off-today. Past navigation is capped at the
  data-retention floor. The Wochenplan's weekday headers link straight to that day's
  board. Backed by a new reusable `DatePicker.vue` (hand-rolled month calendar with
  month/year dropdowns, min/max, weekend-disabling, clearable, Escape/backdrop-to-close)
  that also replaces the native date inputs on the child and excursion forms.
- **Activity log (Protokoll)**: an admin-only audit trail at `/protokoll` (linked in the
  avatar menu) recording who changed what — children, Stammplan, absences, excursions,
  the Tagesprogramm and users/roles are auto-logged via `spatie/laravel-activitylog`,
  plus explicit entries for departures marked, day-plan adjust/reset (with the before/
  after: Uhrzeit, „geht allein" vs „geht mit … mit", Begleitkind), guardian links and
  excursion RSVPs. Fully de/en — each row is composed in the frontend from structured
  data (event + subject + neutral label), so nouns, field names and enum values follow
  the language toggle. Sign-ins are deliberately not logged (noise).
- **One shared edit popup everywhere** (`DayEditor.vue`): editing a child on a day is
  now the exact same dialog on the Heute board and the Wochenplan — Krank/„Kommt nicht",
  Uhrzeit, Art (incl. „geht mit einem anderen Kind mit"), bis/genau um/ab, Kommentar,
  „Auf Standard". The board's old inline editor is gone; the board also gained the
  companion picker and the Krank/„Kommt nicht" flow it lacked.
- **Clickable „Hortfrei" pills**: the names of children who aren't at the Hort — on the
  Heute board and in the Wochenplan „Diese Woche nicht da" summary — are now buttons that
  open the day editor to add a one-off pickup (own children for parents, all for staff).
- **Admin role switch from the menu**: an admin can flip their own role between Erzieher
  and Elternteil straight from the avatar menu — a real, persisted change that stays on
  the current page.
- **Explicit „Hortfrei" in the Stammplan**: each weekday is now one clear choice —
  Hortfrei / Wird abgeholt / Geht allein — instead of leaving a blank time. „Hortfrei"
  (regularly no Hort that day) is deliberately distinct from a reported per-day absence
  („Kommt nicht"/„Krank", which carries a reason), and it's now surfaced everywhere it
  used to be invisible: a muted „Heute hortfrei (Stammplan)" line on the board (in one
  block with the reported absences), clearer „Hortfrei" cells on the Wochenplan, and a
  per-weekday list in the „Diese Woche nicht da" summary. The „geht mit einem anderen
  Kind mit" option is gone from the Stammplan (a per-day Wochenplan choice, rejected
  server-side there too).
- **„Geht allein" time qualifier on the Stammplan and the Heute board**: a „geht
  allein" time can say it means *bis* / *genau um* / *ab* — previously only on the
  Wochenplan, now also in the Stammplan editor and the board's same-day override, and
  seeded onto the board from the Stammplan. The Wochenplan grid, „Ganze Woche"
  timeline and Stammplan page show the bis/ab prefix on standard days too.
- **„Wochenplan fehlt"-Erinnerung**: parents whose child has no Stammplan yet see a
  warning bar linking straight to that child's schedule, and a new
  `wochenplan:remind-unset` command DMs those guardians (Slack and/or push) — with a
  `--dry-run` that shows who would be nudged without sending.
- **Linked people are visible in the admin lists**: the Benutzer page shows each
  user's children, and the Kinder page shows each child's guardians (chips under
  the name).
- **Always-fresh installed app**: a drag-down **pull-to-refresh** on Heute and the
  plans (the spinner trails the pull at half speed and spins while loading); a
  **silent reload** onto the newest version after a deploy; and a **stale-content
  refresh** that re-fetches the current page when the app is reopened after 15+
  minutes in the background — each guarded so it never interrupts an active edit.
- **Whole invited group on the staff excursion cards**: each Ausflug card now lists
  every invited child with their status, always expanded (parents keep the
  collapsible „Alle Kinder" list).
- **„Geht mit einem anderen Kind mit"**: a child can be set (per day, on the
  Wochenplan) to go home with another child; the pickup time mirrors that child's
  and follows it live. When the companion goes home alone, their family must confirm
  (an interactive Ja/Nein — web-push + an in-app card, plus a Slack DM that stays in
  sync across all guardians); when the companion is picked up by their own parents,
  it's approved automatically. Until confirmed, staff/board see a normal pickup at
  the synced time. The requesting family is told the outcome (Slack + push), and if
  the companion is later reported away the arrangement is unwound with a heads-up to
  re-plan. Guards against chains, self- and mutual arrangements. Idea by Andrea,
  Ezgi and Vio.
- **„Geht allein" time qualifier**: a „geht allein" pickup can say the time means
  *bis* / *genau um* / *ab* — shown as „bis 15:30" / „ab 15:30" on the plans + board.
- **Parent pickup-arrangement overview**: the Wochenplan and Heute show parents a
  „Mit anderen nach Hause" panel — their child tagging along (with the confirmation
  status), or a child coming home with theirs (confirm inline).
- **Absence needs a reason**: reporting a child krank/„kommt nicht" in the Wochenplan
  editor now stages it and requires a short comment before saving; the reason is
  stored and shown. Absent children drop off the whole-week grid and appear in a
  per-day „nicht da" summary below it.
- **Dark mode** with a per-device Light/Dark/Automatic switch under „Profil →
  Darstellung" (Automatic follows the OS `prefers-color-scheme`). The scheme is
  applied before first paint by a tiny inline script (no flash) and stored in
  `localStorage`. Backed by a proper theme-token layer: the **entire palette is
  driven by CSS variables** (`resources/css/app.css`), so retheming or fixing a
  contrast issue is a one-line change and never touches component markup. Semantic
  neutrals swap between schemes — `canvas` (page), `surface` (cards), `ink` (text /
  borders) — while brand hues are tunable per theme (teal lifted slightly in dark
  for legibility; `hort-navy` stays dark as it is both the app chrome and the text
  colour on solid accent surfaces). Convention: text on a neutral surface uses
  `ink`, text on a brand background uses `hort-navy`/`white`. Idea by Stepan.
- **TRMNL staff-room dashboard**: a read-only JSON feed (`GET /trmnl/dashboard`,
  authenticated by a Laravel signed URL) that drives an e-ink display of today's
  pickup timeline and the Mo–Fr week — focused on who leaves when, plus present
  count, absences and the day's program. `HortDashboardData` derives everything
  from the Stammplan + same-day overrides without seeding rows; `hort:trmnl-url`
  prints the link to paste into TRMNL. Templates + setup in [`docs/trmnl/`](docs/trmnl/README.md).
- **See the whole group going on an Ausflug**: on the Ausflüge page a collapsible
  „Alle Kinder anzeigen" link under the parent's own Abstimmung lists every invited
  child and their status (open-information policy); the Heute board shows who's on
  today's trip the same way. Adds a shared `CollapsibleChips` disclosure and a
  `ChildStatusBadge` chip.
- **German/English UI with an in-app language switch**: German stays the default;
  each user can switch to English under Profil. Built on standard Laravel lang files
  (`lang/de|en/*.php`) that drive both server output (`__()`, incl. enum labels and
  flash messages) and the Vue UI via an Inertia-shared catalog + a small `$t()`
  helper — no `vue-i18n` dependency. A `users.locale` column + `HasLocalePreference`
  + a `SetLocale` middleware carry the choice through to notifications and mail.
- **AI assistant in Slack (DM chat + `/hort`)**: parents can write to the bot in
  plain German — report a child sick/absent, change a pickup time or method, answer
  an excursion RSVP, or ask about the day's plan. A local Ollama model (via the
  `laravel/ai` SDK) classifies the intent and extracts parameters; date resolution
  and every mutation happen server-side, scoped strictly to the parent's own
  children. Needs the `message.im` event + `im:history` scope — see
  [`docs/slack-setup.md`](docs/slack-setup.md).
- **Krankmeldung / Abwesenheit**: parents and staff can report a child sick or absent
  (from today on) on Heute and the Wochenplan editor, or via the assistant; a pending
  departure for that day is removed, and the entry is pruned with the other old data.
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

### Changed

- **A pickup now needs a method and a time.** The „— offen —" placeholder can't be
  saved: the day editor disables „Speichern" until a real method and time are chosen
  („geht mit … mit" mirrors the other child's time), and the server (`AdjustDayRequest`)
  enforces the same. The placeholder now reads „— bitte wählen —" to match the Stammplan.
- **Excursion group list is ordered by status** — joining first, then still-undecided,
  then not coming (alphabetical within each) — on both the parent poll and the staff
  cards.
- **The Heute board's absence report asks for a reason and is undoable.** Tapping
  „Krank"/„Kommt nicht" now opens a required-reason field and only fires on confirm
  (a mis-tap no longer silently unwinds a „geht mit … mit" arrangement), and the
  „Heute abwesend" strip gains an „aufheben" undo.
- **Clearer companion picker**: children who can't be a companion that day are shown
  disabled with a reason, a hint explains the mirrored time and that the other family
  must still confirm, and a declined arrangement now reads red (not the same orange as
  pending) on the grid.
- **Fewer queries on the board and weekly views**: pickup-time resolution is batched
  (`EffectivePlan::forMany`), removing per-row/per-cell N+1s; the confirm-companion
  side effects are consolidated in one place used by both the app and Slack.
- **Time pickers start at 11:00** (the Hort opens around 11:30) — earlier options
  were dropped.
- **„Abwesend" renamed to „Kommt nicht"** across the plans and board.
- The whole-week grid now has faint horizontal lines between the time slots
  (matching the Stammplan), and the „Heute" column is tinted its full height.
- **Clearer departure-method distinction on the plans and board.** Per staff
  feedback, the two methods were too close at a glance and the child's name shared
  the method colour. Now the name/time is always solid `ink`, and the method reads
  from a warm/cool split — picked up = teal, goes home alone = a new warm
  `hort-orange` (moved off purple, which the app already uses for excursions /
  activities) — and the "goes home alone" case additionally carries a 🚶 icon so
  the safety-relevant exception stands out. Adds `hort-orange`/`hort-orange-dark`
  theme tokens.
- **Wochenplan and Stammplan are now two separate pages.** The navigable week view
  (`/wochenplan`) shows only the per-week plan, with a strong current-week cue (a
  coloured „Aktuelle Woche" / „Nächste Woche" / „in X Wochen" pill plus a highlighted
  today-column), while the read-only standard timetable moves to its own „Stammplan"
  page (`/stammplan`, `StandardPlanController`) and navbar entry for parents and
  staff. `ResolvesWeek` now exposes `week.offset` and per-day `is_today`; the former
  „Abholplan" label is renamed „Wochenplan". Idea by Yvonne.
- **Slack deep-links open the normal login screen** instead of forcing "Sign in with
  Slack". Every Slack button routes through `slack.enter`, which now shows the login
  page (Slack, e-mail/password, or password reset) when signed out and still lands on
  the linked target afterwards. Slack sign-in is only ever the explicit button.
- **"Angemeldet bleiben" is checked by default** on the login screen.
- The **"Passwort vergessen?"** reset flow is now reachable from the login screen,
  and its e-mail is rendered **in German** (via `ResetPassword::toMailUsing` +
  `lang/de.json`). Delivering the mail in production requires configuring `MAIL_*`
  (SMTP) — see [`docs/deployment.md`](docs/deployment.md).

### Fixed

- A child with a same-day override no longer *also* shows in the board's „Heute
  hortfrei" list — a manual pickup for today means they're on the board, not Hortfrei.
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
