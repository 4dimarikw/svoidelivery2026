# Laravel web application security

Use this checklist for code-based security review. Distinguish a confirmed vulnerability from a conditional risk and from hardening. Don't treat this checklist as a substitute for a full penetration test.

For a JSON/token API, SPA/mobile backend or webhook receiver, pair this file with [api-security.md](api-security.md) (OWASP API Security Top 10) — §6 links to it at the point it becomes relevant.

## Contents

1. Threat model and attack surface
2. Authentication and sessions
3. Authorization and object access
4. Input, injection and output
5. Files, URLs and external services
6. API, webhooks and abuse
7. Secrets, configuration and infrastructure
8. Dependencies and supply chain
9. Cryptography and sensitive data
10. Rules for writing up findings

## 1. Threat model and attack surface

- Enumerate public, authenticated, admin, API, webhook and debug routes.
- Identify assets: accounts, personal data, payments, orders, files, admin operations and integration secrets.
- Trace trust boundaries from request to DB, filesystem, queue and external service.
- Account for multi-tenant boundaries, roles, resource ownership and impersonation.
- Check unexpected entry points: commands exposed via panels, signed URLs, previews, exports, callbacks and queued payloads.

## 2. Authentication and sessions

- Check the guard/provider for each user type and for the admin panel.
- Check password hashing, reset flow, verification, remember-token, logout and session invalidation.
- Check rate limiting and enumeration risk on login, reset, OTP, verification and invitation endpoints — including response-shape/timing differences that reveal whether an account exists (distinct error message, different response time, different HTTP status).
- Check password policy: `Password::defaults()` (or equivalent) sets a real minimum length, ideally `uncompromised()` (breach-list check), and no silent truncation before hashing. An absent/weak policy is the finding — don't just note "passwords could be stronger" without pointing at the missing `Password::defaults()` rule.
- Check lockout/backoff after repeated failed login attempts, distinct from the rate limiter on the route itself.
- Check MFA/recovery flow, if present.
- Check session fixation after login/privilege change and CSRF/session regeneration.
- Check cookie `secure`, `http_only`, `same_site`, domain and lifetime in a production context.
- Check the `EncryptCookies` middleware's except-list — any cookie name added there is no longer encrypted/authenticated by the framework; confirm nothing sensitive was added without justification.
- Check session store isolation in a multi-tenant or shared-cache setup — a cache-driver session store must key by more than a guessable ID if the cache is shared across tenants.
- Do not treat missing MFA as a vulnerability without a stated requirement or threat model; file it as hardening.

## 3. Authorization and object access

- For every mutating and sensitive endpoint, check middleware plus policy/gate/ownership.
- Do not treat authentication as authorization: a user must not be able to read or modify another user's object by ID.
- Check nested resources: the child model must be verified to belong to the parent named in the URL.
- Check `authorizeResource`, implicit binding, scoped bindings and manual `findOrFail` calls.
- Check bulk operations, export, download, search/autocomplete and other indirect endpoints.
- Check admin actions, bulk actions, jobs and commands: UI hiding does not substitute for server-side authorization.
- Check mass assignment separately from authorization; `$fillable` doesn't restrict *who* may change a field, only *which* fields are assignable at all.
- An inline `abort_unless($resource->user_id === $request->user()->id, 403)` check is an acceptable pattern for a single model — when you find it, check every sibling endpoint (index/show/update/destroy, and any admin-panel or API duplicate route) repeats the same check rather than assuming one instance implies the rest are covered.

## 4. Input, injection and output

### Injection

- **SQL injection**: check `DB::raw`, `selectRaw`, `whereRaw`, `orderByRaw`, `havingRaw` and string-concatenated SQL.
- Check dynamic column names, sort direction and table names against an allowlist — bindings protect values, not identifiers.
- Check user-supplied `LIKE` wildcards if they change search semantics or enable an expensive query (e.g., unanchored `%term%` on an unindexed large column).
- **Command injection**: check for `shell_exec`, `exec`, `system`, `passthru`, `proc_open`, `popen`, backticks, and Laravel's `Process::run()`/`Process::start()` called with a single interpolated string instead of an argument array — never build a command string by concatenating user input regardless of which sink is used.
- **LDAP injection**: check `ldap_search`/`ldap_bind` filters built by string concatenation from user input; require `ldap_escape()` (or equivalent) on any value entering an LDAP filter.
- **XPath injection**: check dynamic XPath expressions built from user input (XML processors, SAML/SOAP integrations); require parameterized queries or strict input validation, since XPath has no native binding mechanism.

