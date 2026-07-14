# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**hort-manager** — a management app for a German after-school daycare (*Hort*). Two-sided: **Erzieher** (staff) and **Eltern** (parents). The job it exists to do: track **when each child leaves each day, and how**.

**The frontend UI language is German.** User-facing strings, labels, and routes that users see should be German (domain terms below are the canonical vocabulary). Code, comments, and identifiers stay English.

## Run everything through Laravel Sail

This project runs in Docker via Sail. **Never run `php`/`composer`/`npm`/`artisan` on the host** — the host has the wrong runtimes (e.g. Node 18, too old for Vite 8). Prefix every command with `./vendor/bin/sail`. This overrides the bare `php artisan ...` examples in the Boost guidelines below.

| Task | Command |
|------|---------|
| Start / stop containers | `./vendor/bin/sail up -d` / `./vendor/bin/sail down` |
| App URL | http://localhost (port 80, served by Sail) |
| Vite dev server (HMR) | `./vendor/bin/sail npm run dev` |
| Build assets | `./vendor/bin/sail npm run build` |
| Migrate | `./vendor/bin/sail artisan migrate` (fresh: `migrate:fresh`) |
| Run all tests | `./vendor/bin/sail artisan test` |
| Run one test | `./vendor/bin/sail artisan test --filter=TestMethodName` |
| Format PHP (do before finishing) | `./vendor/bin/sail pint --dirty` |
| Tinker / REPL | `./vendor/bin/sail artisan tinker` |

**Database is SQLite** (`database/database.sqlite`) — there is no MySQL service. Backups are a file copy.

## Domain model

**All identifiers/enum values are English; only rendered text is German** (enums expose a `label()`).

```
User            role: staff | parent  (App\Enums\UserRole)   — isStaff() helper
  ⇄ children    child_user pivot (guardians)                  User::children / Child::guardians
Child           name, date_of_birth?, note   (flat list — NO groups)
WeeklySchedule  (Stammplan)  child + weekday 1–5 → planned_time + method + time_qualifier? + comment?
                method = App\Enums\DepartureMethod: picked_up | sent_home (companion `with_child` is
                Wochenplan/per-day only, NEVER the Stammplan — rejected server-side there)
                time_qualifier = App\Enums\TimeQualifier: by | at | from (bis / genau um / ab); only for
                sent_home, null = the implicit „genau um"
                NO row for a weekday = „Hortfrei" (structurally not a Hort day) — see „Hortfrei vs Absence"
DailyDeparture  (Tagesboard) one row per child+date (unique):
                status (App\Enums\DepartureStatus: present|picked_up|sent_home|excursion),
                planned_time/planned_method/time_qualifier (seeded from Stammplan, same-day overridable),
                companion (planned_method = with_child): companion_child_id + companion_confirmed
                (null=pending|true|false) + companion_confirmed_by (null=system-auto) + _at
                left_at + marked_by (set when staff marks them off), note
Absence         (Krank/„Kommt nicht") one row per child+date with a required reason
                (App\Enums\AbsenceReason: sick|away) + comment; the child is off the board/plan that day
DailyProgram    (Tagesprogramm) one row per date: lunch + activity + homework_start/end (Hort-wide)
                staff edit weekly at /programm; read-only on board + Abholplan
HomeworkDefault per-weekday default homework slot; DailyProgram homework overrides it
                (effective = override ?? weekday default; equal-to-default is stored as no override)
Excursion       (Ausflug)  name, date, depart_at, return_at, rsvp_deadline,
                departed_at/returned_at (live state) + child_excursion pivot
                pivot carries the parent RSVP: response (null=offen|true|false) + answered_by/answered_at
```

### Ausflug participation poll
Creating an excursion invites **every** child (a pending `child_excursion` row). Parents answer per child via `polls.update` (staff may answer too, even after the deadline). The answer lives on the child, so once either guardian answers it's resolved for both. `Excursion::participants()` = response `true` — that's who shows on the board. `pendingPolls` (shared Inertia prop) drives a parent notification banner until answered or the `rsvp_deadline` passes.

