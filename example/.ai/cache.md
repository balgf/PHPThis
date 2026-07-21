# Example Redis cache and schedule-lease context

The executable example follows ADR 028 as one application-owned Redis proof. It adds no PHPThis cache, Redis, lock, or lease API and makes no production-readiness claim.

## Protected document cache

- Cache owner: `RedisDocumentDetailsCache` for the bounded document-details projection used by the protected document Get operation. The cache excludes not-found results, credentials, principals, memberships, permission data, denials, session state, secrets, and authorization decisions.
- Request order: authenticate, resolve the requested account to a tenant, authorize the current principal, then consult Redis. Every hit therefore follows current request policy; Redis never grants access.
- Authority: SQLite remains authoritative. Missing, expired, evicted, malformed, wrong-version, wrong-tenant, and unavailable entries each execute the ordinary bounded SQLite read once. A cache write failure does not alter that selected result.
- Key: exactly `phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>`, built only from validated values. The complete runtime key is not logged.
- Value: canonical JSON schema version `1` with exactly `schema_version`, `tenant_account_id`, `document_key`, and `title` in that order; the encoded payload is at most 1,024 bytes and the non-empty valid-UTF-8 title at most 512 bytes. The parser re-encodes the ordered shape with `JSON_UNESCAPED_SLASHES` and requires byte equality, so duplicate fields, alternate ordering or whitespace, and other non-canonical encodings are corruption. It is parsed as untrusted input into the concrete projection. PHP serialization and arbitrary objects are forbidden.
- Lifetime: 30 seconds. The cache endpoint may evict earlier; eviction is a miss.
- Mutation: `RedisInvalidatingDocumentTitleUpdate` executes one authoritative SQLite autocommit update and then invalidates the exact affected key. Its typed outcome exposes the database and invalidation results. Invalidation failure leaves the committed update authoritative and records a failure.
- Stale refill: an in-flight miss may write an older value after a concurrent commit and invalidation. That representation may remain for at most 30 seconds from refill. The example does not claim read-after-write consistency, coalescing, revision fencing, or compare-and-set refill.
- Stampede: the proof does not add a loader lock or coalescing. Concurrent cold callers may each perform the one bounded authoritative read under the recorded request-capacity limit.

## Redis schedule lease

- Scope: only the existing `schedule:run` pass. The direct `jobs:run-one` command and the SQLite worker keep their existing behavior.
- Owner: `RedisScheduleRunLease`.
- Key: exactly `phpthis_example:<environment>:schedule_run:v1`.
- Value: one fresh 128-bit lowercase-hex owner token.
- Acquisition: one nonblocking `SET key token NX PX 30000` operation. Contention returns `overlap_skipped` without job work.
- Renewal and release: one fixed Lua script each, permitted only when the stored token matches. A stale owner cannot renew or delete a later owner's value.
- Timing: connect and read timeout are each 250 milliseconds, and the phpredis maximum-retries option is zero. The lease TTL is 30 seconds, and one invocation performs at most four explicit renewals.
- Failure: connection, acquisition, renewal, or release failure produces the generic redacted `command_failed` result. There is no retry, wait, polling, background heartbeat, or renewal loop.
- Crash behavior: process termination leaves the key until Redis expiry. Later acquisition is permitted after expiry; work from the terminated or paused owner is not fenced by Redis.
- Topology: the cache defaults to `127.0.0.1:6379`, logical database `0`; the single-primary `noeviction` lease defaults to `127.0.0.1:6380`, logical database `0`. They are distinct Redis processes. A logical database number on the cache process is insufficient separation.

## Correctness and unsupported claims

The Redis lease is overlap coordination, not durable-job correctness. `Example\Jobs\SqliteUserWelcomeJobWorker` retains its own SQLite claim token, expiry checks, explicit transactions, and idempotent effect under ADR 024. If the Redis lease expires during work, scheduled passes may overlap and SQLite must still preserve its documented at-least-once behavior.

The example does not prove a fencing token, exactly-once execution, mutual exclusion through a network partition, Redis restart persistence, failover or replication safety, Cluster or Sentinel behavior, managed-service semantics, production clock or pause bounds, or cross-region coordination. Repository evidence declares `ext-redis ^6.3` and Redis server `>=7.4` and `<9.0`; other client or server versions remain unproved. Endpoints, timeouts, TTL, capacity, eviction policy, security controls, and dated integration evidence remain explicit deployment-owned facts.

## Observability and evidence

`DocumentDetailsCacheTrace` records finite read, write, and invalidation outcomes. Request-summary schema version `2` contributes its bounded `document_cache` snapshot to the existing terminal sink attempt. Every schedule success emits `coordination` after `command` and `outcome`; `not_due` uses `[]`, contention uses `["connected","contended"]`, and the demonstrated owned pass uses `["connected","acquired","renewed","released"]`. Redis operational failure emits one stderr object with `error: command_failed` and the bounded list. Neither channel includes complete keys, payloads, owner tokens, endpoints, credentials, exception messages, raw replies, SQL, bindings, request values, or domain values. No per-operation log write, second request sink, or durable-delivery claim is added.

Source is under `example/`; behavior and integration evidence is under `tests/`. Tests cover hit, miss, expiry, eviction, canonical-encoding rejection, backend outage, source and cache-write failure, tenant, environment, and endpoint isolation, authoritative commit before invalidation, rejected-write non-invalidation, invalidation outage, stale-refill expiry recovery, concurrent cold misses, cold-cache query scaling, contention, deterministic and real stale owners, deterministic and real server rejection, real renewal, safe release, process termination, TTL recovery, exact coordination output, redaction, and real Redis integration. See `docs/redis-coordination.md` for the reusable evidence boundary.

Forbidden in this proof: framework core cache or lease types, a generic cache interface or bag, a generic Redis client wrapper, transparent method caching, a remember callback, automatic object serialization, hidden retry, automatic renewal, lock discovery, service location, global helpers, middleware caching, and a claim that another backend shares these semantics.
