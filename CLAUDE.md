# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

Laravel 12 + MoonShine 4 admin panel for **"Свои Delivery"** (alcohol delivery, Russian market). `src/Domain`/`src/Services`/`src/Infrastructure` now hold real code — catalog (`Domain\Catalog`, `Services\CatalogImport`), auth (`Domain\Auth`), profile (`Domain\Profile`), Untappd sync (`Domain\Untappd`, `Services\Untappd`) — see "Architecture: PSR-4 layout beyond `app/`" below. The public-facing frontend uses a token-driven Blade + Alpine component library (`<x-ui.*>`) — see "Frontend component library" below — and `laravel/fortify` provides the auth backend, with views composed entirely from that library — see "Authentication (Fortify)" below. Treat existing structure as intentional scaffolding to build into, not legacy to work around.

## Conventions

Write code comments (docblocks, inline `//`) in **Russian** — the codebase currently mixes Russian and English comments, but new/edited comments should be Russian going forward. Identifiers, commit messages, and this file stay in English.

## Stack

- PHP 8.2, Laravel 12, MySQL (dev, see `.env`), database-backed session/cache/queue
- MoonShine 4.18 — admin panel framework (Blade + Alpine, no Livewire)
- `defstudio/telegraph` + Laravel Socialite + `socialiteproviders/telegram` — Telegram login/linking, wired and live — see "Telegram login" below
- Vite + **Tailwind CSS v3.4** (`postcss.config.js` + `autoprefixer`, no `@tailwindcss/vite` plugin — the project targets old browsers, see below) + Alpine.js 3.15
- `APP_LOCALE=ru` (fallback `en`) — the product is Russian-language; `lang/ru/*` is fully translated via `laravel-lang/common`
- Dev tooling: Laravel Debugbar, IDE Helper, Pint, Pail, Sail

## Legacy-browser target

`package.json` declares `browserslist: ["> 0.5%", "last 2 versions", "not dead", "Safari >= 13.1", "Chrome >= 80"]`. This is why Tailwind is pinned to v3 (v4 requires modern-browser CSS features) and why the component CSS avoids flex `gap` (Safari < 14.1), `:focus-visible` without a fallback (Safari < 15.4), `color-mix()`, and WebP. Don't reach for these without checking they degrade safely first.

## Frontend component library (`<x-ui.*>`)

