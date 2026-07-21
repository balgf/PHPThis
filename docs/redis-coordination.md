# Redis cache and schedule coordination

PHPThis provides no Redis client, cache interface, cache helper, lock interface, or distributed-coordination runtime. ADR 028 records one application-owned proof in the executable example. This guide explains the evidence boundary an AI must preserve when reviewing or adapting that proof.

## Two Redis roles

The proof uses Redis for two roles that must not be collapsed operationally:

| Role | Stored state | Failure policy | Required topology |
| --- | --- | --- | --- |
| Document cache | `RedisDocumentDetailsCache`, bounded reproducible document-details JSON, 30-second TTL | miss, expiry, eviction, corruption, or outage falls back once to SQLite | distinct cache process, default `127.0.0.1:6379`, logical database `0`, with recorded finite capacity and eviction policy |
| Scheduled-pass lease | `RedisScheduleRunLease`, one owner token, 30-second TTL | contention skips; Redis operation failure fails the command closed | distinct single-primary process, default `127.0.0.1:6380`, logical database `0`, using `noeviction` |

A logical database number does not create the required separation. Databases in one Redis process share its memory limit, eviction policy, restart, and failure domain. The executable proof therefore records two different endpoints and two different Redis process identities while using logical database `0` on each.

## Protected cache-aside path

The cache never participates in authentication, tenant resolution, or authorization. The order remains:

```text
authenticate -> resolve tenant -> authorize -> cache read -> authoritative read on non-hit -> cache write -> response
```

Every request performs the first three steps. A cache hit says only that a bounded tenant-owned representation was previously derived; it grants no access and carries no permission state.

The exact key grammar is `phpthis_example:<environment>:tenant:<account-id>:document_details:v1:<document-key>`. Its environment, positive account identifier, and bounded document key are already validated. Tests use synthetic values and assert exact grammar without emitting complete runtime keys.

The value is JSON schema version `1` with exactly `schema_version`, `tenant_account_id`, `document_key`, and `title` in that order. It is at most 1,024 bytes, and the non-empty valid-UTF-8 title is at most 512 bytes. Its parser rejects invalid JSON, excessive bytes or depth, missing or unknown fields, wrong scalar types, wrong tenant identity, wrong document identity, empty or oversized titles, and non-canonical encodings. Canonical validation re-encodes the accepted ordered shape with `JSON_UNESCAPED_SLASHES` and requires byte equality, so duplicate fields and alternate whitespace or ordering cannot be accepted through ordinary JSON decoding. A rejected value is a recorded cache failure followed by the same authoritative read used for an ordinary miss. Arbitrary PHP serialization is prohibited.

The cache TTL is 30 seconds. Early eviction is an ordinary miss. A backend exception or timeout does not trigger a hidden retry and cannot turn a failed SQLite operation into success. A successful authoritative read may still be returned when the subsequent cache write fails.

## Writes, invalidation, and stale refill

`RedisInvalidatingDocumentTitleUpdate` keeps authoritative writes and cache invalidation deliberately non-atomic:

```text
execute authoritative SQLite autocommit update -> invalidate exact Redis key -> return typed database and invalidation outcome
```

Never invalidate before commit and never report a rolled-back SQLite mutation as successful because a cache operation succeeded. If invalidation fails after commit, the committed SQLite result remains authoritative and the failure is observable.

The accepted proof adds no coalescing, generation fence, or compare-and-set refill. A miss can read an older SQLite value, another process can commit and invalidate, and the first process can then refill the older value. The recorded maximum stale window is 30 seconds from that refill. Tests exercise that order and expiry. Applications that cannot accept this bound need a separately approved revision-aware design; shortening the TTL is not equivalent to read-after-write consistency.

Cold-cache database evidence remains mandatory. Hit tests cannot replace query budgets, bounded traces, small-versus-large fixture comparison, authorization tests, or direct inspection of the authoritative query.

## Owner-token lease

