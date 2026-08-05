# Laravel quality and performance

Use this checklist for correctness, Laravel-convention, structure and performance review. Check only applicable items and back conclusions with code or measurements.

## Contents

1. PHP and Laravel correctness
2. HTTP layer and validation
3. Eloquent and database
4. Architecture and maintainability
5. Queues, events and integrations
6. Performance
7. Testing

## 1. PHP and Laravel correctness

- Check parameter/return types, nullable states, enum/cast handling and date conversions.
- Check comparisons, operator precedence, truthy/falsey values, and handling of `0`, `"0"`, empty string and empty collection.
- Check branch reachability, early returns, exceptions and correct cleanup in `finally`.
- Check container bindings: singleton/scoped/transient, interface vs. implementation, circular dependencies.
- Check long-running worker/Octane lifecycle: no request/user state held in a singleton or static state between requests.
- Check config caching compatibility: no `env()` calls outside `config/*.php` files.
- Check model serialization in jobs/events and the risk of stale data after the job sits in the queue.
- Check locale, timezone and date-boundary handling.
- On Laravel 11+, check `bootstrap/app.php` for middleware registration/exception handling — a missing `app/Http/Kernel.php` is expected, not a regression; look there instead.
- Check whether strict-mode guards are enabled outside production: `Model::preventLazyLoading()`, `Model::preventSilentlyDiscardingAttributes()`, `Model::preventAccessingMissingAttributes()`, `Model::shouldBeStrict()`. Their absence is not itself a bug, but it removes a class of otherwise-silent defects — note it when relevant to another finding.

## 2. HTTP layer and validation

- Check HTTP methods, route model binding, required middleware and correct status codes.
- Check that a Form Request (or equivalent) validates every external input.
- Check nested arrays, `sometimes`, `nullable`, `present`, `required_if`, wildcard rules and length/size limits.
- Check input normalization before validation, and the distinction between a missing field and `null`.
- Check domain exception handling doesn't leak internal details.
- Check JSON contract stability, pagination metadata and API backward compatibility.
- Do not demand "thin controllers" as a goal in itself; extract an action/service only for reuse, complex orchestration, or a distinct business operation.
- Route caching (`route:cache`) breaks on closure routes — flag closure routes intended for production.
- Check `Route::apiResource` vs. `resource`: the former excludes `create`/`edit`, which matters for API-only vs. Blade-serving controllers.

## 3. Eloquent and database

- Cross-check `$fillable`/`$guarded`, casts and attributes against the migrations.
- Check PK/FK types, signed/unsigned, string lengths, precision/scale, nullable/default and collation.
- Check foreign keys, unique/check constraints and delete rules.
- Check the app doesn't rely on a prior `exists()` check alone where a DB-level uniqueness guarantee is required.
- Check transactions wrap multiple related writes; don't hold a transaction open during slow network calls.
- Check for lost updates, double-submit, race conditions, locking and idempotency.
- Check bulk update/delete: they bypass some model events and casts.
- Check soft deletes interacting with unique indexes, relationships and admin-panel queries.
- Check scopes for ambiguous column names after a join.
- Check raw expressions, select aliases, `group by` and cross-database portability.
- Check migration ordering and safe deploy with old and new code running concurrently.
- `updateOrCreate`/`firstOrCreate` are not atomic without a backing unique index — under concurrent requests they can both insert, then throw or duplicate; check the index exists or the call is behind a lock.
- Check for `withoutEvents`/`unguarded` blocks and `saveQuietly` calls — they skip observers/events; verify nothing downstream (audit log, cache invalidation, notification) depends on the events that are being skipped.

## 4. Architecture and maintainability

- Find business-rule duplication across controllers, jobs, commands and observers.
- Preserve dependency direction; domain rules must not depend on the HTTP representation.
- Do not introduce a repository/service/DTO layer without a concrete boundary or benefit.
- Check for a single source of truth for statuses, transitions and computed fields.
- Check observers and model events for hidden side effects, recursion and unexpected firing during imports.
- Check clarity of public contracts, names, return types and exceptions.
- Check that comments explain reasoning or a constraint, not restate the code.
- Do not flag taste differences as defects when they don't affect correctness or maintainability.