### Authorization (ChildPolicy + inline checks)
- **Reads are open to everyone** (open information policy) — never scope read queries per-parent. The parent↔child link only identifies whose kid is whose.
- **Children:** create = anyone (trust-based, Slack-SSO identity); **edit (incl. Stammplan), delete, and guardian links = staff _or_ the child's own guardian** (`ChildPolicy::update/delete/manageGuardians`).
- **Tagesboard:** marking departures = **staff only**; same-day plan override (`board.override`) = staff _or_ the child's parent.
- **Ausflug:** managing trips = staff only (ExcursionController guards with `ensureStaff()`); answering the **poll** = the child's parent (while open) or staff (anytime), via ExcursionRsvpController.
- **Admin role self-switch:** an admin can flip their own `role` (staff↔parent) from the avatar menu via `role.update` (`SwitchRoleController`, admin-only). It's a real, persisted change (admin status untouched) — no impersonation/session-override machinery.

### Wochenplan
Two parts: **Diese Woche** = effective plan per child for the selected week (Stammplan merged with any `DailyDeparture` overrides; adjusted days flagged, past days greyed), scoped to the user's **own** children (staff see all); week navigation via `?week=YYYY-MM-DD` (arrows + swipe). Editable per day by staff / the child's parent via `weekly-plan.adjust` (+ `weekly-plan.reset`) — any weekday from today on, not-yet-departed. An override comment lives on `DailyDeparture.note` and defaults to the day's Stammplan comment. Below: the read-only **Standard** Stammplan timetable (all children).

### Editing a day — the shared `DayEditor` popup
**One component, `resources/js/Components/DayEditor.vue`, edits any child on any date — used by BOTH the Wochenplan (grid cells, timeline, „nicht da" pills) and the Heute board („Abholzeit ändern" + „Hortfrei" pills). Do NOT add a second inline editor.** Open it with `dayEditor.value.open(child, day, dayMeta)`; it always posts to `weekly-plan.adjust` (set a plan), `absences.store` (Krank/„Kommt nicht"), or `weekly-plan.reset` (revert to Stammplan). Companion („geht mit … mit") is offered here too — the board feeds its picker via a `children` prop (each child's effective time today). A **complete plan is required**: a real pickup needs BOTH a method and a time; `with_child` needs a companion (its time mirrors them). Enforced in the popup (Speichern disabled) **and** server-side in `AdjustDayRequest` (`planned_method` required; `planned_time` required unless `with_child`). The board's older `board.override` endpoint still exists but the UI now edits through `DayEditor`/`weekly-plan.adjust`.

### Hortfrei vs. Absence — two different „not there"
- **Hortfrei** = *structural* non-attendance: the child's Stammplan simply has no entry for that weekday (no `WeeklySchedule` row). No reason, no record. Surfaced explicitly as a muted „Heute hortfrei (Stammplan)" line on the board and per-weekday in the Wochenplan „Diese Woche nicht da" summary; names are **clickable pills** (own children for parents, all for staff) that open `DayEditor` to add a one-off pickup. A child with a same-day override is NOT listed as Hortfrei (they're on the board). Unplanned children (zero `WeeklySchedule` rows) are the „Wochenplan fehlt" case, not Hortfrei.
- **„Kommt nicht" / „Krank"** = a *reported* `Absence` for a specific date, **with a required reason** — amber, undoable, and a separate flow. The Stammplan editor's non-attendance option is deliberately named **„Hortfrei"** (not „Kommt nicht") to keep the two apart.

