# TRMNL staff-room dashboard

A [TRMNL](https://usetrmnl.com) e-ink display for the staff room showing **who
leaves when** — today's pickup timeline and a Mo–Fr week overview. The device
**polls** a read-only JSON feed from the app; nothing is pushed.

## 1. Get the feed URL

The feed is a signed URL (no secret to manage — the signature derives from
`APP_KEY`). Print it:

```bash
# local
./vendor/bin/sail artisan hort:trmnl-url
# production
docker compose -f docker-compose.prod.yml exec app php artisan hort:trmnl-url
```

Copy the printed `https://YOUR_DOMAIN/trmnl/dashboard?signature=…` URL. It's
permanent; it only changes if you rotate `APP_KEY`.

## 2. Create two private plugins

TRMNL renders one template per plugin, so make **two** and add both to a
**Playlist** that rotates between them.

For each plugin (TRMNL → Plugins → Private Plugin → *Add New*):

1. **Strategy:** `Polling`
2. **Polling URL:** paste the URL from step 1 (same URL for both plugins)
3. Save, then open **Edit Markup** and paste:
   - Plugin **"Hort · Heute"** → [`heute.liquid`](heute.liquid)
   - Plugin **"Hort · Diese Woche"** → [`woche.liquid`](woche.liquid)
4. Add both to a playlist; set the **refresh rate to 15 minutes**.

> **Variable names:** in *Edit Markup*, TRMNL lists **Your Variables** from the
> live feed. The templates reference `today.*` and `week` at the root. If your
> TRMNL nests the response under `data`, prefix accordingly (`data.today.…`) —
> the live preview shows the exact path.

## 3. Outside opening hours

The Hort powers the device off after hours, so there's no "closed" screen — the
feed always shows the plan for today (or the next weekday on weekends).

## What the feed returns

`GET /trmnl/dashboard` → JSON:

- `generated_at` — "HH:MM" the snapshot was built.
- `today`: `weekday`, `date`, `present_count`, `next_pickup`, `departures[]`
  (`{ time, children[]: { name, method, changed, left, excursion } }`),
  `absent[]` (`{ name, reason }`), `program` (`lunch`, `activity`, `homework`).
- `week[]`: five days, each `{ weekday, date, is_today, excursion, departures[]: { time, names[] } }`.

Marks in the Heute template: `✓` already left · `(allein)` walks home alone ·
`✱` changed from the Stammplan today · `🚌` on an excursion.