### XSS and HTML/JS

Three variants, each needing a distinct check:

- **Reflected XSS**: user input echoed back in the response (search results, error messages, redirect targets) without escaping.
- **Stored XSS**: database content rendered without escaping — includes admin-panel display of attacker-supplied data (product descriptions, user-submitted fields, imported CSV content): a trusted admin can still open attacker-controlled data.
- **DOM XSS**: client-side JavaScript writing untrusted data into the DOM — this is the one Blade's server-side escaping cannot prevent, so check it separately. Sinks: `innerHTML`, `outerHTML`, `insertAdjacentHTML`, `document.write`, `eval`, `location.href`/`location.assign`, jQuery `.html()`, Alpine `x-html`.

Checks applying across all three:

- Find `{!! !!}` (Blade's equivalent of Twig's `|raw` filter — same risk, same scrutiny), `HtmlString`, `html_safe`, and any user-supplied Markdown/HTML rendering.
- Check context-appropriate encoding for HTML, attributes, URL, CSS and JavaScript — these are different encodings and one does not substitute for another.
- Unquoted HTML attributes are exploitable even with `{{ }}`-escaped content (e.g. `<div class={{ $value }}>` instead of `class="{{ $value }}"`) — Blade's `e()` escaping assumes a quoted attribute context; check attributes are actually quoted.
- For passing data into JavaScript, prefer `Js::from`/`@js` over manual JSON concatenation.
- Check `@verbatim` blocks — content inside is not escaped by Blade and needs the same scrutiny as `{!! !!}`.
- If Livewire is in use, treat public component properties bound from the client as user input needing the same validation/authorization scrutiny as a request field.
- Check CSP as a defense-in-depth layer, not a replacement for escaping.

### Commands, templates and serialization

- Check shell/process calls: never build a command string by concatenating user input.
- Check dynamic Blade view selection, mail template, filesystem path or class name resolution from user input.
- Never call `unserialize()` on untrusted data.
- Check CSV/formula injection on export for values starting with `=`, `+`, `-`, `@`.
- Check header injection, open redirect and CRLF injection in user-controlled URLs/headers.

## 5. Files, URLs and external services

### Upload and download

- Check size, MIME, extension, actual format and decoding of images/archives.
- Store untrusted files outside any executable/public path, or serve them through controlled storage with authorization.
- Generate the server-side filename; never trust the original filename or path.
- Check path traversal, symlink handling, ZIP slip, archive/decompression bombs and deletion of another user's files.
- Check authorization for downloads and temporary/signed URLs.
- Set a safe `Content-Type`/`Content-Disposition`; don't let an uploaded HTML file execute in the application's own origin.
- A validated `$request->file()` (extension/MIME check passed) is not proven-safe content — the declared MIME type is client-supplied metadata; for images, re-encoding (not just format-sniffing) is the reliable defense against a polyglot file.
- Media/upload packages (e.g. a media-library integration) often default to a **public** disk — confirm the configured disk visibility matches the intended access level before assuming uploads are private.

### SSRF and URLs

- Check `Http::*`, `file_get_contents`, image/PDF processors and webhooks wherever the URL is user-supplied.
- Restrict scheme/host/port; account for redirects, DNS rebinding, IPv4/IPv6 and private/link-local addresses.
- Do not treat blocking the literal string `localhost` as sufficient protection.
- Set connect/read timeouts and a response size limit.

## 6. API, webhooks and abuse

