# Redis proof testing

Deterministic application tests cover:

- hit, miss, finite configured expiry, early eviction, corruption, wrong tenant, wrong document, duplicate and non-canonical encoding, oversized value, and backend outage; the executable composition's ordinary TTL is 30 seconds;
- exact cache key grammar and 192-byte cap, canonical ordered `schema_version`, `tenant_account_id`, `document_key`, and `title` JSON fields, 1,024-byte payload cap, and 512-byte title cap;
- SQLite fallback and identical typed results, authoritative source failure with retained read evidence, cache write failure without changed truth, autocommit update before invalidation, rejected-update non-invalidation, invalidation success and failure, and stale-refill expiry recovery;
- concurrent cold misses with no coalescing and cold-cache constant SQL across materially different fixtures;
- lease acquisition, contention distinguished from server rejection, 250-millisecond timeouts, no more than four explicit renewals, stale-owner rejection, owned release, operation failures, process termination, and post-expiry acquisition; and
- request-summary version `2`, `document_cache` outcomes, schedule success and Redis-failure `coordination` lists, exact one-line command bytes, and complete redaction.

Separate integration evidence uses `ext-redis ^6.3` and Redis server `>=7.4` and `<9.0`. It proves two distinct process identities: the eviction-capable cache defaults to `127.0.0.1:6379`, database `0`, while the single-primary `noeviction` lease defaults to `127.0.0.1:6380`, database `0`. Real-backend cases cover renewal TTL restoration, stale-owner inability to change a successor, and server rejection reported as failure rather than contention. Source remains under `example/`; evidence remains under `tests/`. Passing deterministic tests does not certify restart, failover, replication, partition, clock, pause, production capacity, or fencing behavior.
