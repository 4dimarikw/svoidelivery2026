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
- Layouts are `resources/views/components/layouts/{app,auth}.blade.php`, used as `<x-layouts.app>` / `<x-layouts.auth>`.
- `tailwind.config.js`'s `borderRadius`/`boxShadow` **redefine** Tailwind's stock scale (`rounded-md` = 3px, not 6px; `shadow-md` is teal-tinted). This is deliberate brand styling, not a mistake.
- Form controls (`<x-ui.input>`, `<x-ui.select>`, `<x-ui.checkbox>`, …) accept a value both the classic Blade way (`value`/`old()`) and via Alpine `x-model`. Under `x-model` the component rewrites it to `x-model.fill` and still emits the server value so `old()` keeps working — see the header comment in `input.blade.php` before changing this. Checkbox/radio can't use `.fill` (Alpine can't bridge booleans that way); those need state seeded from PHP via `<x-ui.form :state="[...]">`.
- `@aware()` is used for exactly one key across the whole library: `mode` on `<x-ui.form>`. Never `@aware(['name'])` — it walks the *entire* ancestor component stack, not just the immediate parent, and would silently pick up a `name` from any unrelated ancestor.
- `/ui-kit` (local-only route) renders every component/variant/state and three live forms (`default`/`enhance`/`ajax` mode) — the way to verify component changes without Fortify installed.

## Authentication (Fortify)

`laravel/fortify` v1.x, Blade mode (`config('fortify.views')` = `true`) — the `<x-ui.*>` library is the frontend, there is no Breeze/Jetstream/Livewire. Views live in `resources/views/auth/{login,register,forgot-password,reset-password,confirm-password}.blade.php`, wired via explicit `Fortify::*View()` closures in `app/Providers/FortifyServiceProvider.php`, and are composed entirely from existing `<x-ui.*>`/`<x-layouts.*>` components — no component file needed to change for this.

- **Enabled features** (`config/fortify.php`): `registration`, `resetPasswords`, `updateProfileInformation`, `updatePasswords`. The latter two are live `PUT` endpoints (`/user/profile-information`, `/user/password`) with **no UI yet** — nothing calls them until an account/settings page is built.
- **Deliberately deferred, with reasons:** email verification (`MAIL_MAILER=log`, no real mail configured); two-factor authentication (no product need yet — its migration was deleted after `fortify:install`, re-publish via `vendor:publish --tag=fortify-migrations` when enabling); passkeys (WebAuthn needs far newer browsers than this project's `Safari >= 13.1 / Chrome >= 80` target — same constraint that pins Tailwind to v3); phone/SMS login and Telegram Socialite login (`socialiteproviders/telegram` is installed but has zero config/listener wiring) — the login page has no buttons for either yet.
- **`Fortify::confirmPasswordView()` is mandatory, not optional.** With `views => true`, `GET /user/confirm-password` is registered unconditionally regardless of which features are enabled, and Fortify has no default `ConfirmPasswordViewResponse` binding — omitting the view callback means that route 500s. `auth/confirm-password.blade.php` exists solely for this.
- **`config('fortify.home')` is `'/'`** (the stock welcome page) as a placeholder — no dashboard/account page exists yet. Update it once one does; it's read by `LoginResponse`, `RegisterResponse`, and `PasswordConfirmedResponse`. Note `PasswordResetResponse` redirects to `route('login')` instead, with `session('status')` — that's why `<x-ui.status-alert>` is on the login page, not just forgot-password.
- **The login rate limiter has a custom `->response()`** in `FortifyServiceProvider` — without it, a throttled login returns a bare Symfony 429 instead of the translated `auth.throttle` string. If you touch that limiter, keep the response callback.
- **Registration has two separate consent checkboxes** (`age_confirmed`, `terms_accepted`) validated in `app/Actions/Fortify/CreateNewUser.php` — deliberately not one combined checkbox (bundled age+ToS consent is legally dicey in some jurisdictions). Both are `['accepted']`-validated only; neither is ever persisted (`User::create()` and `$fillable` both ignore them) and there's no corresponding `users` column.
- **`lang/{ru,en}/account.php` is new and app-owned** — login/register/reset page copy. Keep it separate from `auth.php`/`passwords.php`/`validation.php` (managed by `laravel-lang/common`, overwritten by `composer post-update-cmd` → `artisan lang:update`) and from `ui.php` (`<x-ui.*>` component-internal strings only, not page copy).
- **MoonShine and Fortify are fully separate auth systems.** MoonShine's `moonshine` guard/provider (→ `moonshine_users` table, `admin/*` routes) is injected at runtime by its own service provider; Fortify uses the stock `web` guard → `App\Models\User`. A `users` login grants no admin access and vice versa.

## Architecture: PSR-4 layout beyond `app/`

Composer autoloads three extra namespaces out of `src/` (see `composer.json`):

- `Domain\` → `src/Domain/` — domain/business logic (empty so far)
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
