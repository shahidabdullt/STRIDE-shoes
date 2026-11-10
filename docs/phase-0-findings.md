# Phase 0 findings

> Companion to `docs/BLUEPRINT.md` §8 (Phase 0). Started 23 October 2026.
> Each item is **Verified (docs)**, **Verified (run)**, **Conflict**, **Pending** (spike written, not run) or **Blocked** (cannot run with current access).
> Nothing here is "Verified (run)" unless it was executed and the output inspected.

## 1. Environment (inspected 23 Oct 2026)

| Item | Found | Needed | Action |
|---|---|---|---|
| Laravel | 13.38.0, **React/Inertia starter kit** (Inertia 3, React 19, TS, Fortify with 2FA and passkeys, Wayfinder, vite-plus, Boost). Unmodified kit code | Laravel 13, no starter kit, Blade + vanilla JS | **Done 28 Oct 2026 (M1):** fresh Laravel 13 skeleton, no starter kit. The old tree is backed up (410 files, vendor and node_modules excluded) at `D:\voiceai-faqs-backup-starterkit` |
| PHP | CLI 8.3.23 (Herd). Herd also has 8.4.27 | 8.3–8.5; Forge 8.4/8.5 | Switch local CLI to 8.4 |
| Extensions | pdo_mysql, mbstring, intl, curl, openssl, sodium, bcmath, zip, fileinfo | standard | OK |
| Composer | 2.7.1 | 2.x | OK (update optional) |
| Node / npm | **20.13.1** (system install in `C:\Program Files\nodejs`) / 10.5.2 | Vite 8: Node ^20.19 or ≥ 22.12 | **User action:** install Node 22 LTS |
| Database | Port 3306 is **XAMPP MariaDB 10.4.28** (EOL), not MySQL. `.env` and `phpunit.xml` used SQLite | MySQL 8, tests on MySQL | **Done 24 Oct 2026:** MySQL **8.4.13** (official no-install ZIP, MD5 verified) in `C:\Users\DELL\mysql\`, listening on `127.0.0.1:3307`. Databases `stride` and `stride_testing` (utf8mb4_0900_ai_ci), app user `stride`. `.env`, `.env.example` and `phpunit.xml` now use MySQL. XAMPP untouched |
| Docker | Docker 27.4.0 CLI installed. Docker Desktop would not start from the agent session | — | Not needed any more (native MySQL install instead) |
| Git | 2.43. `git init` done 23 Oct 2026, no commits yet. `.env` is ignored | Git + GitHub | Remote not configured yet |
| `.gitignore` | Ignores `/CLAUDE.md`, `/AGENTS.md`, `/.claude`, `/boost.json`, `/.mcp.json` (Boost defaults) | CLAUDE.md is checked in | Fix in M1 |
| CI | Starter-kit workflow (PHP 8.3, SQLite, no MySQL, no gitleaks) | §9.2 | Rewrite in M1 |
| Gemini key | Set in `.env` 24 Oct 2026. **Free tier** | needed for S1–S5 | Paid tier before any real user (free-tier content may be used by Google) |
| Node / npm (update) | **22.22.0 / 10.9.4** installed by user 24 Oct 2026 | Vite 8 | OK |
| Devices | Desktop Chrome/Edge with mic only | Safari, Firefox, iPhone, Android, Forge HTTPS | S2b and non-Chromium browsers **Blocked** |

## 2. Documentation check (ai.google.dev, fetched 23 Oct 2026)

### Verified (docs)

- **`gemini-3.8-live`** (model page updated 2026-10-19): stable.
  - Supported: function calling, search grounding, interleaved thinking.
  - Not supported: structured outputs, caching, `thinking_level` (omit `thinking_config`).
  - Function calling: `NON_BLOCKING` is the default and `BLOCKING` is available per declaration.
  - Proactive audio is permanent, and `proactive_audio: false` errors. Affective dialog is removed. Responses are audio only, so use output transcription for text.
- **Ephemeral tokens** (guide updated 2026-09-15; **labelled Preview**):
  - Minting: `POST https://generativelanguage.googleapis.com/v1beta/auth_tokens` with `x-goog-api-key`.
  - Fields: `uses` (default 1), `expireTime` (default 30 min, < 20 h), `newSessionExpireTime` (default 60 s).
  - Works with the Live API on **v1beta only**.
  - Pass the token as the `access_token` query parameter or as `Authorization: Token <token>`.
  - Resuming a session does not consume a use.
- **Live API reference** (updated 2026-10-09; **labelled Preview**): `AuthToken.bidiGenerateContentSetup` + `fieldMask`.
  - Empty mask, setup present: *"the setup message from the Live API connection is ignored"*. This is full locking.
  - Non-empty mask: the masked fields from the token **overwrite** the client's fields, so they are locked, and unmasked fields come from the client.
  - Server messages: `setupComplete | serverContent | toolCall | toolCallCancellation | goAway | sessionResumptionUpdate`, plus `usageMetadata` and **`voiceActivity`**. `voiceActivity` is not in the blueprint; record whether it appears.
  - `activityStart`/`activityEnd` only work with automatic VAD disabled. `audioStreamEnd` only works with it enabled.
