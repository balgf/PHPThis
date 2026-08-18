# Application-owned operation coordination

PHPThis provides no atomic-lock helper, mutex, lease, fencing-token service, distributed-lock abstraction, coordination facade, driver, or discovery mechanism. Coordination is part of one application's exact operation, backend, topology, failure policy, and evidence. A mechanism that is correct for one operation or topology is not a portable framework capability.

Use this guide when a task names an atomic lock, mutex, mutual exclusion, lease, critical section, application coordination, or another operation-specific exclusion boundary. Start by naming the protected operation and the guarantee it actually needs. Do not start by selecting a generic lock interface.

## Keep one current application owner

For an arbitrary application operation that has no narrower concern owner, record `NOT_APPLICABLE(OPERATION_COORDINATION)` or the complete named adoption in `.ai/operations.md`. Do not add `.ai/coordination.md`; topology, process ownership, outage behavior, runbooks, incident response, and evidence references already belong in the operations contract. Executed evidence and its commands remain authoritative in `.ai/testing.md`.

Some operations already have a narrower current owner. Keep their mechanism and correctness policy there, and use `.ai/operations.md` only for surrounding deployment mappings, runbooks, incident ownership, and evidence references:

| Coordinated concern | Canonical application-owned policy |
| --- | --- |
| Scheduled application pass | `.ai/cli.md`; include clock, cadence, overlap scope, acquisition, timeout, expiry or cleanup, maximum work, supervisor, and real-console evidence |
| Database migration writer | `.ai/migrations.md`; include every reachable writer topology, stable history namespace, complete protected interval, lost-owner database-work safety, recovery, and exact-engine evidence |
| Durable-job delivery or processing ownership | `.ai/jobs.md`; include actual acknowledgement, redelivery, ownership-window, renewal or session-liveness, stale work, idempotency, replay, and recovery semantics |
| Server-side cache stampede control | the application cache policy plus `.ai/operations.md` for the selected backend topology and incident behavior |
| Native session concurrency | the application session policy plus `.ai/operations.md` for the exact native-file topology and evidence |
| File-transfer reservation, lifecycle, or cleanup | `.ai/file-transfers.md`; do not generalize storage-specific coordination into an application-wide lock |
| External deployment serialization or another arbitrary operation | `.ai/operations.md`, keyed by one stable operation name |

Do not duplicate a concern-owned record in the generic operation-coordination section. Instead, link to the concern owner and record only the operational mapping owned by `.ai/operations.md`. A name such as `schedule`, `migration`, or `job` does not make another concern's lease or lock reusable.

## Name the guarantee precisely

These terms are not interchangeable:

- **Critical section** names the exact interval of application work that must be considered together. It is not itself an exclusion guarantee.
- **Mutual exclusion or mutex** means cooperating contenders in one recorded collision domain admit at most one current owner while the mechanism remains valid. It says nothing about bypassing callers, partitions, stale work after ownership loss, or another topology.
- **Lease** is expiring ownership. Expiry enables recovery, but a paused or partitioned prior owner may continue after a successor acquires. A lease is not permanent mutual exclusion.
- **Fencing** requires a monotonically ordered token that every protected downstream effect validates so an older owner is rejected. A random owner token used only for renewal or release is not a fencing token.
- **Idempotency or duplicate safety** bounds the effect of repeated work. It can make overlap survivable, but it does not prevent concurrent execution and does not prove ownership.
- **Cross-system atomicity** requires one proved transaction or protocol spanning the named effects. A lock, lease, idempotency key, or successful call to two systems does not make those effects atomic together.

State the weakest guarantee that is sufficient and proved. If ownership can expire without fencing, record that overlap is possible and make every affected durable or external effect safe under that bounded failure. Do not upgrade an owner-checked release into a fencing, exactly-once, or cross-system-atomicity claim.

## Record one finite operation

Key every adoption by one stable application operation name. The record in `.ai/operations.md` must contain all of these facts before implementation:

