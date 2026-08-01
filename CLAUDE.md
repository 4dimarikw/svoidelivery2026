# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

Laravel 12 + MoonShine 4 admin panel for **"Свои Delivery"** (alcohol delivery, Russian market). `src/Domain` and `src/Services` are still empty scaffolding. The public-facing frontend uses a token-driven Blade + Alpine component library (`<x-ui.*>`) — see "Frontend component library" below — and `laravel/fortify` now provides the auth backend, with views composed entirely from that library — see "Authentication (Fortify)" below. Treat existing structure as intentional scaffolding to build into, not legacy to work around.

## Stack

- PHP 8.2, Laravel 12, MySQL (dev, see `.env`), database-backed session/cache/queue
- MoonShine 4.18 — admin panel framework (Blade + Alpine, no Livewire)
- Laravel Socialite + `socialiteproviders/telegram` — Telegram OAuth login (installed, **not yet configured**: no `services.telegram`, no `SocialiteWasCalled` listener)
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

- **Enabled features** (`config/fortify.php`): `registration`, `resetPasswords`, `updateProfileInformation`, `updatePasswords`. The latter two are the `PUT /user/profile-information` and `PUT /user/password` endpoints consumed by the private account area — see "Private account area" below.
- **Deliberately deferred, with reasons:** email verification (`MAIL_MAILER=log`, no real mail configured); two-factor authentication (no product need yet — its migration was deleted after `fortify:install`, re-publish via `vendor:publish --tag=fortify-migrations` when enabling); passkeys (WebAuthn needs far newer browsers than this project's `Safari >= 13.1 / Chrome >= 80` target — same constraint that pins Tailwind to v3); phone/SMS login and Telegram Socialite login (`socialiteproviders/telegram` is installed but has zero config/listener wiring) — the login page has no buttons for either yet.
- **`Fortify::confirmPasswordView()` is mandatory, not optional.** With `views => true`, `GET /user/confirm-password` is registered unconditionally regardless of which features are enabled, and Fortify has no default `ConfirmPasswordViewResponse` binding — omitting the view callback means that route 500s. `auth/confirm-password.blade.php` exists solely for this.
- **`config('fortify.home')` is `'/account/profile'`** — read by `LoginResponse`, `RegisterResponse`, and `PasswordConfirmedResponse`. Note `PasswordResetResponse` redirects to `route('login')` instead, with `session('status')` — that's why `<x-ui.status-alert>` is on the login page, not just forgot-password.
- **The login rate limiter has a custom `->response()`** in `FortifyServiceProvider` — without it, a throttled login returns a bare Symfony 429 instead of the translated `auth.throttle` string. If you touch that limiter, keep the response callback.
- **Registration has two separate consent checkboxes** (`age_confirmed`, `terms_accepted`) validated in `app/Actions/Fortify/CreateNewUser.php` — deliberately not one combined checkbox (bundled age+ToS consent is legally dicey in some jurisdictions). Both are `['accepted']`-validated only; neither is ever persisted (`User::create()` and `$fillable` both ignore them) and there's no corresponding `users` column.
- **`lang/{ru,en}/account.php` is new and app-owned** — login/register/reset page copy. Keep it separate from `auth.php`/`passwords.php`/`validation.php` (managed by `laravel-lang/common`, overwritten by `composer post-update-cmd` → `artisan lang:update`) and from `ui.php` (`<x-ui.*>` component-internal strings only, not page copy).
- **MoonShine and Fortify are fully separate auth systems.** MoonShine's `moonshine` guard/provider (→ `moonshine_users` table, `admin/*` routes) is injected at runtime by its own service provider; Fortify uses the stock `web` guard → `App\Models\User`. A `users` login grants no admin access and vice versa.

## Public pages & site chrome

Content pages (as opposed to auth screens) live in `resources/views/pages/*.blade.php` and are served by `App\Http\Controllers\PageController` — one method per page (`home()` → `pages/home.blade.php`, route name `home`). Add new static pages the same way rather than inlining closures in `routes/web.php`.

- Wrap page content in `<x-layouts.site>`, not `<x-layouts.app>` directly — it adds `<x-layouts.header>`/`<x-layouts.footer>` and a sticky-footer flex column (`<main class="flex-1">`) around your slot.
- `<x-layouts.header>` is auth-state-aware (`@guest`/`@auth` against the `web` guard): shows Войти/Регистрация or the user's name + a logout form. It has no nav links yet — no catalog/account pages exist to link to; add them once those routes exist rather than stubbing dead links.
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

First real use of the `Domain\` → `src/Domain/` PSR-4 namespace (previously empty scaffolding). `Domain\Profile\Models\{Profile,Address}` — Eloquent models, not framework-agnostic business logic, living outside `app/Models` because that's where this project chose to put "logic strongly tied to a bounded concept but not `User` itself." `Profile` is 1:1 with `User` (`user_profiles` table, `user_id` unique+cascade), created automatically by `App\Listeners\CreateUserProfile` on the `Illuminate\Auth\Events\Registered` event (relies on Laravel's default listener auto-discovery scanning `app/Listeners` — no explicit `Event::listen()` anywhere, verify with `php artisan event:list` if it ever seems not to fire). Every profile field is nullable — `$user->profile` can also be legitimately `null` (pre-listener users, factories that bypass `Registered`), so callers must be null-safe (`$user->profile?->first_name`, `updateOrCreate([], $data)` rather than `update($data)`).

`Address` is 1:many, `Address::booted()`'s `saving` hook enforces "at most one default address per user" by unsetting `is_default` on the user's other addresses — not a DB constraint, deliberately (a partial unique index is disproportionate complexity for this).

## Architecture: PSR-4 layout beyond `app/`

Composer autoloads three extra namespaces out of `src/` (see `composer.json`):

- `Domain\` → `src/Domain/` — domain/business logic; `Domain\Profile\Models\{Profile,Address}` (see "Domain layer" above)
- `Services\` → `src/Services/` — service classes (empty so far)
- `Support\` → `src/Support/` — helpers; `src/Support/helpers.php` is autoloaded globally via composer `files`

Standard Laravel dirs (`app/Http`, `app/Models`, `app/Providers`) hold framework glue only. `app/MoonShine/` holds MoonShine-specific classes:
- `app/MoonShine/Resources/<Name>/<Name>Resource.php` + `Pages/<Name>FormPage.php` + `Pages/<Name>IndexPage.php` — one folder per admin resource
- `app/MoonShine/Pages/` — standalone MoonShine pages (e.g. `Dashboard.php`)
- `app/MoonShine/Layouts/MoonShineLayout.php` — admin panel layout

New MoonShine resources must be registered in `App\Providers\MoonShineServiceProvider::boot()`'s `->resources([...])` call — they are not auto-discovered.

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
```

## Skills available in this repo

Project-specific Claude Code skills are configured for: Laravel best practices, Blade+Alpine components, MoonShine components/fields/layouts/palettes, Fortify auth, Debugbar-based debugging, and spatie/laravel-sluggable. Prefer invoking these over ad-hoc approaches when working in their respective areas.
