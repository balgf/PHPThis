# Application operations contract

Use this guide for application-wide operation coordination, startup and probe claims, release ordering across application concerns, recovery sequencing, and the operational part of the optional local environment launcher. It does not own framework package preparation or publication; `RELEASING.md` is the sole route for those tasks.

## Application-wide sequencing

- Keep application release and recovery sequences explicit and application-owned. Reference configuration, database authority, migrations, jobs, cache, file transfer, WebSockets, and other concern records rather than copying their policies here.
- `.ai/operations.md` owns the application-wide release order and cross-history recovery sequence. `.ai/migrations.md` owns each migration history, database-writer coordination, partial-state detection, and forward-recovery contract; `.ai/database.md` owns effective runtime authority. Activate and verify required authority before dependent traffic, and remove or drain dependent code before deactivation or namespace removal.
- Keep production operations excluded until explicitly requested and authorized. Local development context does not authorize service connection, installation, provisioning, mutation, or production use.

## Standalone operation coordination

- Keep atomic lock, mutex, mutual exclusion, lease, critical-section, and other arbitrary operation coordination application-owned. Route the public policy through `docs/coordination.md`; use the application `.ai/operations.md` `OPERATION_COORDINATION` section as the sole record for a standalone named operation and do not add `.ai/coordination.md`.
- Record the exact protected operation, resource, interval, namespace, and collision scope; backend, client version, topology, authority, and security; atomic acquisition, wait, timeout, and contention behavior; owner token, TTL, renewal, and owner-checked release when supported or the exact non-token lifecycle; cleanup, crash, and uncertain outcomes; ownership loss and fencing or bounded non-fencing behavior; maximum work, idempotency, and bypass policy; bounded observability and incident ownership; and real concurrency, outage, recovery, and topology evidence.
- Keep scheduler overlap in `.ai/cli.md`, migration-writer coordination in `.ai/migrations.md`, durable-job ownership in `.ai/jobs.md`, and cache, session, file-transfer, and deployment concerns in their current owners. Preserve ADR 028 only as its Redis scheduled-pass reference.
- Add no framework lock, mutex, lease, fencing-token, coordination helper, facade, driver, registry, discovery, runtime dependency, checker rule, Contract/Profile change, or `PHT` diagnostic.

## Startup and probes

- Keep startup, liveness, dependency-health, and readiness claims aligned across installed configuration guidance, application routing, application `.ai/operations.md`, `.ai/testing.md`, and the skeleton.
- `Connection::connect()` constructs PDO eagerly and may perform driver- and DSN-specific I/O or fail during composition. Do not describe a connection-bearing composition root as dependency-free or defer its failure implicitly.
- The starter `GET /health` composes no database, cache, queue, or network dependency in its shared bootstrap, but its terminal coordinator invokes the deployment-configured `error_log` sink synchronously before returning. That destination may itself perform network or remote-filesystem I/O. Until its destination and latency are verified, describe the starter only as the current liveness route and HTTP composition proof, not as external-service-independent liveness.
- Every adopted HTTP or non-HTTP probe records its exact claim, composition root, every synchronous dependency and destination, bounded work, failure response or process behavior, local or deployment operations owner or explicit non-applicability, and evidence.
- Do not add a framework probe API, lazy connection, hidden bypass, second HTTP execution path, universal readiness definition, or checker diagnostic for operational semantics.

## Optional local launcher operation

- For an adopted application-owned local launcher, resolve the absolute project root, `PHP_BINARY`, and exact private PHP child explicitly, invoke PHP CLI without a shell, and keep the launcher outside production delivery and execution.
- `.ai/configuration.md` owns profiles, input names, file grammar, selected environment, failure, and redaction. `.ai/cli.md` owns the finite command map. `.ai/testing.md` owns the real launcher subprocess and array-form `proc_open` evidence. This guide does not authorize a second console or production launcher path.

## Verification

Exercise every claimed sequence and failure boundary at the level that owns it. Prove route and process startup claims against the real composition root and configured synchronous destinations. Coordination adoption requires real concurrency, contention, expiry or ownership-loss, outage, cleanup, recovery, topology, redaction, and bounded-work evidence; a happy-path lock call is not sufficient.
