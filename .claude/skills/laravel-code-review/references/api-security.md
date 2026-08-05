# API security — OWASP API Security Top 10 (2023)

Use this checklist whenever the reviewed surface includes a JSON/token-authenticated API, a mobile or SPA backend, or a webhook receiver. Skip it for a Blade-only application with no such surface. It's a companion to [security.md](security.md), not a replacement — cross-references point back there instead of duplicating content.

## API1:2023 — Broken Object Level Authorization (BOLA)

An endpoint resolves an object by an ID the client supplies, but only checks the requester is authenticated, not that they own or may access that specific object.

- Check every route param/body field that reaches `find()`, `findOrFail()`, `firstWhere()` or a repository lookup: is there a policy/gate call or an ownership-scoped query (`->where('user_id', $request->user()->id)`), not just a null check.
- Implicit route-model binding only *resolves* the model — it is not authorization. `Route::scopeBindings()` (or manual nested-ownership checks) is required for nested resources (`/orders/{order}/items/{item}` — `item` must belong to `order`).
- Check API Resources and admin/export endpoints too — BOLA isn't only on `show`/`update`/`destroy`.
- Evidence bar: reproduce with a second authenticated user's ID and show the response is 200/data instead of 403/404 — a grep for a missing policy call is a lead, not a finding on its own.

## API2:2023 — Broken Authentication

Weaknesses in how identity is established or a token/session is kept valid.

- Guard/provider correctness per route group (cross-ref [security.md](security.md) §2).
- Token lifecycle for Sanctum/Passport: expiry set, tokens revoked on logout and on password change, refresh flow doesn't silently extend a compromised token indefinitely.
- Password policy: `Password::defaults()` configured with a real minimum, `uncompromised()` (breach-list check) where justified, no silent truncation before hashing. Absence of a policy is the finding here, not a generic "weak passwords" note.
- Rate limiting and account enumeration on login/reset/OTP/invitation endpoints — cross-ref [security.md](security.md) §2 rather than re-deriving it.
- Check the token is transmitted via the `Authorization` header or a secure cookie, never as a URL query parameter (query strings land in logs, browser history, referrer headers).

## API3:2023 — Broken Object Property Level Authorization (BOPLA)

Merges the old "excessive data exposure" and "mass assignment" categories — both directions of the same problem: which *fields* of an object a given caller may write or read.

- **Write direction**: check `$fillable`/`$guarded` against what's actually reachable — `$request->all()` (or a full `validated()` array with no field allowlist) passed into `fill()`/`update()`/`create()` lets the caller set fields the endpoint never intended to expose, e.g. `role`, `is_admin`, `balance`, `status` on their own record.
- **Read direction**: check `$model->toArray()` / a hand-rolled array response / an over-inclusive API Resource for fields that shouldn't reach this caller — `email`, password/reset-token columns, internal flags, another tenant's nested relations. `$hidden` is a serialization default, not an authorization boundary — a route that explicitly selects or merges attributes can still leak a `$hidden` field.
- Check per-role/per-caller field visibility is enforced server-side (a conditional in the Resource, or a separate Resource class per role), not left to the frontend to not-render a field.

## API4:2023 — Unrestricted Resource Consumption

No limits on how much of the server's (or a paid upstream's) resources a single caller can consume.

- `throttle` middleware presence *and* its actual key — per-user vs per-IP, and whether it works correctly behind a reverse proxy (cross-ref `TrustProxies` in [security.md](security.md) §6).
- Client-controlled `per_page`/`limit`/`page_size` with no server-side cap — a caller requesting 100000 can force a full-table load.
- Unbounded `include`/`with`/`expand` query parameters letting the caller force arbitrary eager-loading depth.
- Expensive sort/filter parameters with no allowlist (cross-ref [security.md](security.md) §4 SQL/injection).
- Upload size limits, and array/batch endpoints with no cap on item count per request.
- Amplification: one request fanning out into N outbound emails/SMS/queue jobs/external API calls — the request is cheap for the caller, expensive for the app or its bill.

## API5:2023 — Broken Function Level Authorization (BFLA)

The caller can reach an operation (usually admin/privileged) their role shouldn't permit, as opposed to API1's "wrong object."

- Check role/permission gates on admin-only endpoints — authentication alone is not authorization here either.
- Check verb-level gaps: `GET /resource/{id}` is protected but `DELETE`/`PATCH` on the same route was added later and missed the same middleware/policy.
- Check route groups: a `middleware(['auth', 'can:admin'])` group is safe only if every admin route is actually declared inside it — a route accidentally defined outside the group (or after a `Route::group` closure was closed too early) silently loses the check.
- Check generated CRUD scaffolding and admin-panel resources for the same per-action authorization the hand-written controllers have.

