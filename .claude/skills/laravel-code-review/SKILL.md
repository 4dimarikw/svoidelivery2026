---
name: laravel-code-review
description: "Evidence-based code review of Laravel/PHP projects and changes: bugs, regressions, Laravel convention violations, architecture, Eloquent/query and performance problems, plus web application security assessment. Use when reviewing Laravel code, a pull request or diff, hunting bugs and N+1 queries, or auditing authentication, authorization, validation, file uploads, APIs, Blade and production configuration. Laravel-specific — for Symfony use the `review` / `security-review` skills instead."
argument-hint: "[diff | branch | PR | file | directory | 'full audit']"
---

# Laravel Code Review

Review as an engineering check with reproducible evidence. Separate confirmed defects from assumptions and generic advice.

## Choose a review mode

- For a diff/PR review, first inspect the changed lines, then the calls, models, migrations, config and tests they touch. See "Scope selection by mode" below for the exact commands.
- For a file/component review, scope to the given code but trace critical inputs and outputs.
- For a full audit, map the application, then work through quality, performance and security in sequence.
- For an explanation/recommendation-only request, do not change code.
- Fix code only on explicit user request; after fixing, run the tests and describe residual risk.

For a comprehensive review, read all applicable references in full:

- [Quality and performance](references/quality-and-performance.md)
- [Web application security](references/security.md)
- [API security (OWASP API Security Top 10)](references/api-security.md) — read whenever the reviewed surface includes a JSON/token API, SPA/mobile backend or webhook receiver; skip for a Blade-only application. A full audit of an app with such a surface reads all three references.

For a narrow request, read only the relevant reference. Do not rely on the summary below instead of the matching checklist.

## Scope selection by mode

- **Diff/PR**: `git status` (uncommitted scope), `git diff --stat` and `git diff` (uncommitted), or `git log --oneline main..HEAD` and `git diff main...HEAD` (branch/PR scope — use the three-dot form so the diff is against the merge-base, not current `main`).
- **Single commit**: `git show <sha>` / `git diff <sha>^..<sha>`.
- **File/component**: read the target plus its direct callers and callees (`Grep` for the class/function name across the repo) before judging it in isolation.
- **Full audit**: build the surface map in "Build the surface map" below, then walk it in order — don't sample at random.

## 1. Establish context

1. Find and read applicable `CLAUDE.md`/`AGENTS.md` files and any nested per-directory instructions — check subdirectories too, not just the repo root.
2. Fix the review scope: whole project, current diff, branch, PR, file or subsystem.
3. Check `git status` without modifying or deleting user files.
4. Determine PHP, Laravel and key package versions from `composer.json` and `composer.lock`.
5. Identify the stack: Blade, Livewire, Inertia, API, queues, scheduler, admin panel, storage and external services.
6. Identify available quality/test commands from `composer.json` scripts, `package.json` scripts, `phpunit.xml`/`pest.php`, and CI config — detect what exists, don't assume a command is available (see §4).

Do not read or print the contents of `.env`, keys, tokens, cookies, production dumps or user data. For configuration, use `.env.example` and `config/*.php` files.

If behavior depends on the installed Laravel/package version, check documentation for that exact major/minor version — if a Context7-style docs tool is available, prefer it for library/framework API questions over relying on training data. For web searches, use primary sources: official docs, upstream repositories and vendor security advisories.

## 2. Build the surface map

For a full audit, investigate in this order:

1. `routes/`, middleware and service providers (Laravel 11+: check `bootstrap/app.php` for middleware/exception config — there is no `app/Http/Kernel.php` on current skeletons).
2. Controllers, Form Requests, actions/services and policies/gates.
3. Eloquent models, scopes, observers, casts and relationships.
4. Migrations, indexes, constraints and transaction boundaries.
5. Blade/Livewire/Inertia/JavaScript and every place user data is output.
6. Jobs, events, listeners, commands, scheduler, webhooks and integrations.
7. Auth, session, cookie, CORS, filesystem, queue, logging and production configuration.
8. Tests covering both happy and failure paths.

Use `Grep`/`Glob` for targeted search (ripgrep regex semantics; escape literal `{}` etc.). Never conclude from a filename or a single match alone — read the surrounding code and the execution path.

## 3. Verify changes and behavior

For each candidate defect:

1. Identify the attacker/user-controlled input and the reachable path to the risky operation.
2. Check existing validation, authorization, casts, middleware, database constraints and framework defaults.
3. Find the calling code and every meaningful call site.
4. Check handling of `null`, empty collections, repeat submission, concurrency and exceptions.
5. Cross-check the code against migrations and actual DB types/constraints.
6. Reproduce the issue where possible with a minimal test or a safe command.
7. Drop the finding if you cannot show a real failure scenario or material risk.

Do not run migrations, seeders, destructive commands, workers, external writes or production queries without explicit permission. Do not install new analyzers or update dependencies just for the review.

## Gathering evidence

Rank evidence from strongest to weakest and match your reported confidence to the strongest rung actually reached — don't claim "confirmed" on rung 5 evidence:

1. A minimal failing test (or one that fails before your fix and passes after).
2. A query log / query-count assertion / profiler output (`DB::listen`, Debugbar, Telescope, or a test's query-count assertion) showing the actual behavior.
3. `EXPLAIN` on representative data for a performance/index claim.
4. A traced code path with all guards checked (validation, middleware, policy, DB constraint) and shown to be absent or bypassable.
5. A reasoned argument from reading the code alone, with no execution — weakest; only for findings too cheap/risky to execute (e.g., would require a destructive command).

## 4. Run available checks

Pick commands from what the project actually has, don't assume a generic list applies. Read `composer.json`'s `scripts` and `require-dev`, and `package.json`'s `scripts`, before running anything. Commonly relevant, if present:

```text
git diff --check
composer validate --no-check-publish
php artisan test  (or vendor/bin/pest)
vendor/bin/pint --test
vendor/bin/phpstan analyse   # only if phpstan/larastan is in require-dev
npm test
npm run build
```

- Run only commands that exist and are safely configured.
- Prefer narrow tests of the affected subsystem first, then the full suite if the cost is reasonable.
- A successful build is not evidence of application correctness.
- Do not auto-fix formatting during a review.
- For dependency audits, use lockfile versions; only run network checks (`composer audit`, `npm audit`) if network access is available, and state exactly what was checked.

## 5. Classify findings

Prioritize by actual impact:

- **P0 — critical:** likely compromise, remote code execution, mass data leak or unrecoverable data loss.
- **P1 — high:** reachable vulnerability, authorization bypass, material data corruption or failure of a core flow.
- **P2 — medium:** confirmed bug, noticeable performance degradation, or a risk that requires specific conditions.
- **P3 — low:** limited defect or a concrete improvement with measurable benefit.

Do not inflate priority based on hypothetical scale. Mark hardening recommendations separately when they have no demonstrated exploit path.

## Not a finding

Do not report these as defects — they inflate the report and erode trust in the real findings:

- Mass assignment on a model whose `$fillable` is correct and is only ever fed `validated()` data — mass assignment is a finding only when attacker-controlled unvalidated keys can reach `create()`/`fill()`/`update()`.
- Missing explicit CSRF check on a `web`-group route — Laravel's `VerifyCsrfToken` middleware already covers it; check the middleware group, don't assume it's absent.
- "No MFA" / "no CSP" / "no rate limiting on an internal admin tool" reported as vulnerabilities rather than hardening — these need a stated threat model or product requirement to be more than P3/hardening.
- Style/taste differences (naming, file organization, thin-vs-fat controllers) with no correctness or maintainability impact.
- `SELECT *` or an "N+1" flagged without a query count, `EXPLAIN`, or profiler evidence — see "Gathering evidence".
- Absence of a security header checked at only one layer (app or reverse proxy) when the other layer wasn't inspected — see security reference §7.
- A `findOrFail`/ownership check that exists on the endpoint you're reading but you haven't checked whether it exists — verify, don't assume by pattern-matching against a different, unguarded endpoint.
- BOLA reported on a nested resource that already uses `Route::scopeBindings()` or an equivalent policy-scoped query — check the actual binding/query, not just whether an explicit ownership `if` is visually present.
- Missing rate limit reported on a single route that inherits `throttle:api` (or equivalent) from its route group — check the group's middleware stack, not just the route definition line.

## 6. Prepare the report

Lead with findings sorted by priority. Skeleton:

```text
## Findings

| # | Priority | Title | File:Lines |
|---|----------|-------|------------|
| 1 | P1 | <short title> | path/to/File.php:42-58 |
...

## [P1] <Short title>
File and minimal line range: path/to/File.php:42-58
Trigger condition and execution path: ...
Observed impact: ...
Why the current defense doesn't prevent it: ...
Fix direction: ...
Confidence: high / medium / low (state which evidence rung — see "Gathering evidence")

## Checks run
- <command> — <result>

## Not covered / residual risk
- <subsystem or scenario not reviewed, and why>
```

Requirements:

- Give a precise line reference for where the cause manifests, not the whole file.
- Merge duplicates sharing one root cause.
- Don't pad the report with style preferences that don't affect reliability.
- After the findings, list checks performed, limitations and areas not reviewed.
- If no confirmed findings exist, say so plainly and state residual review risk (what wasn't checked and why).
- Never claim the application is "secure" — scope the conclusion to the code, configuration and tests actually reviewed.

## 7. Recommend fixes

- Prefer built-in Laravel features and small, locally-scoped changes.
- Preserve public contracts and behavior unless the user asked to change them.
- For performance, confirm cost first via query, execution plan, profiling or an explicit complexity estimate.
- For security, fix the root cause, not just the symptom.
- Add a regression test that fails on the original implementation and passes after the fix.
- Separate mandatory fixes, optimizations and optional refactoring.

## Related skills

If these are available in the current environment, delegate rather than duplicate:

- Laravel idioms/conventions in depth → `laravel-best-practices`.
- Fortify auth internals (login, registration, password reset, email verification, 2FA) → `fortify-development`.
- Blade/Alpine component review → `laravel-blade-alpine-components`.
- MoonShine admin panel resources/fields/layouts → the relevant `moonshine-*` skill.
- Runtime profiling / N+1 confirmation via Debugbar → `debug-using-debugbar`.
- Version-specific framework/package API confirmation → a docs-lookup tool (e.g. Context7), preferred over relying on training data alone.