Design tokens (colors, type scale, radii, shadows) live in `tailwind.config.js` `theme.extend` — **no component file should contain a raw hex value**. The visual source of truth is `data/design-system/project/design-system.html` (a static brandbook mockup; recreate its visual output, don't copy its markup).

- Atoms/molecules live in `resources/views/components/ui/*.blade.php`, used as `<x-ui.btn>`, `<x-ui.input>`, etc. — all anonymous Blade, no class-based components (Tailwind's `content` globs cover `resources/**/*.blade.php` but not `app/View/Components/**/*.php`, so classes are invisible to the JIT scanner).
- Layouts are `resources/views/components/layouts/{app,auth,site,header,footer}.blade.php`, used as `<x-layouts.app>` / `<x-layouts.auth>` / `<x-layouts.site>`. `app` is the bare `<html>`/`<head>`/`<body>` shell (fonts, Alpine, skip-link); `auth` wraps it for chrome-free centered auth cards; `site` wraps it with `<x-layouts.header>` + `<main>` + `<x-layouts.footer>` for public content pages — use `site`, not `app` directly, for anything that isn't an auth screen.
- `tailwind.config.js`'s `borderRadius`/`boxShadow` **redefine** Tailwind's stock scale (`rounded-md` = 3px, not 6px; `shadow-md` is teal-tinted). This is deliberate brand styling, not a mistake.
- Form controls (`<x-ui.input>`, `<x-ui.select>`, `<x-ui.checkbox>`, …) accept a value both the classic Blade way (`value`/`old()`) and via Alpine `x-model`. Under `x-model` the component rewrites it to `x-model.fill` and still emits the server value so `old()` keeps working — see the header comment in `input.blade.php` before changing this. Checkbox/radio can't use `.fill` (Alpine can't bridge booleans that way); those need state seeded from PHP via `<x-ui.form :state="[...]">`.
- `@aware()` is used for exactly one key across the whole library: `mode` on `<x-ui.form>`. Never `@aware(['name'])` — it walks the *entire* ancestor component stack, not just the immediate parent, and would silently pick up a `name` from any unrelated ancestor.
- `/ui-kit` (local-only route) renders every component/variant/state and three live forms (`default`/`enhance`/`ajax` mode) — the way to verify component changes without Fortify installed.

## Authentication (Fortify)

`laravel/fortify` v1.x, Blade mode (`config('fortify.views')` = `true`) — the `<x-ui.*>` library is the frontend, there is no Breeze/Jetstream/Livewire. Views live in `resources/views/auth/{login,register,forgot-password,reset-password,confirm-password}.blade.php`, wired via explicit `Fortify::*View()` closures in `app/Providers/FortifyServiceProvider.php`, and are composed entirely from existing `<x-ui.*>`/`<x-layouts.*>` components — no component file needed to change for this.

- **Enabled features** (`config/fortify.php`): `registration`, `resetPasswords`, `updateProfileInformation`, `updatePasswords`, **`emailVerification`**. The middle two are the `PUT /user/profile-information` and `PUT /user/password` endpoints consumed by the private account area — see "Private account area" below.
- **Email verification is live, not deferred** — `Features::emailVerification()` is enabled, `Fortify::verifyEmailView()` is wired, and `routes/web.php` guards `/account` with `middleware(['auth', 'verified'])`. A freshly registered user is *not* redirected straight to `config('fortify.home')`'s content: `RegisterResponse` still redirects there unconditionally, but the `verified` middleware immediately bounces an unverified visitor to `route('verification.notice')` on the very next request. `MAIL_MAILER=log` means the verification link only ever lands in the log file, not a real inbox — fine for dev, but don't assume this flow is inert.
- **Deliberately deferred, with reasons:** two-factor authentication (no product need yet — its migration was deleted after `fortify:install`, re-publish via `vendor:publish --tag=fortify-migrations` when enabling); passkeys (WebAuthn needs far newer browsers than this project's `Safari >= 13.1 / Chrome >= 80` target — same constraint that pins Tailwind to v3); phone/SMS login (no wiring, no button). Telegram login **is** live — see "Telegram login" below.
- **`Fortify::confirmPasswordView()` is mandatory, not optional.** With `views => true`, `GET /user/confirm-password` is registered unconditionally regardless of which features are enabled, and Fortify has no default `ConfirmPasswordViewResponse` binding — omitting the view callback means that route 500s. `auth/confirm-password.blade.php` exists solely for this.
- **`config('fortify.home')` is `'/account/profile'`** — read by `LoginResponse`, `RegisterResponse`, and `PasswordConfirmedResponse`. Note `PasswordResetResponse` redirects to `route('login')` instead, with `session('status')` — that's why `<x-ui.status-alert>` is on the login page, not just forgot-password.
- **The login rate limiter has a custom `->response()`** in `FortifyServiceProvider` — without it, a throttled login returns a bare Symfony 429 instead of the translated `auth.throttle` string. If you touch that limiter, keep the response callback.
- **Registration has two separate consent checkboxes** (`age_confirmed`, `terms_accepted`) validated in `app/Actions/Fortify/CreateNewUser.php` — deliberately not one combined checkbox (bundled age+ToS consent is legally dicey in some jurisdictions). Both are `['accepted']`-validated only; neither is ever persisted (`User::create()` and `$fillable` both ignore them) and there's no corresponding `users` column.
- **`lang/{ru,en}/account.php` is new and app-owned** — login/register/reset page copy. Keep it separate from `auth.php`/`passwords.php`/`validation.php` (managed by `laravel-lang/common`, overwritten by `composer post-update-cmd` → `artisan lang:update`) and from `ui.php` (`<x-ui.*>` component-internal strings only, not page copy).
- **MoonShine and Fortify are fully separate auth systems.** MoonShine's `moonshine` guard/provider (→ `moonshine_users` table, `admin/*` routes) is injected at runtime by its own service provider; Fortify uses the stock `web` guard → `Domain\Auth\Models\User` (moved out of `app/Models`, which is now empty — see "Architecture" below). A `users` login grants no admin access and vice versa.

## Telegram login

Built on `defstudio/telegraph` v1.72 (bot API client, webhook handling, `telegraph_bots`/`telegraph_chats` tables) plus `laravel/socialite` + `socialiteproviders/telegram` (Login Widget signature verification only). Three **independent** entry points, deliberately not unified into one:

- **Guest login/registration, browser** — `/login`'s Telegram Login Widget → `GET auth/telegram/callback` → `App\Http\Controllers\Auth\TelegramLoginController::callback()`. On first-ever login (i.e. only inside `register()`, never on a repeat login of an existing chat) the `@username` Telegram supplied is also written to `Domain\Profile\Models\Profile::$telegram_url` as `https://t.me/{username}`, so a fresh Telegram-registered account arrives at `/account/profile` with that field already filled instead of blank. `@username` is optional on Telegram — no username means `telegram_url` is simply left null, same as any other registration path. The widget path pulls it from `$telegramUser->getRaw()['username']`, not `getNickname()` — `SocialiteProviders\Telegram\Provider::mapUserToObject()` (`vendor/socialiteproviders/telegram/Provider.php:88`) has `getNickname()` silently fall back to `first_name` when there's no real username, which would produce a bogus `t.me/<first name>` link; the raw payload either has the real key or doesn't have it at all. The Mini App path (below) doesn't have this problem — `WebAppInitData::$username` is already the untouched field.
- **Guest login/registration, Telegram Mini App** — the site is meant to run inside a Telegram Web App, where the Login Widget doesn't work (it renders in an `oauth.telegram.org` iframe and needs a full-page redirect to the domain set via BotFather `/setdomain`, which a Mini App webview either blocks or breaks the session over). `resources/js/telegram.js`'s `telegramAuth` Alpine component reads `window.Telegram.WebApp.initData` (SDK loaded globally in `components/layouts/app.blade.php`) and toggles which button `login.blade.php` shows: non-empty `initData` means Mini App, so `<x-ui.telegram-webapp-button>` (a plain POST form) is shown instead of `<x-ui.telegram-login-button>`. That form posts to `POST auth/telegram/webapp` → `TelegramLoginController::webapp()`, verified by `Domain\Telegram\Support\WebAppInitData::verify()` — **a different HMAC algorithm than the widget**: secret is `HMAC_SHA256(key: "WebAppData", data: bot_token)`, not `sha256(bot_token)`, so this couldn't reuse `socialiteproviders/telegram`. `callback()` and `webapp()` share a private `loginTelegramUser()` tail (find-or-create `TelegramChat` → find-or-register `User` → `Auth::login()`).
  - **Optional autologin, no button tap needed** — gated by `Infrastructure\Settings\SiteSettings::$telegram_autologin` (off by default, toggled on the MoonShine "Настройки сайта" page, `app/MoonShine/Pages/SiteSettingsPage.php`). When on, `<x-ui.telegram-autologin>` — a hidden form, mounted in `components/layouts/app.blade.php` itself rather than just on `/login`, so it fires on whichever page the Mini App happens to open — self-submits to the same `auth.telegram.webapp` route via the `telegramAutologin` Alpine component the moment `initData` is non-empty. It guards against a redirect loop (stale `initData` → 302 back to a page that immediately resubmits) with a `sessionStorage` one-shot flag, and skips entirely — leaving the visible button as the fallback — if `sessionStorage` throws (Safari private mode). The hidden form also carries `redirect_to` (the current request URI) so a mid-browse autologin lands back where the user was instead of `fortify.home`; `TelegramLoginController::safeRedirectTarget()` only accepts a same-origin path (`^/[^/\\]`, rejecting `//host` and `/\host` protocol-relative forms) before honoring it, since this value is attacker-reachable if the hidden form's markup is ever tampered with. The `<x-ui.telegram-webapp-button>` tap path never sends `redirect_to`, so its behavior (→ `fortify.home`) is unchanged.
- **Linking Telegram to an already-authenticated account** — `/account/profile` shows a `t.me/<bot>?start=<code>` deep link → user opens it in the Telegram app → Telegraph's own webhook route (`POST /telegraph/{token}/webhook`, package-registered) → `App\Telegraph\WebhookHandler::start()`.

The split isn't arbitrary: a bot can only message a user who has actually started a conversation with it, and a deep link always creates that conversation; the widget only does if the user grants `data-request-access="write"`. The widget also can't be used for linking at all — its callback URL's HMAC is computed over *every* query parameter except `hash`, so a `?intent=link`-style flag would break the signature. Guest login, conversely, can't go through the bot/webhook path — logging someone in requires an immediate redirect-with-session, which a Telegram chat can't provide.

**Identity lives in `telegraph_chats`, not on `users`.** `Domain\Telegram\Models\{TelegramBot,TelegramChat}` extend the package's own models (wired via `config('telegraph.models.*')`) and add exactly what the package doesn't have:
- `TelegramBot` adds a `username` column (the package only stores `token`/`name`; the real `@username` needs a live `getMe` call) and `current(): ?self` / `flushCache()` — `Cache::rememberForever` + hook-invalidated, the same pattern as `Domain\Catalog\Filters\FilterOptionsRegistry`/`CategoryRegistry`. `booted()` auto-fetches `username` via `TelegramBot::info()` whenever the token is set/changed, guarded off under `app()->runningUnitTests()` — this is what lets the stock, interactive `php artisan telegraph:new-bot` (asks only for token + name) remain the *only* way to provision a bot, no custom command needed.
- `TelegramChat` adds a `user()` `belongsTo` and a nullable `user_id` column (migration `2026_08_09_083518_create_telegraph_chats_table.php`, `nullOnDelete` + `unique(['user_id', 'telegraph_bot_id'])`). `Domain\Auth\Models\User::telegramChat(): HasOne` is the other side.
- **Both subclasses set `protected $table` explicitly** (`telegraph_bots`/`telegraph_chats`) — Eloquent's naming convention would otherwise guess `telegram_bots`/`telegram_chats` from the class names, which don't exist.
- The bot's token is **not** in `.env` — `telegraph_bots.token` is the only source of truth. `config('services.telegram.*')` (what `SocialiteProviders\Manager\Helpers\ConfigRetriever` reads) is populated **lazily, locally, only inside `TelegramLoginController::resolveTelegramUser()`** right before calling `Socialite::driver('telegram')->user()` — not eagerly in `AppServiceProvider::boot()`. That was tried first and broke every single test in the suite: `boot()` runs on every request/console bootstrap, including before `RefreshDatabase` has migrated the test DB, so an unconditional `TelegramBot::current()` query there throws on a table that doesn't exist yet. Same reasoning keeps `<x-ui.telegram-login-button>` reading `TelegramBot::current()?->username` directly instead of through `config()` — the config value only exists inside that one request path.

**A Telegram-only account has no email and no password** — `users.email`/`users.password` are nullable (`0001_01_01_000000_create_users_table.php`). Consequences, all load-bearing:
- `User::hasVerifiedEmail()` returns `true` when `email === null` — a free property check, not a `telegramChat()->exists()` query, because it's evaluated on *every* `verified`-guarded request. `MustVerifyEmail`, `Features::emailVerification()`, and `middleware('verified')` on `/account`, `/cart`, `/checkout` all stay exactly as they were — this override is the whole reason no custom middleware was needed. `telegramLinked()`/`canUnlinkTelegram()` *do* query the relation, but only run on the low-traffic `/account/profile` render and the unlink action.
- `User::sendEmailVerificationNotification()` no-ops on a null email — `TelegramLoginController::register()` still fires `Registered` (so `App\Listeners\CreateUserProfile` keeps owning post-registration setup), which triggers the framework's `SendEmailVerificationNotification`; without the guard that notifies a null address and really throws under `MAIL_MAILER=smtp`.
- `App\Actions\Fortify\UpdateUserProfileInformation`'s `email` rule is `Rule::requiredIf(fn () => $user->email !== null)` — required for normal accounts (can't be blanked out), optional for Telegram-only ones (nothing to require). An empty submission is also never written as `null` even when allowed through — it means "leave the field alone", not "clear it"; a verified user blanking their email would otherwise permanently bypass `verified` via the override above.
- `User::canUnlinkTelegram()` gates unlinking on the user still having both an email and a password; `TelegramLoginController::unlink()` `abort_unless`es on it — unlinking a password-less account would lock it out forever. The password section of `account/profile.blade.php` is hidden entirely when `password === null` (`UpdateUserPassword` needs `current_password`, and `Hash::check()` against a null hash is a `TypeError`, not a clean `false`). **A "set a password" flow for Telegram-only accounts does not exist yet** — until it does, those users can never unlink, and password reset can't reach them either (no email).

**Testing needs neither a real bot nor a tunnel.** `tests/Feature/Auth/TelegramLoginTest.php` creates its own `TelegramBot` row, signs Login Widget payloads with the same HMAC the driver expects, and POSTs synthetic Telegram `Update` payloads straight at `route('telegraph.webhook', ...)` — outbound bot replies are intercepted with `DefStudio\Telegraph\Facades\Telegraph::fake()`. `tests/Feature/Auth/TelegramWebAppLoginTest.php` does the same for the Mini App path, signing `initData` with the `WebAppData` HMAC variant instead. Exercising the real widget/webhook/Mini App end-to-end does need a public HTTPS domain (BotFather `/setdomain` for the widget, a reachable URL for `telegraph:set-webhook`, and a Mini App URL configured on the bot) — `APP_URL=http://localhost:8000` won't do; use a tunnel (`cloudflared`/`ngrok`) for that.

`spatie/laravel-csp` is installed but emits no header (no `config/csp.php`, no middleware registered). If CSP is ever switched on, allowlist `telegram.org`/`oauth.telegram.org` in `script-src`/`frame-src` or both the widget and the Mini App SDK die silently.

## Public pages & site chrome

Content pages (as opposed to auth screens) live in `resources/views/pages/*.blade.php`. `home` (the catalog) is served by `CatalogController::index()`, not `PageController` — that controller exists only for genuinely static pages (`about()` → `pages/about.blade.php`, route name `about`). Add new static pages as a `PageController` method the same way rather than inlining closures in `routes/web.php`.

- Wrap page content in `<x-layouts.site>`, not `<x-layouts.app>` directly — it adds `<x-layouts.header>`/`<x-layouts.footer>`/`<x-ui.mobile-nav>` and a sticky-footer flex column (`<main class="flex-1">`) around your slot.
- `<x-layouts.header>` is auth-state-aware (`@guest`/`@auth` against the `web` guard): shows Войти/Регистрация or the user's name + a logout form (`<x-ui.user-menu>` covers profile/addresses/favorites/logout). It also renders `<x-ui.nav-menu :items="$menu" />` (right-aligned, `hidden md:flex`, desktop-only) — the site's main navigation, sourced from CMS content (`Domain\Content\Models\{SiteMenu,SiteMenuItem}`, `key = 'main'`), not hardcoded links. `$menu` is a tree array built by `Domain\Content\Actions\Content\LoadSiteMenu` (a request-memoized singleton) and injected into `components.layouts.header` by a `View::composer(...)` in `AppServiceProvider::boot()` — every public page gets it this way, not by a controller passing a prop, so `LoadPublicPage`'s own `menu` key (used by `sections`-consuming pages) and the composer resolve to the same memoized tree instead of querying twice. `<x-ui.nav-menu>` renders one level of nesting as a `<x-ui.user-menu>`-style dropdown; deeper children are silently not shown. `<x-ui.mobile-nav>` (mobile-width navigation, `md:hidden`, mounted by `<x-layouts.site>`) is deliberately **not** wired to this CMS menu — it keeps its own hardcoded links (Каталог/Избранное/Корзина/Профиль/О нас) and does not stub links to routes that don't exist.
- Both header and footer switch from a stacked mobile layout to a single row at the `sm:` breakpoint using `space-y-*`/`sm:space-x-*` (margin-based) — **not** flex `gap`, per the legacy-browser constraint above. These were the first files in the project to use a responsive (`sm:`) prefix at all; if utilities you add don't show up in the built CSS, check `resources/**/*.blade.php` actually contains the literal class string (Tailwind's JIT purge needs it verbatim somewhere in the content globs) before suspecting a build/cache bug.
- `lang/{ru,en}/layout.php` is app-owned header/footer copy — same ownership rule as `account.php` (keep out of `laravel-lang`-managed files and out of `ui.php`).

