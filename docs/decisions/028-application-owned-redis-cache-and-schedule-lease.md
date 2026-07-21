# ADR 028: Application-owned Redis cache and schedule lease

Status: accepted

## Context

ADR 016 accepted cache policy before mechanism and required the first server-side proof to remain application-owned, domain-specific, and backend-specific. ADR 025 then accepted a same-host file lock for one scheduled pass while explicitly deferring multi-host coordination. The executable example now needs evidence for one cache-aside read model and one cooperating multi-process schedule lease without pretending that cache availability, Redis availability, or a lease establishes authoritative correctness.

Redis can provide the exact operations needed for this proof, but its cache and lease roles have different failure requirements. A cache endpoint may evict derived data without changing correctness. A lease key that disappears early may permit overlapping work, so placing it in an eviction-capable cache topology would silently weaken even its limited coordination claim. Redis expiry, process failure, restart, failover, replication, partitions, and client timeouts also prevent a lease from being treated as an ownership fence or an exactly-once guarantee.

The protected document read used by the proof already has an explicit `authenticate -> resolve tenant -> authorize -> handler` order. Caching that authorization decision, or allowing a cache hit before those policies complete, would turn a performance mechanism into hidden security state.

## Decision

PHPThis accepts one application-owned Redis proof in the executable example and adds no framework cache, Redis, lock, or lease API.

### Cache-aside read

`RedisDocumentDetailsCache` caches one bounded document-details projection only after authentication, tenant resolution, and current authorization complete. It never caches credentials, principals, memberships, permissions, authorization decisions, denials, or not-found results. The authoritative SQLite read remains correct and available when the cache entry is absent, expired, evicted, malformed, wrong-version, wrong-tenant, or unavailable.

The exact key grammar is `phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>`, built only from validated bounded application values. Its JSON schema version is `1`, its only fields in canonical order are `schema_version`, `tenant_account_id`, `document_key`, and `title`, and its maximum encoded size is 1,024 bytes. The title is non-empty valid UTF-8 of at most 512 bytes. A hit must repeat the requested tenant and document identities and is parsed as untrusted external input into the concrete document projection before use. The parser re-encodes that exact ordered shape with `JSON_UNESCAPED_SLASHES` and rejects the value unless the bytes match, so duplicated fields, alternate field order, insignificant whitespace, and other non-canonical encodings are corruption. No unknown field, PHP object serialization, or arbitrary value bag is accepted. The example TTL is 30 seconds.

`RedisInvalidatingDocumentTitleUpdate` performs one authoritative SQLite autocommit title update and only then explicitly invalidates the finite affected cache key. Its typed outcome keeps both the database result and invalidation result visible. Cache invalidation cannot participate in or roll back the SQLite statement. An invalidation failure is observable but does not rewrite the committed result. The proof accepts the ordinary stale-refill race and bounds the resulting stale representation by the same 30-second cache TTL: an in-flight miss may repopulate an older committed value after another process commits and invalidates. There is no miss coalescing, revision fence, or compare-and-set refill. This is an explicit bounded-staleness decision, not a claim of read-after-write consistency.

Cache misses, corruption, eviction, and Redis outage fall back once to the authoritative read without an implicit retry. The phpredis maximum-retries option is explicitly zero for both application Redis clients. Cache write or invalidation failure does not change the selected authoritative result. The cache Redis endpoint may use a documented eviction policy because every entry is reproducible derived data.

### Distributed schedule lease

`RedisScheduleRunLease` replaces the same-host schedule file lock for the existing `schedule:run` pass. Its exact key grammar is `phpthis_example:<environment>:schedule_run:v1`, and its complete value is one fresh 128-bit lowercase-hex owner token. Acquisition is one Redis `SET key token NX PX 30000` operation. Renewal and release each execute one fixed code-owned Lua script that changes or deletes the key only when the stored token equals the caller's token.