- the exact protected operation, resource, start and end of the critical interval, stable namespace, environment or tenant dimensions, and collision scope;
- every cooperating and bypassing entrypoint, process identity, host or service topology, and the authority that permits acquisition and protected work;
- the selected backend, server and client or package names and exact supported versions, deployment topology, persistence or replication assumptions, network and TLS boundary, credential input names and owner without values or the exact filesystem authority, update owner, and security policy;
- the exact mechanism: local mutex, owner-token lease, advisory lock, same-host file lock, external serialization, or another backend-specific primitive;
- the one atomic acquisition operation and evidence that it is atomic for the selected backend, including blocking or nonblocking mode, maximum wait, connect and operation timeouts, retry or polling policy, contention outcome, and fairness or starvation limitation;
- the owner identity or token, TTL, renewal, and owner-checked release when the selected mechanism supports them; otherwise its exact descriptor, session, transaction, process, or external-serialization lifecycle or explicit non-applicability; in either case record cleanup, crash behavior, and the policy for an uncertain acquisition, renewal, release, or ownership result;
- ownership-loss detection, stale-owner behavior, the downstream fencing rule and ordered token when supported, or the exact bounded non-fencing limitation when it is not;
- the maximum admitted work duration or an explicit `UNPROVED` duration limitation, its relationship to TTL and renewal, timeout and forced-termination policy, idempotency or duplicate-safe effect scope and retention horizon, and every path that could bypass coordination;
- bounded code-owned observability without keys, tokens, credentials, payloads, exception details, or one event per backend call; the operations owner, alert, incident, outage, recovery, and manual-intervention behavior; and
- references to `.ai/testing.md` for real evidence covering concurrent acquisition, ordinary contention, timeout, expiry or cleanup, renewal where used, stale-owner rejection or demonstrated overlap limitation, process termination, backend outage, uncertain outcomes, recovery, every supported topology, and every recorded bypass denial.

`NOT_APPLICABLE(OPERATION_COORDINATION)` means the application currently has no arbitrary operation-specific coordination adoption outside its routed concern owners. It does not erase a scheduler, migration, job, cache, session, file-transfer, or deployment record that is already owned elsewhere.

## Prove acquisition and the whole protected interval

Acquisition must be one backend-supported atomic operation. A read followed by a write, existence check followed by creation, or local in-memory test followed by a remote write is a race unless the selected backend proves the complete sequence atomic. Record the exact primitive and its result mapping rather than saying only "obtain the lock."

Blocking acquisition needs a finite maximum wait, cancellation and interruption behavior, capacity impact, and a distinct timeout outcome. Nonblocking acquisition needs an exact contention outcome. Hidden retries, polling, reconnects, exponential backoff, fairness, and starvation behavior are absent unless explicitly selected, bounded, and proved.

The protected interval begins before the first operation that must not overlap and ends only after the last protected effect or accepted completion record. Acquiring after a read or releasing before a durable write leaves part of the operation outside the guarantee. When coordination is external to the application process, prove that every authorized entrypoint goes through that boundary and that bypassing work cannot obtain the required configuration or authority.

## Treat uncertain outcomes as state

A timeout or lost response does not prove that a remote acquisition, renewal, release, or protected effect failed. Record whether the backend offers a safe query or owner-checked reconciliation operation. Until the result is resolved, do not blindly retry a non-idempotent operation or assume the resource is free.

Release acts only on current ownership. When the mechanism uses an owner token, compare it atomically before renewal or release so a stale process cannot extend or remove a successor's ownership. Cleanup in `finally` is useful ordinary behavior, but process termination may skip it. A non-expiring mechanism therefore records its exact automatic release on descriptor, session, transaction, or process termination, or a separate bounded backend or supervisor cleanup owner; an expiring lease requires the stale-work policy described below.

## Bound ownership loss and stale work

An expiring lease may be lost because of timeout, pause, partition, restart, failover, clock behavior, renewal failure, or work exceeding the current TTL. Stop before the next protected effect when loss is detected, but do not claim that stopping the client terminates a database statement, external request, child process, or already accepted remote effect.

Use a fencing claim only when:

1. acquisition yields a monotonically ordered token in the complete collision domain;
2. every protected downstream effect receives that token; and
3. each downstream owner atomically rejects a token older than the latest accepted token.

