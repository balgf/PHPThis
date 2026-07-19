# Cache policy contract

PHPThis accepts cache policy before it accepts a cache mechanism. HTTP response caching and server-side data caching are separate concerns. The framework currently provides no generic cache API, cache adapter, automatic response policy, or cache-backed correctness guarantee.

## HTTP response caching

- Record an explicit intended cache policy for every emitted response path, including success, mapped and unknown failure, redirect, and not-found paths. Add ordinary response headers where the application controls the `Response`; the current `UnknownFailureBoundary` does not inject a policy, so record that unenforced path instead of claiming complete coverage. Do not rely on status-code defaults, a session cookie, `Set-Cookie`, or intermediary configuration that is absent from the application record.
- Begin personalized, session-affecting, authenticated, and sensitive responses with `Cache-Control: private, no-store` unless an accepted application decision proves another policy.
- Any storable response, including a private response, needs an explicit finite freshness or revalidation policy. Use `public` only when the representation is safe for reuse across users; also record every request dimension that changes it, the exact `Vary` fields, validator semantics when used, and intermediary invalidation ownership.
- Treat `no-cache` as permission to store with mandatory revalidation, not as a synonym for `no-store`.
- Keep conditional-request handling explicit. An `ETag` or `Last-Modified` value identifies the selected representation; it is not a decorative performance header.
- Test every application-controlled success, error, redirect, authenticated, anonymous, and cookie-emitting response that can expose different policy. The current framework does not enforce those headers mechanically.

## Server-side data caching

- Cache only derived, reproducible application data. The authoritative database or external system remains the source of truth, and every miss or eviction must be correct.
- Put one narrowly named typed application service around one recorded backend-specific cache path, for example `UserSummaryCache`. Do not expose a generic key/value cache, global helper, facade, `remember` callback, or application-wide PSR interface to handlers.
- Keep cache-aside I/O visible: construct a bounded versioned key, read, distinguish hit from miss and backend failure, parse a bounded payload into a typed projection, perform the explicit source read on a miss, and write with a finite TTL.
- Commit authoritative writes first, then explicitly invalidate the finite affected keys. Also account for a stale refill: an in-flight miss can read old authoritative data, a writer can commit and invalidate, and that miss can then repopulate the old value. Record an accepted staleness bound or a backend-specific fence, revision, or compare-and-set mitigation, plus what the application does when invalidation fails. TTL is a bound on intended freshness, not an invalidation strategy or an availability guarantee.
- Treat cached values as untrusted external input. Reject malformed, oversized, wrong-version, and wrong-tenant payloads as the recorded failure policy requires. Do not deserialize arbitrary PHP objects.
- Include application identity, environment, tenant or ownership scope, domain purpose, schema version, and bounded unambiguously encoded identity components in the key design. Do not put credentials, secrets, session identifiers, or raw sensitive values in keys. Sensitive derived payload fields require an explicit classification and backend protection decision.
- Do not cache credentials, session state, CSRF tokens, or authorization decisions. The first application proof also excludes authentication and permission data.
- Record backend version, topology, connection ownership, eviction behavior, value and key bounds, TTL, invalidation, failure and fallback behavior, stampede ownership, observability, and deployment capacity before adoption. `NOT_APPLICABLE(CACHE)` means no server-side data cache; it does not waive HTTP response policy.
- Test hit, miss, expiry, malformed payload, backend outage, tenant isolation, invalidation success and failure, eviction, concurrent cold misses, and a concurrent miss racing an authoritative write. Prove database query count with a cold or bypassed cache so a warm cache cannot conceal N+1 behavior.

PSR-6 or PSR-16 may be an application-selected transport dependency behind the typed service, but neither is the PHPThis application API. Their generic values, key operations, TTL defaults, and failure semantics do not decide domain typing, invalidation, tenancy, stampede control, or production topology.

Do not put database, network, filesystem, or other fallible work inside a cache callback whose lock scope is not explicit and measured. Backend-specific atomic loaders and locks require concurrency evidence; for example, `apcu_entry()` holds an exclusive cache-wide lock while its callback runs.

See [Cache policy](../docs/caching.md) and [ADR 016](../docs/decisions/016-cache-policy-before-cache-mechanism.md). A reusable runtime mechanism is reconsidered only after independent application evidence, not added in anticipation of it.
