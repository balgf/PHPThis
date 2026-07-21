# Cache policy

PHPThis has an accepted cache policy but no framework cache mechanism. This distinction keeps correctness, I/O, locking, consistency, and topology visible while applications gather evidence from real workloads.

Caching has two separate execution paths:

1. HTTP caches reuse complete response messages under HTTP semantics.
2. Server-side data caches reuse derived application values before an authoritative read.

An application may permit HTTP reuse, adopt a server-side data cache, do both, or do neither; the choice to prohibit HTTP reuse is still explicit. The two paths do not share a key model, invalidation path, trust boundary, or failure policy.

## HTTP response caching

HTTP responses can be stored or reused even when application code did not intend caching. Several status codes are heuristically cacheable, and `Set-Cookie` does not inhibit caching. The policy target is an explicit decision for every emitted response path rather than an implicit default, including success, mapped and unknown failure, redirect, and not-found paths.

The initial safe policy for personalized, session-affecting, authenticated, and sensitive responses is:

```text
Cache-Control: private, no-store
```

`private` prevents shared-cache storage while allowing private-cache storage under its own constraints; `no-store` instructs both private and shared caches not to store the request or response. Neither directive encrypts content or compensates for an untrusted cache. `no-cache` has different semantics: a cache may store the response but must successfully validate it before reuse.

Every storable response, including one restricted to a private cache, records a finite freshness or revalidation policy and any permitted stale behavior. Public response caching is an additional explicit application decision. Before emitting `public`, the application records:

- why the selected representation is safe to reuse across users and tenants;
- one finite `max-age` and, when applicable, separately justified shared-cache `s-maxage`;
- every request header that can change the representation and the exact corresponding `Vary` fields;
- how `ETag` or `Last-Modified` identifies the selected representation and how conditional requests are evaluated;
- whether stale responses may be served and under which reviewed directive;
- which deployment component owns purge or invalidation and its failure behavior;
- policy for successful, error, redirect, authenticated, anonymous, and cookie-emitting responses.

`Vary` expands the cache key only by the named request fields. It does not repair a response that is already unsafe to share, and `Vary: *` makes later reuse indeterminate rather than defining a useful key. Validators distinguish selected representations; a strong entity tag must change when the representation changes and must distinguish negotiated encodings where required.

PHPThis adds no response-cache helper, general automatic default, conditional-request middleware, or static enforcement. Its framework-owned 404 and 405 responses include `no-store`, while the generic unknown-failure 500 uses `private, no-store`; every current skeleton and example response path includes at least `no-store`, and protected example outcomes use `private`. Applications use ordinary explicit response headers where they control the `Response` and prove their deployed proxy, CDN, and browser behavior. PHPThis does not inject or replace policy on an arbitrary handler response, so new success, mapped failure, redirect, personalized, validator, and `Vary` behavior remains application-owned. A future framework boundary must not silently change existing response policy.

## Server-side data caching

A server-side data cache is an application-owned optimization over derived, reproducible data. It is never the source of truth. Correct behavior must survive a miss, eviction, expiry, malformed entry, cold deployment, or unavailable cache backend.

The accepted shape is one narrowly named typed service for one domain projection, backed by one explicit backend-specific implementation. A handler might depend on `UserSummaryCache`, not a generic `Cache`, `CacheInterface`, global helper, facade, or arbitrary key/value bag. The typed service owns its key schema, payload parser, TTL, invalidation, and failure behavior.

The cache-aside read path remains visible:

1. Build one bounded, versioned key from already-validated typed values.
2. Read the configured backend and distinguish a hit, miss, malformed value, and backend failure.
3. Parse a hit through a named bounded projection factory before use.
4. On a miss, execute the explicit authoritative query or integration call.
5. Validate the resulting projection, write its bounded payload with one finite TTL, and return it.

Do not replace this path with a `remember` callback or another shorthand that hides backend I/O, source I/O, lock scope, or error conversion. Atomic loader behavior is backend-specific. PHP's `apcu_entry()`, for example, executes its callback while holding an exclusive cache-wide lock, so placing a database or network read inside that callback changes concurrency for every APCu operation.

The write path commits authoritative state first and then explicitly invalidates the finite affected keys. That ordering still has a stale-refill race: a miss can read old authoritative data, a writer can commit and invalidate, and the in-flight miss can then repopulate the old value. The application records either an accepted staleness bound or a backend-specific mitigation such as an authoritative revision in the key, a generation fence, or compare-and-set behavior, and tests the exact ordering it relies on. It also records the deterministic response when invalidation fails. A cache write cannot make a failed authoritative write successful, and a TTL is not a substitute for invalidation. Eviction can remove an entry before its TTL; Redis exposes policies that evict keys under memory pressure independently of expiration.

## Keys and values

Each adopted cache records one bounded key grammar containing:

- application identity and environment;
- tenant or other ownership scope where applicable;
- domain purpose and payload-schema version;
- bounded unambiguously encoded identity components.

