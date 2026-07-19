# ADR 016: Cache policy before cache mechanism

Status: accepted

## Context

Caching can reduce latency and authoritative-source load, but a generic cache call hides consequential differences. HTTP caches reuse response messages under protocol rules, while server-side data caches reuse application values under backend-specific expiry, eviction, locking, and failure behavior. APCu process storage, Redis, filesystems, and distributed services are not operationally interchangeable.

A broad key/value interface also cannot decide what is safe to cache, how tenants are isolated, when source writes invalidate derived values, whether stale data is acceptable, or how concurrent cold misses are bounded. PSR-6 and PSR-16 provide useful library interoperability but intentionally permit generic values and leave those application decisions open. A callback convenience can hide lock scope; `apcu_entry()`, for example, holds an exclusive cache-wide lock while its loader runs.

PHPThis has not yet gathered cache evidence from a production-shaped consumer. Adding a framework API before that evidence would create a second source of truth for application behavior and spend core budget on an abstraction selected by anticipation.

## Decision

PHPThis accepts cache policy before any framework cache mechanism and treats two concerns separately.

HTTP response caching is application-owned protocol behavior. The policy target is an explicit decision for every emitted response path, including success, failure, redirect, and not-found paths. Personalized, session-affecting, authenticated, and sensitive responses begin with `Cache-Control: private, no-store` unless an accepted application decision proves another policy. Every storable response requires finite freshness or revalidation and reviewed stale behavior; public caching additionally requires safe cross-user reuse, correct representation variance, validator semantics when used, and deployed intermediary invalidation evidence. `Set-Cookie` is never treated as a cache prohibition.

Server-side data caching remains an application-owned optimization over derived, reproducible data. Its first proof uses one narrowly named typed service, one backend-specific cache-aside path, bounded and versioned environment- and tenant-scoped keys, bounded strictly parsed payloads, finite TTL, authoritative-write-first invalidation, an explicit stale-refill policy, explicit failure behavior, and concurrency evidence. It excludes credentials, sessions, CSRF tokens, authentication and permission data, secrets, and authorization decisions. A cache miss or eviction remains correct.

Handlers do not receive a generic cache, PSR pool, PSR simple-cache interface, global helper, facade, arbitrary key/value bag, or `remember` callback. An application may place an intentionally selected PSR implementation behind its typed service, but the application's domain-specific contract owns all policy and PHPThis does not require that dependency.

PHPThis adds `.ai/cache.md`, `docs/caching.md`, application-context decision fields, and this record. Framework-owned 404, 405, and unknown-failure 500 responses, plus every current skeleton and example response path, explicitly emit `Cache-Control: no-store`. This is a narrow visible policy for paths PHPThis owns, not automatic rewriting of arbitrary handler responses. PHPThis adds no cache client or backend dependency, generic cache API, conditional-request middleware, cache backend certification, Strict Profile rule, consumer-checker rule, or Consumer Contract version change before Alpha.

The first backend-specific application proof occurs after Alpha. It must test hits, misses, finite expiry, early eviction, malformed values, backend failure, tenant isolation, write invalidation and its failure, a miss racing a committed write, concurrent cold misses, and cold-cache query scaling. A warm cache cannot satisfy database query-scaling evidence.

## Consequences

An AI can distinguish accepted cache policy from a nonexistent framework mechanism. Cache I/O, authoritative reads, invalidation, lock scope, and fallback remain visible in application code and human-approved context. Session state and cache data remain separate concepts.

Applications repeat small typed cache-aside services rather than sharing a premature universal abstraction. This can cost more application code initially, but it exposes which semantics are actually stable across backends and workloads. Applications that do not adopt server-side caching may record `NOT_APPLICABLE(CACHE)` without weakening their HTTP response decisions.

HTTP caching remains possible through ordinary explicit response headers. PHPThis now proves `no-store` on its current framework-owned 404, 405, and unknown-failure 500 paths and on every current skeleton/example response path, but it does not enforce completeness or correctness for new application responses. Each application still owns success, mapped failure, redirect, personalized, validator, and `Vary` decisions that fall outside those explicit paths. Server-side cache observability, backend availability, capacity, eviction, stampede control, and stale-data risk remain application and deployment responsibilities.

## References

- [RFC 9111: HTTP Caching](https://www.rfc-editor.org/rfc/rfc9111.html)
- [RFC 9110: HTTP Semantics](https://www.rfc-editor.org/rfc/rfc9110.html)
- [PSR-6: Caching Interface](https://www.php-fig.org/psr/psr-6/)
- [PSR-16: Common Interface for Caching Libraries](https://www.php-fig.org/psr/psr-16/)
- [PHP manual: `apcu_entry`](https://www.php.net/manual/en/function.apcu-entry.php)
- [Redis key eviction](https://redis.io/docs/latest/develop/reference/eviction/)

## Reconsider when

At least two independent applications prove the same smaller typed boundary across real workloads, including failure, invalidation, isolation, capacity, and concurrency evidence; or a required HTTP policy cannot remain explicit through ordinary responses. Reconsider one narrow evidence-backed mechanism, not a generic API that erases backend or domain semantics.