- Check Sanctum/Passport/token scopes and the separation of cookie-based vs. token-based auth.
- Check CORS is scoped to specific origins/methods/headers; a wildcard origin with credentials enabled is never acceptable.
- Check CSRF protection for cookie-authenticated requests; a webhook route may be excluded only alongside signature verification.
- Check webhook signature verification against the raw body, a timestamp/replay window, and constant-time comparison.
- Check idempotency of payment, order and callback processing.
- Limit request body size, JSON nesting depth, pagination limits and expensive filters.
- Check rate limits key on an appropriate actor/IP/token, accounting for trusted proxies.
- Do not trust `X-Forwarded-*` headers without correctly configured proxy trust (`TrustProxies` / `trustProxies()`).
- Check CSRF on the form/session side: every non-GET Blade form includes `@csrf`; AJAX/fetch requests send `X-CSRF-TOKEN` (from the meta tag) or rely on `X-XSRF-TOKEN` via the `XSRF-TOKEN` encrypted cookie that Laravel's `VerifyCsrfToken`/Sanctum stateful middleware reads automatically; any route excluded from CSRF verification (`VerifyCsrfToken::$except` or middleware exclusion) is justified only alongside its own signature/authentication check (e.g. a webhook).
- If the reviewed surface is a JSON/token API, SPA/mobile backend or webhook receiver, also walk [api-security.md](api-security.md) — the OWASP API Security Top 10 checklist — in addition to this section.

## 7. Secrets, configuration and infrastructure

- Look for committed keys, tokens, private keys, credentials and real data in fixtures/logs; don't print a found secret's value in full.
- Check `APP_DEBUG=false`, production environment settings, and absence of debug routes/tools in production.
- Check `APP_KEY` is stable, unique per environment, and not committed to the repository.
- Check default or seeded credentials/API keys (from a seeder, factory or fixture) don't reach a non-local environment.
- Check dev-only packages (Debugbar, Telescope, IDE Helper, and similar) are declared in `require-dev`, not `require` — a `require` entry ships them (and their routes) to production regardless of whether the route itself is gated.
- Check HTTPS enforcement, trusted hosts/proxies, secure cookies and HSTS, accounting for a reverse proxy in front of the app.
- Check filesystem visibility, the `public` storage symlink, and access to backup/export/log files.
- Check queue/cache permissions and tenant-specific cache key isolation.
- Check log redaction for passwords, tokens, authorization headers, cookies, payment data and PII.
- Check error responses for stack traces, SQL, filesystem paths and external credentials leaking to the client.
- Check CSP, frame-ancestors, MIME-sniffing and referrer-policy at **both** the application layer (e.g. a CSP package) and the reverse-proxy layer before reporting either as missing — a header set by one and not inspected at the other produces a false "absent" finding.

## 8. Dependencies and supply chain

- Use `composer.lock` and the frontend lockfile for actually-installed versions, not the version constraint in the manifest.
- Check Composer/NPM advisories with the project's own audit tooling (`composer audit`, `npm audit`) without auto-updating.
- Check for abandoned packages, unsupported major versions, and direct use of a known-vulnerable API surface.
- Do not declare the app vulnerable from a manifest constraint alone: confirm the locked version and the advisory's actual applicability (is the vulnerable code path even reached?).
- Check install/update scripts and unfamiliar package repositories only when there's a specific reason to.
- Recommend the minimal safe update separately from any feature upgrade.

## 9. Cryptography and sensitive data

- For passwords, use Laravel's `Hash` facade — never reversible encryption, never a fast general-purpose hash.
- For values needing confidentiality, use Laravel's `Crypt` facade (modern authenticated encryption) — never a hand-rolled scheme.
- Do not use deterministic encryption or a shared hash for secret tokens without an explicit analysis of the lookup requirement driving that choice.
- Generate tokens with a cryptographically strong source; store sensitive one-time tokens hashed at rest, and give them a TTL and single-use enforcement — this applies to password-reset and invite tokens specifically, not only API keys.
- Check the distinction between signing and encryption: a signed URL (`URL::signedRoute`) proves the URL wasn't tampered with, it does not hide its contents or the resource ID inside it. Also check its expiry (`temporarySignedRoute`) is actually enforced where the route is sensitive.
- Minimize personal data in events, queues, logs, analytics and URLs.
- Check access control, retention and deletion of sensitive data.

## 10. Rules for writing up findings

A security finding must state:

1. The attacker-controlled input.
2. The reachable path to the dangerous operation.
3. The missing or bypassable defense.
4. The concrete impact and privilege level required.
5. A minimal safe fix and how to regression-test it.

Never publish a working destructive exploit, and never target a real external system. To substantiate a finding, use a safe local test or a minimal non-destructive proof.

Lower confidence or phrase as a question when the reverse proxy, WAF, production environment or external identity provider configuration is unknown. Do not conflate absence of evidence of a vulnerability with evidence of its absence.