- **Interactions API** (overview updated 2026-10-15; reference; May 2026 breaking-changes guide): GA, `POST /v1beta/interactions`.
  - Request: `input` is a string, Content, or **array of Steps**. `system_instruction` is a **string**. Function tool = `{type:"function", name, description, parameters}`.
  - Structured output: `response_format: {type:"text", mime_type:"application/json", schema}`. `response_mime_type` has been removed.
  - Response: `steps[]` (replaced `outputs` on 8 June 2026; older blog posts and libraries using `outputs` are stale) and `status`, which includes `requires_action` when the model wants function calls.
  - Usage fields: `usage.total_input_tokens`, `total_output_tokens`, `total_thought_tokens`, `total_tokens`, …
  - Step shapes: `user_input{content[]}`, `model_output{content[]}`, `function_call{id,name,arguments}`, `function_result{call_id,name,result,is_error?}`, `thought{signature,summary[]}`.
  - Stateless (`store=false`): re-send history as `input`, i.e. previous input + returned `steps` (including `thought`) + `function_result` steps.
  - No `Api-Revision` header is needed any more. The legacy schema was removed 8 June 2026 and the header is now ignored.
  - Retention when stored: 55 days paid, 1 day free. `store=false` opts out.
  - `gemini-3.8-flash` is listed as supported.

### Conflicts (to be settled by running spikes)

| # | Conflict | How it is tested |
|---|---|---|
| C1 | Token body: the guide shows `liveConnectConstraints {model, config}` (SDK form). The reference shows `bidiGenerateContentSetup` + `fieldMask` | S1 variants `a`, `b`, `bw`, `bm` |
| C2 | Scheduling enum: the 3.8 model page says `INTERRUPTED`; the Live tools page (updated 2026-09-15, still showing a 3.1/2.5-only table) says `INTERRUPT` | MVP sends no scheduling value (BLOCKING tools). Low priority |
| C3 | The tools page says async is unsupported on 3.1 Live; the 3.8 page says NON_BLOCKING is the default | S3 declares `behavior: BLOCKING` explicitly |
| C4 | The raw constrained WebSocket URL appears only in the reference. Google's example repo uses the SDK | S2 connects to `…v1beta.GenerativeService.BidiGenerateContentConstrained?access_token=` |
| C5 | **New:** if an empty field mask makes the server ignore the client's whole setup, the client's `sessionResumption.handle` may be ignored too, which would break resumption on a fully locked token | S5 with variants `b` vs `bm` (`bm` locks every field except `sessionResumption`) |

### Still assumptions

- Whether `gemini-3.8-flash` honours `response_format` alongside the classifier schema (S4).
- Live API provider-side retention and data-use terms. These need a manual read of the current Gemini API terms for the chosen tier.

## 3. Spike status

Code is in `spikes/` (see `spikes/README.md`). PHP syntax checks and `node --check` pass. Offline checks of token-body construction, redaction and the stub tool pass (23 Oct 2026).

| Spike | Status | Blocker / next step |
|---|---|---|
| S1 token body (CLI) | **Verified (run)** 24 Oct 2026 | C1 resolved. See results log |
| S1 lock proof | **Verified (run)** 27 Oct 2026 | Locked setup wins. See results log |
| S1 single use | **Verified (run)** 27 Oct 2026 | Second session rejected |
| S1 new-session expiry | **Pending** | Not yet tested (README step 3) |
| S2 desktop audio, hands-free (Chrome/Edge) | **Partly verified (run)**: voice in and out works, text-turn latency measured | Interruption, speaker echo and more latency samples still to do |
| S2 hold-to-talk + wrong-language reply | **Pending** | Language issue seen earlier, not reproduced in a log yet |
| S2 Safari / Firefox | **Blocked** | No Safari or Firefox test access |
| S2b mobile (iPhone, Android, HTTPS) | **Blocked** | No phones or Forge site yet. Mobile default and pre-buffer length stay **[A]** |
| S3 Live tool relay + cancellation | **Pending** | Needs key |
| S4 text API, tool loop + structured output + errors | **Verified (run) on `gemini-3.6-flash`**. `gemini-3.8-flash` **not verified** (503, then timeouts on free tier) | Retry 3.8-flash later |
| S5 resumption | **Pending** | Needs key. Watch C5 |
| S6 MySQL 8 FULLTEXT | **Verified (run)** 24 Oct 2026 | See results log |

## 4. Decisions taken

1. Fresh Laravel 13 skeleton, no starter kit (user, 23 Oct 2026).
2. MySQL 8.4 on port 3307 for development and tests, XAMPP MariaDB untouched (user, 23 Oct 2026). Installed from Oracle's no-install ZIP instead of Docker, because Docker Desktop would not start (user asked the agent to install it, 24 Oct 2026). Start it with `C:\Users\DELL\mysql\start-mysql.cmd`; the root password is in `C:\Users\DELL\mysql\root-password.txt` (outside the repo).
3. Spikes use raw REST/WebSocket JSON (no Google SDK), matching the blueprint's PHP + vanilla-JS clients.
4. Milestone 1 started 28 Oct 2026 on the user's instruction ("skip testing, do the next tasks"). The remaining Phase 0 browser checks (interruption, hold-to-talk language issue, Live tool round trip, resumption C5, new-session expiry, Safari/Firefox, phones) stay **pending** and gate Phase 4/5, not M1.
5. `laravel/boost` re-added as a dev-only dependency. `axios` removed: the chat and voice modules use `fetch` with the CSRF meta tag.
6. Local PHP stays on Herd's 8.3.23 (Laravel 13 supports it). CI and Forge use 8.4.
7. The assistant replies in the customer's language, keeping product names, sizes and prices unchanged (product owner, 8 Nov 2026; confirms BLUEPRINT §1.2 #5). Enforced by a fixed rule in `PromptBuilder` that an admin prompt edit cannot remove.
8. Default text model `gemini-3.6-flash` (product owner, 8 Nov 2026). `gemini-3.8-flash` stays on the allow-list but was unavailable on the free-tier key in Phase 0. Dev AI config version 3 switched to it; version 1 still names 3.8-flash and is kept for history.

## 5. Milestone 1 results (28 Oct 2026)