## API6:2023 — Unrestricted Access to Sensitive Business Flows

Not a traditional bug — the flow works exactly as coded, but nothing stops it from being automated/abused at scale.

- Typical flows to check: coupon/promo redemption, referral/invite credit, checkout/order placement, password-reset email dispatch, review/rating submission.
- Check for idempotency keys on repeatable actions, per-actor quotas/cooldowns, and CAPTCHA/step-up friction where the product already uses it elsewhere.
- Check cost asymmetry: is the action cheap for an attacker (one HTTP call) but expensive for the app (an email, an SMS, a discount, a queued job)?
- Report as a business-risk finding: state the concrete abuse scenario (what an attacker gains, at what cost, at what scale) — this category has no CVE-shaped "missing check", so the finding must justify itself with the scenario, not a code snippet alone.

## API7:2023 — Server Side Request Forgery (SSRF)

Full checklist lives in [security.md](security.md) §5 ("SSRF and URLs") — apply it. API-specific entry points to check first: user-supplied webhook callback/target URL, avatar/image-by-URL import, PDF/HTML/screenshot renderers given a URL, and any field where an upstream URL is taken from request input rather than server config.

## API8:2023 — Security Misconfiguration

- CORS: wildcard origin combined with credentials enabled — never acceptable (cross-ref [security.md](security.md) §6).
- Verbose JSON error responses: confirm `APP_DEBUG=false` in the actual deployed config, not just `.env.example` — a debug-mode API returns stack traces, file paths and query text as JSON to any caller.
- Missing method restrictions — an endpoint responding to unintended HTTP verbs because the route isn't scoped to the ones it should handle.
- Default/seeded credentials or API keys reaching a non-local environment (seeders, factories, fixtures wired into a real deploy).
- Debug/profiler routes reachable in production: `/telescope`, `/horizon`, `/_debugbar`, `/_ignition`, a stray `phpinfo()` route — cross-ref [security.md](security.md) §7.
- Dev-only packages (Debugbar, Telescope, IDE Helper) declared in `require` instead of `require-dev` — they ship to production even when their routes are gated, and widen the dependency attack surface unnecessarily.

## API9:2023 — Improper Inventory Management

Vulnerabilities live in the API surface nobody remembers exists.

- Enumerate what's actually registered — `php artisan route:list` (or `--json` for parsing) if runnable — rather than trusting documentation or the obvious route file.
- Look for a leftover `v1` still mounted alongside `v2`: old versions rarely receive the same fixes the current one got.
- Look for staging/internal-only routes shipped to production, undocumented endpoints, unauthenticated health/debug endpoints, and routes registered by a package's own service provider that the team doesn't actively track.
- Check that every environment (staging, prod) is actually running the same route set that was reviewed — a route deliberately disabled in prod config is a different finding than one nobody thought to check.

## API10:2023 — Unsafe Consumption of APIs

Symmetric to SSRF/injection, but about *trusting the response* of an API this app calls, not just the request to it.

- Upstream JSON responses fed straight into `fill()`/`create()`/mass-assignment without the same validation applied to user input — an upstream integration is still an untrusted input source.
- Redirects followed blindly from an upstream response (compounds with SSRF if the redirect target is then itself fetched).
- Missing timeout and response-size caps on outbound HTTP clients (cross-ref [security.md](security.md) §5 SSRF timeouts).
- Upstream error bodies rendered directly to the end user — can leak the upstream's internal details or become a reflected-content vector.
- Webhook payloads processed (side effects triggered) before signature verification completes — verification must gate processing, not run in parallel with it or after.

## Mapping back to security.md

| API Top 10 item | Primary detail lives in |
|---|---|
| API1 BOLA | This file; general ownership pattern in [security.md](security.md) §3 |
| API2 Broken authentication | [security.md](security.md) §2 |
| API3 BOPLA | This file; mass assignment note in [security.md](security.md) §3 |
| API4 Unrestricted resource consumption | This file; rate limits in [security.md](security.md) §6 |
| API5 BFLA | [security.md](security.md) §3 |
| API6 Sensitive business flows | This file only |
| API7 SSRF | [security.md](security.md) §5 |
| API8 Security misconfiguration | [security.md](security.md) §7 |
| API9 Improper inventory | This file only |
| API10 Unsafe API consumption | [security.md](security.md) §5 (SSRF/outbound) |

Don't re-report the same root cause under both an API-item heading and its `security.md` counterpart — cite whichever section carries the fuller detail and note the API-item label alongside it.
