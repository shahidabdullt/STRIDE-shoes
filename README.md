# Stride

A fictional shoe store with a text and voice AI assistant, built as a Laravel 13 prototype.
Product scope, architecture and acceptance criteria live in [docs/BLUEPRINT.md](docs/BLUEPRINT.md).
Phase 0 (Gemini and database verification) results live in [docs/phase-0-findings.md](docs/phase-0-findings.md).

**Status:** storefront with cart and simulated checkout; text assistant (Gemini `gemini-3.6-flash`) and voice assistant (Gemini Live `gemini-3.8-live`) on every shop page; admin for the catalog, content, AI config, orders, conversations, knowledge gaps, data points and evaluations, with post-conversation analysis. Not done yet: Phase 7 hardening (CSP, Lighthouse, demo data) and deployment.

## Stack

- PHP 8.3+ (8.4 recommended, matching Forge) and Laravel 13
- **MySQL 8** (not SQLite, not MariaDB: see `CLAUDE.md` §5)
- Blade, Tailwind CSS 4 and vanilla JavaScript ES modules, bundled with Vite
- Node 22 LTS
- Gemini API for text; Gemini Live API (`gemini-3.8-live`) for voice (later milestones)

## Setup

Prerequisites: PHP 8.3+ with `pdo_mysql`, Composer 2, Node 22, and a running **MySQL 8** server.

1. Create two databases, `stride` and `stride_testing` (utf8mb4), and a user with access to both:

   ```sql
   CREATE DATABASE stride CHARACTER SET utf8mb4;
   CREATE DATABASE stride_testing CHARACTER SET utf8mb4;
   CREATE USER 'stride'@'localhost' IDENTIFIED BY 'choose-a-password';
   GRANT ALL PRIVILEGES ON stride.* TO 'stride'@'localhost';
   GRANT ALL PRIVILEGES ON stride_testing.* TO 'stride'@'localhost';
   ```

2. Install and configure:

   ```bash
   composer install
   npm ci
   cp .env.example .env
   php artisan key:generate
   ```

3. Edit `.env`: set `DB_PASSWORD` (and `DB_PORT` if your MySQL is not on 3306).
   Set `GEMINI_API_KEY` when you reach the assistant milestones. The key stays on the server and must never be committed.

4. Create the tables and build the assets:

   ```bash
   php artisan migrate
   npm run build
   ```

5. Load the demo catalog (40 shoes, 25 FAQs, 6 articles). On a local machine this also creates the admin `admin@example.com` / `password`:

   ```bash
   php artisan db:seed
   ```

   Anywhere else, create an admin yourself (the password is prompted, never passed on the command line):

   ```bash
   php artisan app:create-admin you@example.com
   ```

6. Run it with `composer dev` (server, queue worker, logs and Vite). The **queue worker is needed** for conversation analysis; `php artisan serve` alone runs the shop and assistants but analysis stays pending. For the scheduled jobs (closing idle conversations every 5 minutes, daily pruning) also run `php artisan schedule:work`.
   Voice needs a microphone and HTTPS or `localhost`.
   The health check is at `/up`. The admin sign-in is at `/admin/login`.

### Windows note

If port 3306 is taken (for example by XAMPP's MariaDB), run MySQL 8 on another port such as 3307 and set `DB_PORT` accordingly.

## Tests and checks

```bash
php artisan test        # PHPUnit on MySQL (database stride_testing)
npm run test:js         # node --test for the JavaScript modules
vendor/bin/pint --test  # code style
```

Before a demo or after changing the assistant prompt, run the golden-set check against the **real** Gemini API (not in CI; uses your key and quota):

```bash
php artisan assistant:eval              # 20 prompts, report in storage/app/private/eval/
php artisan assistant:eval --only=gap-gift-cards,refuse-order --pause=20
```

- The PHP suite always runs on MySQL: `phpunit.xml` forces `DB_CONNECTION=mysql` and `DB_DATABASE=stride_testing`, and a test fails if the server is not MySQL 8.
- Tests never call the real Gemini API: the base `TestCase` blocks outbound HTTP, so every external call must be faked with `Http::fake()`. Captured Gemini responses live in `tests/Fixtures/gemini/`.
- Tests that need MySQL FULLTEXT use the `TruncatesForFulltext` trait instead of `RefreshDatabase` (InnoDB FULLTEXT cannot see uncommitted rows).
- CI (`.github/workflows/tests.yml`) runs Pint, the PHP tests against a MySQL 8.4 service, the front-end build, the JS tests and a gitleaks secret scan.

## Repository layout

| Path | Purpose |
|---|---|
| `app/`, `routes/`, `resources/`, `database/`, `tests/` | The Laravel application |
| `docs/BLUEPRINT.md` | Approved product and technical blueprint (source of truth) |
| `docs/phase-0-findings.md` | Verified Gemini, browser-audio and MySQL findings |
| `spikes/` | Throwaway Phase 0 experiments, not part of the application (see `spikes/README.md`) |
| `CLAUDE.md` | Engineering rules for AI-assisted development |

## Secrets

Credentials live in `.env`, which is git-ignored. `.env.example` contains placeholders only. Do not commit API keys, tokens or real customer data.

## Known limitations

- Checkout is simulated: no payment, no emails, US addresses only. Shipping is a flat $6, free from $100 (`config/store.php`).
- Lighthouse has not been run on the product page (blueprint AC-S6 asks for Accessibility 90+ and Performance 80+ on mobile).
- The "last unit sells once" rule is tested with sequential checkouts and by asserting row locks, not with truly parallel requests.
- Product pages show a placeholder until images are added.

- The seeded local admin (`admin@example.com` / `password`) exists only when `APP_ENV=local`. Never seed it on a public server.
- Only 12 of 40 products have photos (`public/images/products/<slug>.jpg`; re-run `php artisan db:seed` after adding more). Others show a placeholder. There is no image upload in the admin.
- Gemini free tier: `gemini-3.6-flash` allows only **20 requests a day**. Chat turns (1–4 requests each) and conversation analysis (up to 3 requests each) share it; when it runs out, customers see a friendly fallback and analysis fails until the next day. Use a paid key before sharing the site.
- The chat keeps the last 20 messages as context; older context is dropped.
- Voice has been tested in Edge on Windows (including a fake-microphone run against the real API). Safari, Firefox and real phones are untested, and phones need HTTPS (the Forge deploy). Voice latency and interruption speed have not been measured.
- Voice transcripts and token counts are reported by the browser and marked as such in the admin. Token counts from voice sessions currently come through as 0.
- Analysis results (questions, outcomes, data points, evaluations) have only been tested with a faked Gemini response so far.
- No Git remote or CI run yet, so the workflow has not been executed on GitHub.
- No Forge deployment yet.
- Phase 0 items still unverified: voice interruption, hold-to-talk, the Live tool round trip, session resumption, Safari/Firefox and real phones. See `docs/phase-0-findings.md`.