Keys exclude credentials, secrets, session identifiers, CSRF tokens, and raw sensitive values. Payloads exclude credentials, secrets, session identifiers, CSRF tokens, and authorization decisions. Sensitive derived payload fields require an explicit classification plus backend access, transport, retention, and deletion controls. The first application proof also excludes authentication and permission data. Hashing a secret does not automatically make it suitable cache-key material.

Cached payloads are untrusted external values. They have explicit byte and collection bounds, a schema version, one canonical encoding when the application records it, and one strict parser into a final typed projection. Malformed, oversized, unknown-version, wrong-owner, duplicate-field, or otherwise non-canonical data follows the recorded miss or failure policy and never crosses into domain code as an unchecked array or object. Arbitrary PHP object serialization and deserialization are prohibited.

PSR-6 and PSR-16 are interoperability interfaces, not the PHPThis application contract. They permit generic PHP values and leave domain keying, invalidation, tenancy, topology, stampede behavior, and observability to callers and implementations. An application may deliberately choose a conforming library behind its narrowly named typed service, but handlers do not receive the PSR interface and PHPThis does not require either package.

## Application decision and evidence

Before adopting a server-side cache, the accountable human records in application-owned context:

- the measured workload and latency or capacity problem;
- backend product and version, process or network topology, and connection ownership;
- authoritative source and the exact cache-aside call path;
- typed service ownership, key grammar, payload schema, and byte or collection limits;
- finite TTL, invalidation triggers, stale-refill race policy, acceptable stale window, and eviction assumptions;
- backend-failure, malformed-entry, and invalidation-failure behavior;
- stampede prevention owner, lock scope, timeout, and recovery behavior;
- tenant isolation, sensitive-data exclusions, capacity limits, and operational cleanup;
- bounded hit, miss, write, failure, latency, and eviction observability;
- tests and production measurements supporting the decision.

An application with no server-side data cache records `NOT_APPLICABLE(CACHE)` in the relevant application context. That marker does not waive explicit HTTP response policy. This policy record added no consumer-checker rule when accepted under Consumer Contract version 3; version 4 carries that unchanged cache-policy boundary forward.

Evidence includes:

- hit and miss behavior with identical typed results;
- finite expiry and early eviction;
- malformed, oversized, and wrong-version payload rejection;
- cache backend outage and recovery;
- tenant and environment key isolation;
- authoritative write followed by invalidation, including invalidation failure;
- a concurrent miss racing an authoritative write, proving the accepted staleness bound or mitigation;
- concurrent cold misses and the measured stampede bound;
- cold-cache or cache-bypassed database query scaling and query traces;
- response-header behavior through the actual proxy or CDN when HTTP caching is adopted.

The current Redis proof additionally exercises authoritative-source failure after cache-read evidence, backend rejection of a refill without changing the selected SQLite value, committed SQLite state before invalidation, rejected-write non-invalidation, and recovery to the newer authoritative value after an accepted stale refill expires.

A warm cache is not evidence that a database path avoids N+1 queries. Database query budgets and scale tests remain valid with the cache cold or bypassed. Cache instrumentation is separately bounded and must not emit credentials, keys containing sensitive values, complete payloads, or one log line per backend operation.

## Unsupported boundary

PHPThis currently ships no generic cache API, cache item or pool, adapter, backend dependency, automatic query cache, response-cache middleware, distributed lock, tag invalidation, stampede helper, or cache-specific runtime instrumentation. APCu, Redis, filesystem caches, local memory, and distributed stores have materially different lifetimes, locks, eviction, failure, and deployment semantics; one abstraction must not imply that they are interchangeable.

ADR 028 now records the first real application proof: one tenant-scoped Redis document cache paired with a separate Redis schedule lease. The cache path remains after current authorization, requires its canonical bounded JSON encoding, falls back to SQLite, commits before invalidation, and accepts one finite stale-refill window. The executable proof uses distinct Redis processes at cache `127.0.0.1:6379/0` and lease `127.0.0.1:6380/0` by default. The lease process uses `noeviction` and owner-checked `SET NX PX`, renewal, and release operations without claiming fencing or exactly-once behavior. See [Redis cache and schedule coordination](redis-coordination.md).

That evidence remains application-owned. Promotion into the framework is reconsidered only after at least two independent applications demonstrate the same smaller stable boundary with measured failure and concurrency behavior.

## References

- [RFC 9111: HTTP Caching](https://www.rfc-editor.org/rfc/rfc9111.html)
- [RFC 9110: HTTP Semantics](https://www.rfc-editor.org/rfc/rfc9110.html)
- [PSR-6: Caching Interface](https://www.php-fig.org/psr/psr-6/)
- [PSR-16: Common Interface for Caching Libraries](https://www.php-fig.org/psr/psr-16/)
- [PHP manual: `apcu_entry`](https://www.php.net/manual/en/function.apcu-entry.php)
- [Redis key eviction](https://redis.io/docs/latest/develop/reference/eviction/)