### Tagesboard mechanics
`DailyBoardController` targets **today, or the next weekday on weekends**. It lazily `firstOrCreate`s a `DailyDeparture` per scheduled child from the Stammplan (carrying `time_qualifier`). A row is "overridden" when its plan differs from the Stammplan (shown as „heute geändert"). Excursions are an **overlay** (`rows[].excursion`), not a status swap — a child on a trip still gets marked picked up after returning.

## Routes / nav (German URLs, English route names)
`board` (/tagesboard, "Heute") · `weekly-plan` (/wochenplan) · `standard-plan` (/stammplan, read-only) · `children` (/children) · `excursions` (/ausfluege, staff only) · `program` (/programm, staff only). Also `role.update` (/rolle, admin self role-switch). Nav lives in `AuthenticatedLayout.vue` (role-aware; bottom tab bar on mobile; the admin „Meine Rolle" toggle sits under Benutzer). Demo logins: `erzieher@hort.test` (staff+admin) / `eltern@hort.test`, both `password`. Extra demo data via `sail artisan db:seed --class=DemoExtrasSeeder` (Hortfrei days, bis/ab qualifiers, a no-plan child).

**Links use [Laravel Wayfinder](https://github.com/laravel/wayfinder), not Ziggy.** Import typed helpers and call `.url` — e.g. `import { index as childrenIndex } from '@/routes/children'` → `:href="childrenIndex().url"`; args `childrenEdit(child.id).url`; query `weeklyPlan({ query: { week: date } }).url`. Generated files under `resources/js/{routes,actions,wayfinder}` are gitignored (regenerated by the Vite plugin on build). Watch for clashes with local symbols — alias the import (e.g. `import { mark as boardMark }`). Active-nav state is plain URL-prefix matching in `AuthenticatedLayout.vue` (Ziggy is gone).

## Slack integration
Three directions; **production setup is documented in [`docs/slack-setup.md`](docs/slack-setup.md)**. Everything outbound is gated on `SLACK_BOT_USER_OAUTH_TOKEN` — with no token the app degrades silently (no sends, no errors). Inbound callbacks are signature-verified by `VerifySlackSignature` (HMAC over the raw body) and CSRF-excepted in `bootstrap/app.php`.

- **Login = Sign in with Slack** (OIDC via Socialite + `socialiteproviders/slack`). `Auth\SlackController`: restricted to `SLACK_TEAM_ID`, auto-provisions a `parent`, links by email; **self-registration is disabled** (Slack-only). `/slack/enter?to=…` deep-links into the app, signing in via Slack first (auto-register) — Slack message buttons point here, not at pages.
- **Outbound DMs** (bot token, `chat:write`):
  - Departures: `DailyDepartureObserver` (on `left_at`) → `ChildDeparted` notification to the child's Slack guardians.
  - Excursions: `SlackRsvp` service (not a notification) posts per-child Ja/Nein buttons and **remembers each DM** in `excursion_slack_messages` (channel+ts). Answering anywhere (Slack button or app) `chat.update`s **every** guardian's copy to show the result + who answered (+ „Ändern" link); deleting an excursion marks them cancelled. Wired via `ExcursionObserver` (created/deleting) and both RSVP entry points.
  - Daily reminder: `excursions:remind-rsvps` (`ExcursionRsvpReminder`), scheduled 08:00 — needs `schedule:run` cron in prod.
  - Missing Stammplan: `wochenplan:remind-unset` (`ScheduleMissingReminder`) DMs the guardians of any child with no Stammplan yet; **`--dry-run`** lists who would be nudged without sending. Not scheduled (run manually after onboarding). A parent-facing „Wochenplan fehlt" banner (shared `childrenWithoutPlan` prop) nudges the same in-app.
  - App Home tab: `SlackHome` publishes a welcome + quick links on `app_home_opened`.
- **Inbound** (all `POST`, signature-verified): `/slack/interactions` (`SlackInteractionController` — RSVP buttons), `/slack/commands` (`SlackCommandController` — `/hort` quick links), `/slack/events` (`SlackEventController` — url_verification + `app_home_opened`).
- Notifications extend `SlackNotification` (base gates `via()` on the token). `SlackNotification`/`SlackRsvp`/`SlackHome` share the same Block Kit style; links use `route('slack.enter', …)` so `forceRootUrl(APP_URL)` keeps them correct behind the tunnel/proxy.

## Status

App is German end-to-end (`APP_LOCALE=de`, `lang/de/*` validation/auth messages; `Europe/Berlin` timezone). Built & tested: Kinder+Stammplan (with **„Hortfrei"** per weekday + **bis/genau um/ab** time qualifier), parent↔child roles + **admin user management** + admin self role-switch, Wochenplan/Stammplan timetable, Tagesboard, the **shared `DayEditor` popup** (Wochenplan + board), **companion pickups** („geht mit einem anderen Kind mit" with confirmation + cross-guardian Slack sync), Ausflug poll, Tagesprogramm + Hausaufgaben, birthdays, dark mode + de/en language switch, **full Slack integration** (SSO, departure/RSVP/companion/missing-plan DMs, interactive answers, `/hort` + App Home + free-text assistant), **installable PWA + web push** plus **freshness** (silent post-deploy reload, 15-min idle refresh, drag-down pull-to-refresh in `freshness.js` / `PullToRefresh.vue`). Self-hosted via a multi-arch GHCR image on a CalVer tag (see [`docs/deployment.md`](docs/deployment.md)).

**Planned (not built):** Richer admin control over which parents belong to which child (guardian management UI beyond the current per-child links).

---

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

</laravel-boost-guidelines>