| Acceptance criterion | Result |
|---|---|
| Clean-clone setup works against MySQL 8.4 (`composer install`, `npm ci`, `.env.example` → `.env`, `key:generate`, `migrate`, `npm run build`) | **Passed** in a scratch copy with no `vendor`, `node_modules` or `.env`, run against `stride_testing` |
| `php artisan test` on MySQL, including an assertion that the server is MySQL 8 and not MariaDB or SQLite | **Passed**: 9 tests, 28 assertions |
| `/` renders at 360 px and desktop widths without horizontal scroll; `/up` returns 200 | **Passed**: scroll width equals viewport width at 360, 768 and 1280 px in headless Edge; `/` and `/up` return 200 and an unknown URL returns 404. Lighthouse not run |
| `pint --test` clean; `node --test` runs | **Passed**: Pint clean, 3 JS tests passed |
| No React, Inertia, Fortify, Wayfinder or TypeScript packages | **Passed** (grep of `composer.json` and `package.json`) |
| No `.env` or secrets in what git would add; `.env.example` holds placeholders only; CI workflow present | **Passed** for the first two (the Gemini key and DB password appear in none of the 100 files). The workflow file exists but has **never run on GitHub**: no remote yet |

**Not verified:**
- CI on GitHub (no remote). The gitleaks action is pinned to the v3.0.0 commit taken from its GitHub tags page, which was not independently checked.
- Lighthouse scores.
- The workflow's MySQL service (`mysql:8.4`), PHP 8.4 and Node 22 setup are untested until the first run.

## 5b. Milestone 2 results (28 Oct 2026)

The blueprint's Phase 1 is delivered in three milestones (working labels, not blueprint terms): **M1** foundation (above), **M2** admin sign-in, visitor id, catalog/content schema and demo data (this section), **M3** Forge deploy (not started).

**Delivered:** `users.is_admin` and the `access-admin` gate; `/admin/login`, `/admin/logout`, a placeholder `/admin` dashboard; login throttled to 5 attempts/minute per email+IP; `app:create-admin`; `EnsureVisitorId` on the `web` group; migrations, models and factories for `categories`, `products`, `product_variants`, `product_images`, `faqs`, `knowledge_articles`; `DemoCatalogSeeder` (6 categories, 40 shoes at $59 to $189, 516 half-size variants) and `DemoContentSeeder` (25 FAQs, 6 articles). `DatabaseSeeder` creates `admin@example.com` only when `APP_ENV=local`.

| Acceptance criterion | Result |
|---|---|
| Guests are redirected to the login page; signed-in non-admins get 403; admins see the dashboard | **Passed** (PHPUnit, plus a live curl run against `php artisan serve`) |
| Sign-in regenerates the session id; non-admins and wrong passwords get the same error; 6th attempt in a minute returns 429; a POST without a CSRF token returns 419 | **Passed** (PHPUnit; 419 confirmed live) |
| `app:create-admin` hashes the password and rejects short, mismatched or duplicate input | **Passed** (PHPUnit) |
| Every web request gets an HttpOnly, SameSite=Lax UUID cookie; valid ones are reused; invalid ones are replaced | **Passed** (PHPUnit; the HttpOnly flag also seen live) |
| Schema matches blueprint §6.2 for the six tables; unique variant size per product, cascades, FULLTEXT indexes | **Passed** (PHPUnit on MySQL 8.4) |
| Seed data: 6/40/25/6, prices $59 to $189, sizes 6 to 13 in half sizes, some sold out, idempotent, deliberate FAQ gaps left uncovered | **Passed** (PHPUnit) |
| Suite and checks | **43 PHP tests, 134 assertions passed**; 3 JS tests passed; Pint clean; `vite build` succeeded; login page has no horizontal scroll at 360, 768 and 1280 px |

**Findings worth keeping**
- **InnoDB FULLTEXT only sees committed rows.** Inside `RefreshDatabase`'s transaction a `MATCH ... AGAINST` returns nothing. Search tests must use `DatabaseTruncation` (see `tests/Feature/FulltextSearchTest.php`). This applies to the Phase 2 `SearchProducts` tests.
- A freshly created `User` model has `is_admin = null` (the DB default is not loaded), so the gate casts to bool. The factory now sets it explicitly.