Otherwise record `NON_FENCING`, the exact ways a stale owner can continue, the bounded overlap or duplicate-safe effect policy, and the recovery owner. A random UUID or opaque owner token can support owner-checked renewal and release only when the selected mechanism proves those operations; it is not fencing.

## Keep duration, idempotency, and atomicity separate

Record the maximum admitted work duration independently from backend TTL, client timeout, and renewal cadence. If no credible numeric bound is proved, record `UNPROVED`, do not rely on the lease for correctness after expiry, and require the protected effects to tolerate the resulting overlap or fail closed before those effects.

Idempotency has its own exact key source, uniqueness scope, durable owner, retention and deletion horizon, replay policy, backup and restore behavior, and post-horizon outcome. It does not inherit the lock namespace or owner token automatically. A duplicate-safe database write does not make an external call duplicate-safe, and a provider idempotency key does not make a local transaction atomic with the provider.

When work spans a database and another service, state the commit and call order, every partial outcome, ambiguous timeout behavior, retry or reconciliation owner, and compensation. Do not describe that sequence as atomic merely because it ran inside a critical section.

## Bounded Redis schedule-lease reference

[ADR 028](decisions/028-application-owned-redis-cache-and-schedule-lease.md) and [Redis cache and schedule coordination](redis-coordination.md) record one existing application-owned example. It is a reference for the decision fields above, not a portable lock recipe:

| Field | Recorded example fact |
| --- | --- |
| Protected operation and collision domain | one due `schedule:run` pass; cooperating processes that reach the same application environment, Redis lease endpoint, and `phpthis_example:<environment>:schedule_run:v1` key |
| Backend and topology | `ext-redis ^6.3` with Redis server `>=7.4` and `<9.0`; one separate single-primary `noeviction` lease process, default `127.0.0.1:6380`, logical database `0`; the cache uses another process rather than another logical database |
| Acquisition and contention | one nonblocking `SET key token NX PX 30000`; fresh 128-bit lowercase-hex owner token; 250-millisecond connect and read timeouts; zero client retries; contention returns `overlap_skipped` |
| Lifetime and release | 30-second expiry, at most four explicit renewals, fixed owner-checking Lua for renewal and release, no wait, polling, retry, background heartbeat, or hidden renewal loop; Redis operation failure fails the command closed |
| Work and correctness boundary | one in-process job attempt; no proved numeric wall-clock maximum, so duration is `UNPROVED`; the token is not fencing, expiry can permit overlapping stale work, and the independent SQLite job ownership plus duplicate-safe effect remains the demonstrated correctness boundary |
| Exclusions | no same-slot sequential deduplication, direct-job coordination, restart, failover, replication, partition, pause, clock, uncertain-timeout, multi-primary, Cluster, Sentinel, managed-service, multi-region, or production guarantee |
| Evidence | acquisition, contention, bounded renewal, owner-checked release, stale-token rejection, process termination, expiry and later acquisition, backend rejection and outage, exact timeout/TTL settings, redacted finite outcomes, and the recorded two-process topology |

Copy the reasoning fields, never the mechanism by default. Another operation, Redis version, client, key space, topology, failover mode, or downstream effect needs its own application decision and evidence.

## Evidence and review boundary

Deterministic tests prove the application's result mapping and bounded state machine. They do not prove a real backend's atomicity, timing, expiry, failover, or process-termination behavior. Pair them with isolated real-backend or real-filesystem/process evidence for every claimed topology, using synthetic non-production data and no production mutation.

The complete application gate includes the adoption's deterministic and real coordination evidence. `composer check` for PHPThis proves only that this guide, task routing, application-context template, default `NOT_APPLICABLE(OPERATION_COORDINATION)` marker, and no-framework-runtime boundary ship together. It does not certify an application's selected backend, topology, incident response, or production guarantee.

## Unsupported framework boundary

This guidance adds no framework class, interface, trait, helper, facade, service, driver, registry, discovery, configuration, runtime dependency, automatic retry, background renewal, checker rule, Consumer Contract version, Strict Profile rule, or `PHT` diagnostic. PHPThis does not select a coordination backend or convert mutex, lease, fencing, idempotency, and cross-system atomicity into interchangeable mechanisms.