## Private account area

Auth-gated (`middleware('auth')`), prefix `/account`, controllers in `app/Http/Controllers/Account/`. `ProfileController` serves one page (`/account/profile`) composed of three independent form sections — name+email via Fortify's own `PUT user-profile-information.update`, structured profile fields (see "Domain layer" below) via `PUT account.profile.update`, password via Fortify's `PUT user-password.update` — each with its own error bag so per-field `<x-ui.error>` shows the right message. `AddressController` is a standard resource controller (`account.addresses.*`) for `Domain\Profile\Models\Address`; ownership is checked inline (`abort_unless($address->user_id === $request->user()->id, 403)`), no Policy class — fine for one model, reconsider if a second resource needs the same pattern.

**All data-entry forms in this area use `<x-ui.form mode="ajax">`** (explicit product requirement, not the library's default). Two things had to be fixed in shared `<x-ui.*>` code to make that actually work — know these before touching either:
- `resources/js/ui.js`'s `uiForm.submit()` used to fall back to `response.url` when no `X-Redirect` header was present. That's wrong for any PUT-only JSON endpoint that returns a blank 200 with no `Location` (which is exactly what Fortify's profile/password update responses do under `wantsJson()`) — `response.url` then equals the form's own PUT-only action URL, and navigating there is a GET, which 405s. Fixed to only ever navigate on an explicit `X-Redirect` header; no header means "success, no navigation" (`status` becomes `'ok'`, shown inline via `x-show="status === 'ok'"`).
- `<x-ui.field>` and every `*-field` composite now take a `bag` prop (default `'default'`), threaded to `<x-ui.error :bag="$bag">`. Without it, the no-JS fallback (a real 302 redirect-back after a failed submit) could never show Fortify's `updateProfileInformation`/`updatePassword`-bagged errors — `<x-ui.error>` already supported `bag`, the `*-field` composites just never passed it through. Ajax-mode errors were never affected (JSON error bodies are flat regardless of bag name); this was a no-JS-only gap.

Ajax success has no `session('status')` to read (Fortify only sets that for non-JSON requests) — don't route account-page success messages through `<x-ui.status-alert>`. `Fortify::PROFILE_INFORMATION_UPDATED`/`PASSWORD_UPDATED` are also **untranslated raw constants** (`'profile-information-updated'`, `'password-updated'`) even on the no-JS path — `account/profile.blade.php` maps them to real copy itself rather than handing them to `<x-ui.status-alert>`, which would print the raw code verbatim.

`<x-ui.account-nav>` ports the design system's `.acc-nav` (brandbook §09) — text + counter only, no icons (there are none in the mockup) and no links to unbuilt features (orders/payments/favorites/promo codes), same "don't stub dead links" rule already applied to `<x-layouts.header>`.

## Domain layer (`Domain\`)

`Domain\` → `src/Domain/` groups Eloquent models by bounded concept, not framework-agnostic business logic:

- `Domain\Auth\Models\User` — the Fortify/`web`-guard user model, moved out of `app/Models` (now empty) into its own bounded concept.
- `Domain\Profile\Models\{Profile,Address}` — living outside `app/Models` because that's where this project chose to put "logic strongly tied to a bounded concept but not `User` itself." `Profile` is 1:1 with `User` (`user_profiles` table, `user_id` unique+cascade), created automatically by `App\Listeners\CreateUserProfile` on the `Illuminate\Auth\Events\Registered` event (relies on Laravel's default listener auto-discovery scanning `app/Listeners` — no explicit `Event::listen()` anywhere, verify with `php artisan event:list` if it ever seems not to fire). Every profile field is nullable — `$user->profile` can also be legitimately `null` (pre-listener users, factories that bypass `Registered`), so callers must be null-safe (`$user->profile?->first_name`, `updateOrCreate([], $data)` rather than `update($data)`). `Address` is 1:many, `Address::booted()`'s `saving` hook enforces "at most one default address per user" by unsetting `is_default` on the user's other addresses — not a DB constraint, deliberately (a partial unique index is disproportionate complexity for this).
- `Domain\Catalog\Models\{Product,Category,CategoryMatchRule,Manufacturer,BeerStyle,BeerProductDetail,Container,Volume,Property}` — the product catalog, flat schema (price/stock/volume/container live directly on `products`, no separate variants table). `Category`/`CategoryMatchRule` are the admin-editable registry consumed by the catalog import pipeline — see `src/Services/CatalogImport/README.md` for the full pipeline architecture and how to extend it; don't duplicate that detail here. `Domain\Catalog\Builders\ProductBuilder` is `Product`'s custom Eloquent query builder (wired via `Product::newEloquentBuilder()`) — all `Product` query scopes (`published()`, `inCategories()`, etc.) live there, not on the model, to keep it from growing unbounded. `Domain\Catalog\Filters\*` (`FilterManager` + `AbstractFilter` subclasses) drive the public catalog's filter UI on `home` — each filter both narrows a `ProductBuilder` query and renders its own Blade widget.
- `Domain\Untappd\Models\UntappdBeer` — synced beer metadata from the Untappd API (`Services\Untappd`).

## Architecture: PSR-4 layout beyond `app/`

Composer autoloads four extra namespaces out of `src/` (see `composer.json`):

- `Domain\` → `src/Domain/` — domain models (see "Domain layer" above)
- `Services\` → `src/Services/` — service classes: `Services\CatalogImport` (1С CSV import pipeline, see its own README), `Services\Untappd` (Untappd API client/DTOs/repositories)
- `Infrastructure\` → `src/Infrastructure/` — cross-cutting infra: `Infrastructure\Settings\*` (`spatie/laravel-settings` classes, e.g. `CatalogImportSettings`, `SiteSettings`), `Infrastructure\Ftp\Catalog1cFtpClient`, `Infrastructure\Jobs`, `Infrastructure\Rules` (custom validation rules)
- `Support\` → `src/Support/` — helpers (`src/Support/helpers.php` autoloaded globally via composer `files`) and `Support\Logging\Events` (typed events logged for observability, e.g. `CatalogImportCompleted`/`Failed`)

Standard Laravel dirs (`app/Http`, `app/Providers`) hold framework glue only; `app/Models` is empty (`User` lives in `Domain\Auth\Models` — see "Domain layer" above). `app/MoonShine/` holds MoonShine-specific classes:
- `app/MoonShine/Resources/<Name>/<Name>Resource.php` + `Pages/<Name>FormPage.php` + `Pages/<Name>IndexPage.php` — one folder per admin resource
- `app/MoonShine/Pages/` — standalone MoonShine pages (e.g. `Dashboard.php`, `CatalogImportSettingsPage.php`)
- `app/MoonShine/Layouts/MoonShineLayout.php` — admin panel layout

MoonShine resources/pages are auto-discovered (`MoonShineServiceProvider::boot()` calls `$core->autoload()` over the `App\MoonShine\*` namespace) — no manual `->resources([...])` registration needed; just add the class in the right folder.

## Commands

```bash
# Install & setup (first time)
composer setup

# Dev server: Laravel server + queue worker + log tailer + Vite, all at once
composer dev

# Tests (clears config cache first, then runs full suite)
composer test
# or directly:
php artisan test
php artisan test --filter=TestName        # single test
php artisan test tests/Feature/FooTest.php # single file

# Lint/format (Laravel Pint)
vendor/bin/pint
vendor/bin/pint --dirty   # only changed files

# Frontend
npm run dev      # vite dev
npm run build    # vite build

# Catalog import (1С CSV → products) — see src/Services/CatalogImport/README.md
php artisan catalog:import              # download from FTP + import
php artisan catalog:validate-registry   # check category registry invariants (also run as a pre-flight by catalog:import)
php artisan catalog:sync-seo            # bulk create/refresh product seo rows (see Domain\Catalog\Actions\SyncProductSeoAction)
```

## Skills available in this repo

Project-specific Claude Code skills are configured for: Laravel best practices, Blade+Alpine components, MoonShine components/fields/layouts/palettes, Fortify auth, Debugbar-based debugging, and spatie/laravel-sluggable. Prefer invoking these over ad-hoc approaches when working in their respective areas.