The executable proof defaults the cache role to `127.0.0.1:6379`, logical database `0`, and the lease role to `127.0.0.1:6380`, logical database `0`. They are distinct Redis processes. The lease process is one recorded single-primary topology using `noeviction`; the cache process is deliberately eviction-capable. A Redis logical database number alone is not accepted as separation because memory limits, eviction, process restart, and failure domain remain shared. Both connect and read timeout are 250 milliseconds, the lease TTL is 30 seconds, and one command invocation may perform at most four explicit renewals. The command has no automatic renewal and must fail closed if it cannot preserve the recorded renewal schedule.

Ordinary acquisition contention returns the existing expected `overlap_skipped` schedule outcome. Every successful `schedule:run` JSON line includes a bounded `coordination` list after `command` and `outcome`; `not_due` uses an empty list, contention records `connected` and `contended`, and an ordinary owned pass records connection, acquisition, renewal, and release. Connection, acquisition, renewal, or release failure fails the scheduled command closed with `error: command_failed` plus the finite `coordination` list. There is no hidden retry, blocking wait, background heartbeat, or renewal loop. Process termination relies only on finite Redis expiry before another cooperating scheduler may acquire the key.

The owner token prevents a stale owner from renewing or deleting a later owner's current key. It is not a monotonically increasing fencing token, and downstream work does not reject an operation merely because an earlier Redis lease expired. The lease therefore reduces overlap only among cooperating processes using the same reachable endpoint and namespace. It does not prove mutual exclusion through partitions, Redis restart, failover, asynchronous replication, clock anomalies, client pauses, TTL expiry during work, or an unavailable lease service.

ADR 024's SQLite job claim token, expiry checks, transaction, and idempotent effect remain the correctness boundary for the demonstrated durable job. The Redis schedule lease does not replace or strengthen them. If it expires while work continues, two scheduled passes may overlap; the SQLite job path must remain safe under its documented at-least-once behavior.

### Observability and ownership

`DocumentDetailsCacheTrace` records one bounded finite read, write, and invalidation outcome vocabulary. The application request summary advances to schema version `2` and carries one `document_cache` snapshot from that trace through the existing single terminal sink attempt. Lease acquisition, contention, renewal, release, and failures contribute bounded redacted command evidence. Events omit complete Redis keys, owner tokens, values, hostnames, ports, credentials, exception messages, payload fields, SQLite data, SQL, and bindings. No per-operation log write, second request sink, global logger, middleware, discovery, or hidden instrumentation is added.

Redis client construction, typed cache behavior, lease behavior, keys, values, and timeouts remain application source under `example/`; executable evidence remains under `tests/`. The repository records `ext-redis ^6.3` as a development requirement and exercises Redis server `>=7.4` and `<9.0` against the two-process topology. The extension is development evidence for this repository, not a runtime dependency of the framework package. No framework `src/` file, Consumer Contract version, Strict Profile version, or diagnostic changes.

## Consequences

The example provides concrete hit, miss, expiry, corruption, canonical-encoding rejection, source failure, cache-write failure, outage, eviction, endpoint and tenant isolation, committed-write invalidation, invalidation failure, stale-refill expiry recovery, contention, real renewal, real stale-owner rejection, real server rejection, release, and crash-recovery evidence without establishing a generic abstraction. Cache failure cannot convert derived state into authoritative state, and authorization still runs on every protected request.

The example now depends on two explicitly different Redis operational roles. Deployments must provision, secure, observe, and capacity-plan both endpoints and must prove the configured lease endpoint cannot evict the key. A successful local or CI test does not establish production failover, durability, partition, latency, pause, or clock behavior.

Applications may copy the documented reasoning, not a universal cache or lease API. Another backend or topology must record its own encoding, atomic operations, expiration, failure modes, and evidence.

## Reconsider when

At least two independent applications demonstrate the same smaller typed boundary with compatible key, payload, invalidation, stale-refill, timeout, lease, and failure semantics; or production evidence requires a fencing token or stronger coordination than this Redis lease can provide. Reconsider only the proved narrow boundary, never a generic cache facade or distributed-lock claim.