## 5. Queues, events and integrations

- Check retries, backoff, timeout, `retryUntil`, failed jobs and job uniqueness.
- Make handlers idempotent: a retried webhook/job must not duplicate a payment, email or record.
- Check `afterCommit` usage for events/jobs that need committed data.
- Check side-effect ordering and compensation for partially completed operations.
- Check HTTP client timeouts, retrying only safe requests, and a circuit breaker where warranted.
- Check response verification, external error mapping and protection against upstream contract changes.
- Do not log full payloads if they contain tokens or personal data.
- Check queue payload size and `SerializesModels` behavior: if the underlying model row is deleted between dispatch and processing, the job throws `ModelNotFoundException` on deserialization — check whether the handler is expected to tolerate that (`deleteWhenMissingModels`).

## 6. Performance

### Queries

- Find N+1 in loops, resources, accessors, policies, Blade and serialization — but prove it (see "Gathering evidence" in SKILL.md): a query-count assertion, `DB::listen`, or a profiler trace, not a visual guess from reading a loop.
- Check `with`, `withCount`, `loadMissing` and, where appropriate, disabling lazy loading to catch accidental N+1.
- Don't load whole models/collections when `exists`, `value`, `pluck`, an aggregate, or a limited `select` is enough.
- Use pagination; for large or mutating datasets, consider cursor pagination.
- For batch processing, consider `chunkById`, `lazyById` or a cursor, accounting for mutation of the underlying set during iteration.
- Check that filters, joins, foreign keys, sort columns and unique lookups are covered by an appropriate index.
- Judge a composite index by the actual order of conditions and sort columns used, not by creating one index per column.
- Use `EXPLAIN` on representative data before confidently claiming an index issue.
- Look for queries inside accessors, casts and policies that get invoked repeatedly (e.g., once per row in a collection).
- Blade `@each`, view composers, and API Resource `whenLoaded`/`toArray` are common N+1 sites that don't show up in a controller-only read — check them explicitly.

### Memory, CPU and I/O

- Check unbounded `get()`, `all()`, `toArray()` and loading large files into memory.
- Check repeated JSON decoding, image rendering and synchronous external requests.
- Move long-running work to a queue only with correct idempotency and a sensible waiting UX.
- Cache expensive, stable results; define key, TTL, invalidation and the tenant/user boundary.
- Do not cache authorization decisions or personal data without a correct context key.
- Treat OPcache/config/route/view caching as a deployment concern, not a fix for a slow algorithm.

### Frontend and responses

- Check JSON/HTML payload size, repeated serialization and unneeded fields sent to the client.
- Check eager loading of media and nested resources.
- Check Vite chunking, large dependencies and images only when there's a symptom or a metric.
- Separate server latency, DB time and client-side load time when diagnosing "slow".

## 7. Testing

- Check happy path, validation failures, unauthorized/forbidden, not-found and conflicting-state scenarios.
- Add boundary tests: `null`, empty, min/max, date-boundary crossing, repeat request and a concurrent scenario.
- Check database assertions and side effects, not just the HTTP status code.
- Check absence of N+1 via a query-count assertion or profiler when performance is part of the contract.
- Don't mock Eloquent or framework internals unnecessarily; fake external boundaries instead.
- For queues, events, notifications, HTTP and storage, use Laravel's built-in fakes deliberately and assert on the payload.
- Check that the test actually fails when the guard under test is removed.
- Check `RefreshDatabase` vs. `DatabaseTransactions`: `DatabaseTransactions` doesn't reset autoincrement IDs or schema between tests and won't roll back a queue/HTTP fake's side effects the way a fresh migration would — pick deliberately, don't default without checking which the suite already uses.
