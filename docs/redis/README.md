# Redis proof knowledge index

This directory routes an AI through PHPThis's one application-owned Redis proof. It is not a generic Redis manual, cache API, or distributed-lock contract. Start with [the proof boundary](../redis-coordination.md) and ADR 028, then load only the file needed for the task.

| Task | Read | Inspect in the application |
| --- | --- | --- |
| Change key construction | [Cache key](cache-key.md) | validated environment, tenant, document identity, exact key tests |
| Change JSON parsing or bounds | [Cache value](cache-value.md) | `RedisDocumentDetailsCache`, projection factory, corruption tests |
| Add or change a title mutation | [Invalidation](invalidation.md) | `RedisInvalidatingDocumentTitleUpdate`, SQLite update, typed outcome |
| Assess concurrent refill | [Stale refill](stale-refill.md) | miss ordering, 30-second TTL, race tests |
| Change cache outage behavior | [Cache failures](cache-failures.md) | fallback read, trace outcome, no-retry tests |
| Change Redis endpoints or versions | [Topology](topology.md) | cache `127.0.0.1:6379/0`, lease `127.0.0.1:6380/0`, distinct process identities, `ext-redis ^6.3`, Redis server `>=7.4` and `<9.0` |
| Change lease acquisition, renewal, or release | [Lease lifecycle](lease-lifecycle.md) | `RedisScheduleRunLease`, fixed Lua, token and expiry tests |
| Change lease outage handling | [Lease failures](lease-failures.md) | command mapping, termination and expiry recovery |
| Change structured evidence | [Observability](observability.md) | `DocumentDetailsCacheTrace`, request-summary version 2, command output |
| Add proof cases or integration versions | [Testing](testing.md) | deterministic tests, real Redis service, cold-cache SQL proof |
| Decide whether another application may adopt the pattern | [Adoption gate](adoption.md) | accepted application facts, backend evidence, downstream correctness, and explicit unsupported claims |

Do not load every file by default. No file here authorizes framework-core types, a generic cache or Redis wrapper, automatic caching, hidden retries, background renewal, fencing claims, or exactly-once claims.