The schedule lease key is exactly `phpthis_example:<environment>:schedule_run:v1`. It contains no path, database name, hostname, credential, or tenant value because the protected operation is one application-wide scheduled pass. The value is exactly one freshly generated 128-bit lowercase-hex token.

Acquisition is one nonblocking Redis command equivalent to:

```text
SET <lease-key> <owner-token> NX PX 30000
```

Renewal and release each use one fixed Lua script. Renewal extends expiry only when the stored value still equals the caller's token. Release deletes only that same owned value. A process whose lease expired cannot renew or delete a later owner's lease.

Connect and read timeouts are each 250 milliseconds, and the phpredis client maximum-retries option is explicitly zero. There is no blocking acquisition, automatic reconnect retry, command retry, polling, background heartbeat, or hidden renewal. One invocation may renew at no more than four recorded explicit points. Redis expiry permits recovery after process termination, but it also means another process may acquire while paused or slow work from the first process continues.

The token is an ownership check, not a fencing token. It has no ordering and is not passed to SQLite as a condition on durable effects. Redis server time and atomic command execution do not prove safety through network partitions, client timeouts with unknown command outcome, process suspension, restart without the key, failover, asynchronous replication, or a lease TTL shorter than real work. The separate `noeviction` endpoint removes one known early-disappearance cause; it does not remove the others.

The existing SQLite job lease remains independent. It checks its own opaque token and expiry at database transitions and relies on an idempotent demonstrated effect. The Redis schedule lease only reduces concurrent invocations of that one-job pass.

## Failure and event policy

Cache failure falls back to the authoritative read and records a finite outcome. Lease contention returns `overlap_skipped`. Every successful `schedule:run` line adds a finite `coordination` list after `command` and `outcome`; a not-due pass uses `[]`, contention uses `["connected","contended"]`, and the demonstrated owned pass uses `["connected","acquired","renewed","released"]`. Lease connection, acquisition, renewal, and release failures emit one stderr line with `error: command_failed` and their finite `coordination` list. No exception message or raw Redis response is sent to a client or command output.

`DocumentDetailsCacheTrace` records bounded finite read, write, and invalidation outcomes. Request-summary schema version `2` includes its `document_cache` snapshot in the existing single sink attempt. The command coordination list contains at most eight code-owned outcome strings and can name contention, renewal failure, or release failure without exposing Redis data. Both channels omit complete keys and values, owner tokens, Redis endpoints, credentials, exception details, domain values, SQL, and bindings. Do not add one log write per Redis call or another sink invocation to obtain this evidence.

## Required evidence

The application proof covers:

- cache hit and miss with the same typed representation;
- finite expiry and early eviction;
- malformed, oversized, unsupported-version, wrong-owner, duplicate-field, and other non-canonical payloads;
- backend outage, authoritative-source failure after a cache read, and cache write failure without changing authoritative truth;
- environment and tenant key isolation;
- authoritative commit before exact-key invalidation, rejected-write non-invalidation, and committed-write survival through invalidation failure;
- a stale refill racing commit and invalidation, bounded by expiry and followed by authoritative recovery after expiry;
- concurrent cold misses under the recorded stampede policy;
- cold-cache constant query count across materially different fixtures;
- lease acquisition, contention, explicit renewal, and owned release, including renewal against the real endpoint;
- stale-token renewal and release rejection against deterministic and real successor leases;
- ordinary contention distinguished from deterministic and real Redis server rejection;
- process termination, TTL expiry, and later acquisition;
- exact timeout and TTL configuration, redacted structured evidence, and fail-closed lease outage behavior; and
- real integration evidence against `ext-redis ^6.3`, Redis server `>=7.4` and `<9.0`, and the two-process topology, separate from deterministic application tests.

The implementation source is under `example/`; the behavior and integration evidence is under `tests/`.

The evidence does not certify another Redis version, another client, Cluster or Sentinel, managed-service failover, replication durability, production latency, clock behavior, or multi-region coordination.
