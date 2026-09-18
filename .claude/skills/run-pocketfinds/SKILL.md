---
name: run-pocketfinds
description: Build, run, and drive the PocketFinds Laravel marketplace app. Use when asked to start PocketFinds, launch the app, run its test suite, take a screenshot of a page (home/product/shop), smoke-test the landing page, or check for console errors and broken images on a running page.
---

PocketFinds is a Laravel 12 app (PHP 8.5) serving a marketplace: guest storefront (`/`, `/product/{id}`, `/shop/{slug}`) plus buyer/seller/admin/rider/logistics dashboards behind auth. Drive it by starting `php artisan serve` in the background, then pointing the Playwright driver at `.claude/skills/run-pocketfinds/driver.mjs` at a URL — it screenshots desktop + mobile viewports and reports console errors, broken images, and HTTP status in one JSON blob.

All paths below are relative to the repo root (where this file's `.claude/` lives).

## Prerequisites

Windows, verified this session — no Linux/apt-get involved:

```
php --version   # PHP 8.5.9 (cli), ZTS Visual C++ 2022 x64
node --version  # v24.19.0
npm --version   # 11.17.0
```

Composer dependencies and PHP itself must already be installed (`vendor/` present) — this skill doesn't cover initial `composer install`.

## Setup

The app connects to the **team's shared remote Supabase Postgres** (see `README.md`) — there is no local DB. `.env` must exist (copy from `.env.example` if it's missing; do **not** regenerate `APP_KEY` or edit the DB/Supabase values, or you'll be pointed at a different database than the team):

```bash
cp .env.example .env   # only if .env doesn't already exist
```

The driver has its own isolated dependencies (Playwright), separate from the app's own `package.json` — installing them does **not** touch the app's dependency tree:

```bash
cd .claude/skills/run-pocketfinds
npm install
npx playwright install chromium   # no-op if already cached from a prior run
```

## Build

None needed for the guest-facing pages this skill drives. Verified this session: `public/build/` does not exist and the app's own `node_modules` was never installed, yet `/`, `/product/{id}`, and `/shop/{slug}` all rendered correctly. Those pages load plain `<link>`/`<script>` tags straight from `public/css/*.css` and `public/js/*.js` — Vite (`resources/css/app.css`, `resources/js/app.js`) only serves the default unused Laravel welcome page. Don't run `npm run build` unless you're specifically working on that unused scaffold page or a dashboard view that does use it.

## Run (agent path)

Start the server in the background on a throwaway port, capture its PID, then drive it:

```bash
php artisan serve --port=8140 > /tmp/pf_serve.log 2>&1 &
echo $!   # save this PID — you need it to stop the server later
sleep 3
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://127.0.0.1:8140/   # expect 200
```

Then run the driver against any route:

```bash
cd /some/output/dir   # screenshots land in the current directory
node /path/to/repo/.claude/skills/run-pocketfinds/driver.mjs http://127.0.0.1:8140/ home --scroll
```

Verified output shape (real run against the live shared DB, this session):

```json
{
  "url": "http://127.0.0.1:8140/",
  "desktop": { "viewport": {"width":1440,"height":1000}, "httpStatus": 200, "file": "home-desktop.png", "consoleErrors": [], "title": "PocketFinds: Find It. Love It. Pocket It.", "h1": "Find It. Love It. Pocket It.", "brokenImages": 0, "totalImages": 7 },
  "mobile":  { "viewport": {"width":390,"height":844},  "httpStatus": 200, "file": "home-mobile.png",  "consoleErrors": [], "title": "PocketFinds: Find It. Love It. Pocket It.", "h1": "Find It. Love It. Pocket It.", "brokenImages": 0, "totalImages": 7 }
}
```

Artifacts land in the current working directory: `<outPrefix>-desktop.png`, `<outPrefix>-mobile.png` (both full-page), `<outPrefix>-facts.json`. Exit code is non-zero if either viewport got a non-200 status or logged a console error — check that before trusting a screenshot looks fine.

| driver.mjs arg | what it does |
|---|---|
| `<url>` | any route — `/`, `/product/123`, `/shop/some-slug` all work the same way |
| `<outPrefix>` | filename prefix for the three output files |
| `--scroll` | scrolls the full page first (triggers `IntersectionObserver` scroll-reveal animations before the screenshot) then returns to top — omit for a fast above-the-fold check |

**Stop the server by its exact PID, never by name:**

```bash
kill <the PID you saved above>
```

## Run (human path)

```bash
php artisan serve   # -> http://127.0.0.1:8000, open in a browser. Ctrl-C to stop.
```

## Test

```bash
php artisan test
```

Verified this session: 2 tests pass in ~4s. Safe to run — `phpunit.xml` overrides `DB_CONNECTION` to `sqlite`/`:memory:` for the `testing` environment, completely isolated from the real shared Supabase database. The only active feature test (`tests/Feature/ExampleTest.php`) just asserts `/` returns 200.

---

## Gotchas

- **Never `taskkill /F /IM php.exe` to stop a test server.** It kills *every* PHP process on the machine, including anyone else's running server or the user's own IDE tooling — hit this firsthand this session. Always background the server yourself and `kill` its exact PID (`kill $!` right after backgrounding it in the same bash command works — verified the port is actually released afterward, not just that the command exits 0).
- **The database is real and shared**, not a disposable local dev DB (see `README.md`) — `php artisan serve` and read-only browsing is the normal team workflow and is safe, but don't run destructive artisan commands (`migrate:fresh`, `db:wipe`) against it without asking; there's no local fallback to undo it.
- **Blade/CSS changes not showing up in the browser** is almost always a plain browser cache issue, not a Laravel view cache issue — try a hard refresh / incognito window first. `php artisan view:clear` is safe to run but rarely the actual fix (confirmed: the compiled-view cache re-validates against source file mtimes automatically on every request in this setup).
- **Windows SSL cert error on outbound HTTPS** (`cURL error 60: SSL certificate ... unable to get local issuer certificate`) — this hits image uploads to Supabase Storage specifically (a `POST`), not the guest pages this driver screenshots (`GET` only, no outbound calls from PHP). Documented in `README.md` with the `curl.cainfo`/`openssl.cafile` fix; not something this skill's read-only driving needs to worry about, but worth knowing if a task involves uploads.
- **The driver's `node_modules` lives inside the skill directory**, isolated from the app's own `package.json`/`node_modules` (which don't even exist in this checkout). Running `npm install` inside `.claude/skills/run-pocketfinds/` never touches the app's dependency tree.

## Troubleshooting

- **`curl` gets connection refused right after starting the server**: the `sleep 3` wasn't long enough, or the port is already in use by a leftover server from an earlier session — pick a different `--port` and check `/tmp/pf_serve.log` for the actual bind error.
- **Driver hangs waiting for `networkidle`**: check `hero_image`/product images pulling from Supabase Storage aren't timing out — the driver has a 30s navigation timeout and will throw a clear Playwright timeout error naming the URL rather than hanging forever.