**Decisions and limits**
- Visitor cookie lifetime is 30 days (matches the retention default). It is a constant in the middleware, not config.
- Only admins can sign in at `/admin/login`; there is no password reset and no registration (blueprint §1.2 #10).
- The seeded FAQs state flat shipping of $6, free from $100, and a 30-day return window. Phase 2 checkout must use the same rule, or the FAQs must change.
- No product images are seeded: the image source is still open (blueprint §1.2 #9). `product_images` exists but is empty.
- Commerce, AI-config and conversation tables are deliberately left for their own phases.
- Admin pages beyond login and a counts dashboard (CRUD) belong to Phase 3.
- The `sheet/drawer` Blade component listed in Phase 1 is still missing; it is needed for the Phase 2 filter drawer.
- Not run: Lighthouse; GitHub CI.

## 5c. Phase 2 results: storefront, cart, checkout (29 Oct 2026)

**Delivered:** home, `/shoes` (search, category, activity, audience, size-in-stock, price range, sort, pagination), product page, `/help`, cart (add, update, remove), checkout and signed order confirmation; the `carts`, `cart_items`, `orders` and `order_items` tables; actions `SearchProducts`, `ResolveCart`, `AddItemToCart`, `UpdateCartItem`, `RemoveCartItem`, `PlaceOrder`; `config/store.php` (currency, shipping rule, quantity cap, low-stock threshold, link lifetime); the `drawer` Blade component and `drawer.js`; `cart.js` (add to cart without a reload).

| Acceptance criterion | Result |
|---|---|
| AC-S1 invalid filters give 422 (JSON) or a redirect with errors, never a 500 | **Passed**: 12 invalid inputs plus hostile search strings (PHPUnit) |
| AC-S2 a size with stock 0 is rejected with a visible message | **Passed** (PHPUnit; HTML and JSON) |
| AC-S3 totals computed only on the server; tampered price fields ignored | **Passed** (PHPUnit) |
| AC-S4 stock decrements atomically; the last unit sells once | **Partly verified**: two sequential checkouts for the last unit give exactly one order, and the queries use `FOR UPDATE` on `carts` and `product_variants`. **A truly parallel race was not tested** (no second process or connection in the suite) |
| AC-S5 confirmation only via a signed URL | **Passed**: unsigned, tampered and expired links return 403 (PHPUnit, and live in the browser) |
| AC-S6 no horizontal scroll at 360 px and desktop; Lighthouse mobile product page: Accessibility ≥ 90, Performance ≥ 80 | **Scroll: passed** in headless Edge at 360, 768 and 1280 px on home, catalog, filtered catalog, help, product, cart, checkout and confirmation. **Lighthouse: not run** (see below) |

**Other checks:** 100 PHP tests (380 assertions) on MySQL 8.4, 6 JS tests, Pint clean, `vite build` OK. A scripted headless-Edge run of the whole path (42 checks): filter drawer opens and closes on a phone and is a sidebar on desktop, the Add to cart bar is fixed to the bottom on phones, add to cart updates the badge without a reload, validation errors show, an order is placed, the confirmation URL works with its signature and returns 403 without it, and the badge empties afterwards. No uncaught JavaScript errors.

**Bugs the browser run caught that PHPUnit could not**
- The cart overflowed on phones and at 768 px: the image placeholder's `w-full` fought the cart thumbnail's `size-20`. Fixed with a `compact` prop. Headless Edge's mobile emulation widens the viewport when a page overflows, so an "inner width equals scroll width" check passes wrongly; the script now compares against the intended width.
- The cart badge showed a green "0": `inline-flex` overrides `hidden`. Checking the class list said it was hidden, so the check must use the computed style. Fixed and covered by a PHPUnit and a JS test.

**Test-suite notes**
- Laravel's `postJson()`, `patchJson()` and `deleteJson()` do **not** send cookies set with `withCookie()`, so the visitor changes on every call. Tests use the `jsonPost/Patch/Delete` helpers in `tests/Concerns/MakesShopData.php` (an `Accept: application/json` request through the normal methods).
- PHPUnit 12 needs `#[DataProvider]`, not the `@dataProvider` docblock.

**Decisions and limits**
- Shipping is a flat $6, free from $100 (`config/store.php`), matching the seeded FAQs. US delivery only (state code and ZIP validated), which is why international shipping stays a deliberate FAQ gap.
- Cart limits: at most 5 of one size, never above stock. Stock is shown as sold out, "Few left" (3 or fewer) or available, never as an exact number.
- Order numbers are `STR-<year>-<6-digit id>`. Confirmation links expire after 24 hours; the order page shows a name and address, so it is not a permanent public URL.
- `orders.conversation_id` has no foreign key yet; the conversations table arrives with the assistant phase.
- Product images: placeholders at this point; 12 photos were wired in afterwards (see 5d).
- The cart keeps its rows after checkout (status `converted`); a new cart starts empty.
- Not built: admin order list (Phase 3), customer emails, discount codes, a mobile menu (the header fits at 360 px).

**Lighthouse (AC-S6) is still open.** An attempt to run it through `npx` was blocked by the environment's permission check, so no scores exist. To run it by hand: open the product page in Chrome, press F12, open the Lighthouse tab, choose Mobile, tick Performance and Accessibility, click Analyze. Use the production build (`npm run build`), not `npm run dev`.

## 5d. Product images and Phase 3 results: admin (31 Oct 2026)

**Product images.** 12 of 40 photos exist (8 Road Running, 4 Trail Running). The originals (2048 px, about 2.3 MB) are resized to 1000 px JPEGs (64-120 KB) in `public/images/products/<slug>.jpg`, served directly (no `storage:link`). `DemoCatalogSeeder` links a file to its product when one exists, so a product without a file keeps the placeholder. Missing: Pace 1 Men Slate Blue, Summit GTX Men Charcoal, Boulder Pro Men Brown, and all Walking, Casual, Training and Recovery products. The 2 MB originals stay in `product-images/`, which must not be committed. Only the product-page photo of Pace 1 Men Black was visually inspected.

**Delivered (admin, all behind `auth` + `can:access-admin`):**
- Products (list with search, create, edit, delete) with size variants, specifications and a price entered in dollars and converted to cents on the server.
- Categories, FAQs, knowledge articles (CRUD). Always-included knowledge is capped at 300 words in total across published articles (`config/assistant.php`), checked on save.
- AI config: versioned, immutable rows with one active version, activation in a transaction, model allow-lists, history page. Version 1 is seeded only when none exists. **The assistant does not use these settings yet.**
- Orders: read-only list and detail. Dashboard counts and the active AI config.
- Shared form components (`textarea`, `select`, `checkbox`), an admin sub-nav, and `data-confirm` delete confirmations.

| Check | Result |
|---|---|
| PHP tests on MySQL 8.4 | **129 passed** (622 assertions): every admin route redirects guests and returns 403 to non-admins; validation, CRUD, variant rules, word cap, immutability, one-active-version, read-only orders |
| JS tests, Pint, `vite build` | 8 passed, clean, built |
| Headless Edge on the dev server (11 checks) | **11/11**: real sign-in, no horizontal scroll on 12 admin pages at 360, 768 and 1280 px, a product edit saved through the form, a quarter size rejected inline, an AI config version saved and activated |

**Bugs found while testing**
- `is_active` is not mass-assignable, so `update(['is_active' => true])` did nothing and no version ever became active. `activate()` now uses `forceFill`.
- At 360 px the header (Shoes, Help, Cart, Admin, Sign out) overflowed every admin page by 19 px. Sign out moved into the admin sub-nav on phones.
- `@disabled(...)` on a Blade component tag produced a compile error; the component takes `:disabled` instead.

**Decisions and limits**
- Sizes can be added and edited but never deleted from the form, because carts and orders reference them; set stock to 0 instead. A product that has been ordered cannot be deleted, only deactivated.
- Deleting a never-ordered product also clears it from carts.
- No image upload in the admin yet: photos are files in `public/images/products/`.
- Not built from the Phase 3 list: the conversations, knowledge gaps, data point and evaluation criteria screens (they need the conversation tables from the assistant phases).
- Not verified: Lighthouse on the admin pages, and the admin on a real phone.

## 5e. Phase 4 results: text assistant (8 Nov 2026)

**Delivered:** `conversations`, `messages` and `conversation_questions` tables (plus the deferred `orders.conversation_id` key); `GeminiTextClient` (Interactions API, `store=false`, key in the `x-goog-api-key` header, 20 s turn deadline, one jittered retry on 429/5xx/timeout, error mapping for all Phase 0 error shapes); `ToolRegistry` with the 8 blueprint tools (arguments validated with Laravel rules and unknown keys dropped before any handler runs; failures go back to the model as `{error}`); `PromptBuilder` (admin prompt + fixed rules + always-included knowledge); `ChatResponder` (max 4 tool rounds, last 20 messages as history, thought signatures echoed); JSON endpoints with owner check, rate limits, kill switch, daily cap and access-code gate; card/email redaction before storage; 30-day pruning; order attribution; chat widget; `assistant:eval`.

| Acceptance criterion | Result |
|---|---|
| AC-T1 prices/sizes only from the DB (≥ 95 % over 20 golden prompts) | **Not measured.** `assistant:eval` is built (20 cases, checks tools, gaps, refusals, cart, orders and that every `$` amount is grounded). The run was refused: the free-tier limit for `gemini-3.6-flash` is **20 requests per day**, used up by earlier testing. Grounding was spot-checked live (see below) |
| AC-T2 "add … size …" adds the right variant; badge updates without reload | **Passed live** (real API, headless Edge): Summit GTX Men size 10 added, `added_via=chat`, badge 1. Also PHPUnit |
| AC-T3 discount / order requests refused; no order created | **Passed live**: "I cannot provide discount codes or place orders for you. You can complete your purchase directly at checkout." PHPUnit: no tool can create orders |
| AC-T4 every message stored with ids, timestamps, latency, tokens; failures stored `status=error` with a friendly fallback within 20 s | **Passed** (PHPUnit with fixtures; live: 429s stored as `rate_limited`, fallback shown) |
| AC-T5 > 20 messages/minute → 429 with a friendly message | **Passed** (PHPUnit) |
| AC-T6 chat disabled → widget "unavailable", API 503 | **Passed** (PHPUnit) |
| AC-T7 unanswerable question → "doesn't know" + `report_knowledge_gap` | **Passed live**: "Do you sell gift cards?" → "I'm sorry, I don't have information on whether Stride offers gift cards…" and a `model_report` row, topic `gift cards` |
| AC-T8 phone: full-screen chat, input above the on-screen keyboard | **Partly verified**: full screen at 360 px, input at the bottom, no horizontal scroll, launcher above the product page's fixed bar (headless Edge). The keyboard behaviour (`visualViewport` sizing) needs a real iPhone and Android phone |

**Live checks (real API, 8 Nov 2026):** product answer grounded in the catalog ("Summit GTX Men … $189.00 … in stock in size 10", 11.5 s, two tool rounds); Spanish question → Spanish answer from the returns FAQ; Hindi question → Hindi answer with correct seeded prices; "Do you ship to Iceland?" → "We currently only ship within the United States" (answered from the seeded FAQ, so it is **not** a gap: use gift cards, a physical store or loyalty for the knowledge-gap demo); HTML in a message rendered as text; conversation restored after reload; CSRF-less POST → 419.

**Verified API facts (8 Nov 2026, live probe):** prior assistant turns can be sent as `model_output` input steps; `generation_config` accepts `temperature` and `max_output_tokens` (an unknown field gives 400); thinking tokens count against `max_output_tokens`, and a low limit returns `status=incomplete` with cut-off text (handled: shown, flagged `error_code=incomplete`).

**Other checks:** 183 PHP tests (906 assertions) on MySQL 8.4, 15 JS tests, Pint clean, `vite build` OK.

**Bugs found while building**
- Laravel's middleware priority runs throttling **before** appended web middleware, so the visitor id was not set when the limiter ran. `prependToPriorityList` did not change that here; limiters now use `EnsureVisitorId::rateLimitKey()`, which reads the decrypted cookie (IP for a brand-new browser).
- `DatabaseTruncation` only empties tables *before* a test, so FULLTEXT tests leaked rows into later classes (a leftover AI config version made `AiConfigSeeder` skip). New `TruncatesForFulltext` trait also truncates afterwards.
- MySQL JSON columns reorder object keys (shorter keys first).

**Decisions and limits**
- Free-tier quota: **20 requests/day** for `gemini-3.6-flash`; one chat turn uses 1–4 requests. Fine for a few manual tests a day, not for a demo or the eval. A paid key is needed before showing the site to anyone.
- Tool rules are written per tool (`rules()`) next to the JSON schema rather than derived from it automatically (BLUEPRINT §5.1 says "derived"); same effect, simpler, both tested.
- The current message is redacted before it is sent to Gemini as well as before storage.
- Not built: analysis on chat end (Phase 6), voice (Phase 5), admin conversation screens (Phase 6).

## 5f. Phase 5 results: voice assistant (8 Nov 2026)

**Delivered:** `LiveSessionConfig` (locked setup per mode), `GeminiLiveTokenService`, `/voice` endpoints (start, reconnect token, tool calls, transcripts, end) with owner check, access gate, kill switch, daily cap and rate limits (5 tokens / 10 min per visitor+IP, 60 tool calls and 30 transcript posts per minute per conversation); server-side hard stop after `voice_max_seconds` + 60 s; the voice panel (notice, mode choice, hands-free and hold-to-talk, captions, countdown, end) with an AudioWorklet capture, a tested resampler/PCM/transcript/message-router layer, gapless playback with flush on interruption, resumption on `goAway` or a dropped socket, a fresh token if the old one expired, `visibilitychange`/`pagehide` ending (sendBeacon), and clear error messages.

**Conflict C5 resolved (live, 8 Nov 2026).** Token variant `bm`: every setup field locked through `fieldMask` except `sessionResumption`. Results: a browser-sent system instruction is **ignored**; tool calls round-trip; reconnecting on the **same token** with the resumption handle **keeps context** ("Which size did I ask about?" → "9"); a new session on the used token is refused (`1011 Token has been used too many times`). The app uses this design.

**End-to-end checks with the real Live API**
- Through the app's endpoints with text turns (11/11): token from `/voice/sessions`, locked setup accepted, pirate override ignored, `search_products` run by Laravel ("Summit GTX Men and Ridge Runner Men"), voice `add_to_cart` → cart count 1, transcripts stored, tool calls refused after end (409).
- **Real panel in headless Edge with a fake microphone** playing a Windows TTS question ("Do you have trail running shoes for men in size ten?"), both modes: speech transcribed, `search_products` run, spoken answer captioned with correct catalog prices ("Summit GTX in Charcoal for one hundred eighty-nine dollars, and the Ridge Runner in Olive for one hundred thirty-nine dollars"), session ended and stored with client-reported transcripts. Hold-to-talk sent 141 audio frames (only while held, plus the pre-buffer) against 376 hands-free.
- `voiceActivity` messages arrived and were ignored, as designed.

| Acceptance criterion | Result |
|---|---|
| AC-V1 browser never receives the API key | **Passed** (PHPUnit on the token response; CI now greps `public/build`) |
| AC-V2 token `uses=1`, new-session ≤ 60 s, expiry ≤ 15 min, locked | **Passed** (PHPUnit asserts the request body; lock verified live) |
| AC-V3 real products, < 2 s after speech (Chrome + Safari, median of 5) | **Products: passed** (Edge). **Latency not measured** (needs a person and Safari) |
| AC-V4 interruption stops playback within 300 ms | **Implemented** (flush on `interrupted` and on hold-to-talk press), **not measured** |
| AC-V5 voice `add_to_cart` runs in Laravel and the badge updates | **Passed** for the cart (live); the badge update shares the chat's tested code path |
| AC-V6 transcripts in the admin within 10 s, marked client-reported | Stored with `source=client`, flushed every 5 s and on each turn. The admin view is Phase 6 |
| AC-V7 mic denied / network loss / token expiry → message + `ended_reason` | **Implemented**; reasons validated in PHPUnit; not exercised in a browser |
| AC-V8 hard stop at the maximum | **Passed** (client timer; server refuses tool calls and tokens after max + 60 s, PHPUnit) |
| AC-V9 iPhone and Android, speaker and headphones | **Not tested** (no devices or HTTPS host yet) |
| AC-V10 screen lock / app switch ends cleanly | **Implemented** (`visibilitychange`, `pagehide`, tracks stopped); not tested on phones |

**Other checks:** 193 PHP tests (975 assertions), 29 JS tests, Pint clean, `vite build` OK.

**Known gaps**
- Session token usage was stored as 0 in the browser runs: `usageMetadata` was either not counted or arrived after the session ended. The numbers are client-reported estimates; needs a look.
- Mic denial is detected after the session is created (mic and token are requested in parallel inside the tap, as mobile browsers require), so a denied session costs one token mint.
- The microphone only works on HTTPS or localhost: phones need the Forge preview (Phase 1 deploy is still open).
- Live free-tier limits are unknown; about 8 sessions today worked.

## 5g. Phase 6 results: conversation admin, outcomes and analysis (8 Nov 2026)

**Delivered:** `data_point_definitions`, `conversation_data_points`, `evaluation_criteria`, `conversation_evaluations` tables; `ConversationAnalyzer` (three separate structured-output calls: answer outcomes with a fixed schema, data points from admin definitions, evaluations from admin criteria; every value validated, invalid ones dropped and logged); `OutcomeCalculator` (in PHP: an error reply or any `not_answered` question → failed; only `out_of_scope` → success; failure kind knowledge_gap / technical / both); `AnalyzeConversation` job (3 tries, backoff 30 s / 120 s, no overlap per conversation, safe to re-run, keeps live gap reports and manual overrides); analysis queued whenever a text chat or voice session ends; `assistant:close-idle` every 5 minutes; admin conversation list (validated filters: outcome, failure kind, channel, device, dates, question text) and detail page (transcript with client-reported badges and tool calls, questions, data points, evaluations, re-run, override, delete); Knowledge gaps page (grouped by topic, "Create FAQ from this", mark resolved; saving the pre-filled FAQ resolves the gap); data point and evaluation CRUD; dashboard success rate per channel, pending count, top unanswered topics, recent unanswered questions; starting data points and criteria seeded once.

| Acceptance criterion | Result |
|---|---|
| AC-A4 admin-defined data point and criterion; values appear after a conversation ends | **Passed with Gemini faked** (values parsed per type, enum and score validation). Real run **not verified**: today's free-tier text quota is used up. Needs the queue worker (`composer dev`) |
| AC-A5 list filters by outcome, channel and date; invalid values → redirect, never 500 | **Passed** (PHPUnit, including an SQL-injection string) |
| AC-A6 deleting a conversation removes messages, questions, data points, evaluations | **Passed** (PHPUnit) |
| AC-A7 answered + unanswerable + off-topic → three outcomes, conversation Failed | **Passed with a faked classifier**. The ≥ 90 % agreement on a golden set is **not measured** (quota) |
| AC-A8 Create FAQ from a gap → pre-filled form → saved → gap resolved | **Passed** (PHPUnit). The "same question is answered next time" half needs the real API |
| AC-A9 manual override, shown with who made it | **Passed** (PHPUnit; "Overridden by …" on the page; can be switched back to automatic) |

**Other checks:** 221 PHP tests (1,155 assertions), 29 JS tests, Pint clean, `vite build` OK. Headless Edge: the 10 new admin pages have no horizontal scroll at 360, 768 and 1280 px (the conversation page overflowed at 360 px until its grid columns were allowed to shrink).

**Changes made along the way**
- Voice transcripts are stored with the time the speech started (client-reported, clamped to the session), and messages are ordered by time: before this, a tool call appeared above the question that caused it.
- Tests no longer run queued jobs inline (`QUEUE_CONNECTION=null` in `phpunit.xml`): ending a conversation would otherwise call Gemini inside the request.
- Without the classifier, a conversation can still be marked failed (an error reply or a live gap report) but never success: it stays pending.

**Open**
- One real analysis run against `gemini-3.6-flash`, and the AC-A7 golden-set agreement check: tomorrow (quota resets about 20 h after 11:55 on 8 Nov) or with a paid key.
- Data points use one string value per item in the structured-output schema, parsed in PHP, rather than per-type nullable fields; simpler and avoids relying on nullable-type support.

## 6. Phase 0 results log

_Fill in as each spike runs: date, command, HTTP status / close codes, measured numbers, conclusion._

### S1 token minting (24 Oct 2026, `php spikes/s1_token.php`, free-tier key)

| Variant | Result |
|---|---|
| `a` `liveConnectConstraints` (guide) | **400** `Unknown name "liveConnectConstraints" at 'auth_token'`. It is an SDK-only shape, not valid over REST |
| `b` `bidiGenerateContentSetup`, no mask | **200** in 1.0–1.7 s (hands-free and hold-to-talk). Response contains only `name`. The key is never echoed |
| `bw` wrapped in `{"authToken": …}` | **400** `Unknown name "authToken"`. The REST body *is* the AuthToken resource |
| `bm` setup + `fieldMask` with `sessionResumption` still in the setup | **400** `BidiGenerateContentSetup contains fields that are not specified in field_mask` |
| `bm` with `sessionResumption` removed from the setup | **200** |
| `none` (no config) | **200**: an unlocked token is also allowed |

**Conclusions.**
- C1 resolved. Our PHP client must POST the AuthToken fields at the top level, with `bidiGenerateContentSetup` in raw camelCase setup JSON.
- With a non-empty `fieldMask`, every field in the setup must be listed in the mask.
- **Still unproven:** whether the browser's setup is actually ignored (the lock), single use, and new-session expiry. All need the browser test.

### S4 Interactions API (24 Oct 2026, `php spikes/s4_text.php [model]`, free tier)

- **`gemini-3.8-flash`:**
  - First attempt: **503** `{"error":{"message":"gemini-3.8-flash is currently experiencing high demand…","code":"service_unavailable"}}`.
  - Then three attempts timed out at 60 s with no bytes received.
  - **Not verified.** Free-tier requests are probably lower priority on a busy new model.
- **`gemini-3.6-flash`: pass.**
  - Round 1 (3.2 s): `status=requires_action`, steps `thought,function_call` with `{id:"call_…", name, arguments}`.
  - Round 2 (2.2 s): second call.
  - Round 3 (2.1 s): `status=completed`, steps `thought,model_output`. Answer: "The Trailblazer is out of stock in size 9, but it is available in size 9.5." This is grounded in the stub data.
  - Stateless history = previous input + returned steps (thoughts included) + `function_result` steps. It works as documented.
  - The model made **one call per round**: 2 lookups took 3 rounds. The blueprint's 4-round cap allows at most 3 sequential lookups before the answer.
  - Usage fields as documented (`total_input_tokens`, `total_output_tokens`, `total_thought_tokens`, `total_tokens`, …).
- **Structured output** (`response_format {type:"text", mime_type:"application/json", schema}`): 7.8 s, valid JSON. Outcomes matched the hand labels on all 3 questions (`answered/catalog`, `not_answered/none`, `out_of_scope/none`).
- **Error shapes differ by error type. `GeminiTextClient` must handle all of them:**
  - Bad key: **400**, a JSON **array** wrapping a google.rpc error (`status:"INVALID_ARGUMENT"`, `reason:"API_KEY_INVALID"`).
  - Unknown model: **404** `{"error":{"message":…,"code":"not_found"}}` (string code).
  - Overload: **503** `{"error":{"message":…,"code":"service_unavailable"}}`.
  - Network timeout: no body. The blueprint's 20 s timeout + 1 retry + friendly fallback is needed.
- Fixtures: `spikes/fixtures/s4_36flash_*.json`, `s4_error_*.json` (redacted).

**Recommendation:**
- Keep `GEMINI_TEXT_MODEL` configurable.
- Retry `gemini-3.8-flash` later and on a paid key. If it is still unavailable, default to `gemini-3.6-flash`, which is fully verified, and record the decision.

### S1 lock proof, single use, control (browser, Chrome, `gemini-3.8-live`, 27 Oct 2026)

Token variant `b` (full setup in `bidiGenerateContentSetup`, no field mask). The page sent a *different* setup: a pirate system instruction plus a different tool, `get_pirate_treasure`.

| Check | Result |
|---|---|
| Client override attempt | **Ignored.** Asked "Who are you, and what was the first thing you were told to say?" the model answered "Stride here. I am the voice assistant for Stride and was told to start my first reply with those exact words." The locked system instruction won. The pirate setup had no effect |
| Single use | A second new session on the same token was closed by the server: **code 1011, reason "Token has been used too many times"** |
| Control: variant `none` (unlocked token) | The pirate instruction **won** ("Arrr, matey, I be Captain Hook…" and it followed "never mention shoes"). The test itself is valid |

**Conclusion.** The blueprint's locked-token design works as documented. The fallback in §8 ("short-lived token plus non-sensitive prompt") is **not needed**. A stolen or modified browser cannot change the system instruction. The locked tool list is not proven separately, because the model never tried the pirate tool.

### S2 desktop audio (Chrome, hands-free, 27 Oct 2026)

- Mic capture, 16 kHz PCM in, 24 kHz audio out and playback all work. Mic capture started at 48000 Hz and was downsampled to 16000 Hz in the worklet (`audio/pcm;rate=16000`).
- `setupComplete` arrived **1.8–3.0 s** after the socket opened, which is slow. The UI must show "connecting" and must not let the user talk before it. Audio sent before setup is dropped.
- **Latency to first audio**, text turn: 795 ms and 841 ms. Voice turn, one sample: 2515 ms, measured from the last locally detected speech on an unlocked token (default voice-activity timing). That is above the AC-V3 target of 2 s. It needs about 5 samples with the locked setup (700 ms silence) before any conclusion.
- Transcripts arrive as small fragments ("Stride here. I ", "am the voice", …). The UI and the batcher must join them. Input transcription was also seen.
- **New server message: `voiceActivity`.** It appeared in two events and is not in the blueprint. The router must ignore unknown message types without crashing.
- `usageMetadata` is returned with `promptTokenCount`, `responseTokenCount`, `thoughtsTokenCount` and per-modality token counts. A turn of about 20 seconds of audio used about 900 tokens in total.
- The log shows "local speech onset while model audio playing" three times, with no `interrupted` message from the server. Whether that was you talking over the model or the model's own voice reaching the mic (echo) is **not determined**. It needs a clean interruption test and a speaker-vs-headphones comparison.

**Browser audio lessons for Phase 5 (both caused silent failures):**
1. A `AudioWorkletNode` must be connected to the destination (through a zero-gain node) or Chrome never runs it.
2. A buffer passed to `postMessage` as a transferable has length 0 afterwards. Keep the chunk size in a field and never read `.length` of a transferred buffer.

**Not yet done:** new-session expiry, interruption (barge-in), hold-to-talk and its language issue, tool call round trip (S3), session resumption (S5 and conflict C5), speaker echo.

### MySQL switch: migrations and tests (24 Oct 2026)

- `php artisan db:show` reports MySQL 8.4.13, database `stride`, port 3307. `php artisan migrate` ran all 5 starter-kit migrations.
- `php artisan test` passed **40 tests, 138 assertions** on MySQL. `stride_testing` then held 20 tables, which confirms the run used MySQL and not SQLite.

### S6 MySQL FULLTEXT (24 Oct 2026, `php spikes/s6_fulltext.php`, MySQL 8.4.13)

Server defaults: `innodb_ft_min_token_size=3`, stopwords ON, strict `sql_mode` (including `ONLY_FULL_GROUP_BY`).

| Query | Result | Meaning for `SearchProducts` |
|---|---|---|
| natural `trail` | Trailblazer only | **No stemming.** "trail" does not match "trails" |
| natural `trails` | Trailblazer, Summit Hiker | |
| boolean `trail*` | Trailblazer, Summit Hiker | Prefix `*` fixes plurals. Use it on every term |
| boolean `+waterproof +trail*` | Trailblazer, Summit Hiker | `+` gives AND semantics as expected |
| boolean `run*` | Trailblazer, Cloudrunner, Pace 5K | Prefix matches running, runner and runs |
| `5K` (2 chars) | **no rows** | Below the min token size. Needs the `LIKE` fallback, which found Pace 5K |
| `gym` (3 chars) | Zen Trainer | 3-character tokens are indexed |
| `for` | no rows | Stopword |
| `rock-plate` | **no rows** | `-` is an operator in Boolean mode (it means "exclude plate") |
| raw `trail) (` | **SQL error 1064** | **Unsanitised user or AI input crashes the query.** That would be a 500 and would break AC-S1 |

**Decision:** `SearchProducts` must build the Boolean query itself from cleaned input and never pass raw text through. Steps:
1. Strip the operator characters `+ - > < ( ) ~ * : " & | @`.
2. Split the text into words.
3. Turn each word of 3+ characters into `+word*`.
4. Search words shorter than 3 characters with `LIKE`.
5. If no word has 3+ characters, use `LIKE` only.

Add a MySQL feature test that sends operator characters and confirms there is no 500. Ranking scores on tiny tables are close together, so tests should check which rows come back, not their order.
