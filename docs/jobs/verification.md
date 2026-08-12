# Backend-neutral durable-job verification

Status: current optional guidance under accepted ADR 052. The [checked SQLite profile](sqlite.md) and ADR 024 remain the first and only checked backend-specific evidence.

An adopting application owns one exact verification command for its selected backend. The command exercises a real service wherever it claims backend behavior, fails closed when required configuration or evidence is absent, and runs from the normal application test and complete release gate. A skip, missing service, missing credential, mock, in-memory double, or alternate fallback never counts as a passing release result.

Use only an isolated non-production service namespace with one unique bounded run identity. Create every test resource under that identity, reject production credentials and production data, and remove the exact owned resources in bounded `finally` cleanup without broad scans or deletion outside the run namespace. Because `SIGKILL` or another hard termination can skip `finally`, record and prove a finite abandoned-run lifetime plus one exact run-ID-scoped stale-run reconciliation owner and mechanism. Reconciliation selects only eligible resources owned by that run identity; it never performs broad deletion or removes another run, environment or production resource. Retain only bounded redacted evidence permitted by the recorded policy.

This structure organizes application evidence. It is not a backend validator, PHPThis checker extension, queue adapter, transport simulation, or proof of production topology.

## Evidence matrix

### Publication and recovery module

Prove the exact adopted publication boundary:

- the exact initiating durable or business state, if any, and the adopted direct durable message, committed publication intent, or same-database job record;
- the isolated real-service gate asserts the selected service or API identity and version, client version, and every safely observable or pinned version-controlled durability, persistence and topology feature on which the test relies; unsupported or drifted versions and features fail closed, unobservable managed-service internals are not claimed, and production topology remains separate deployment evidence;
- after the application reports publication success, the exact intent or message survives producer/request termination and remains recoverable or deliverable until acknowledgement, terminal outcome, or explicit finite cancellation or expiry inside the recorded durability/fault envelope; restart and failover are asserted only when exercised;
- ambiguous and failed publication remain distinct from success, catastrophic loss outside the named envelope is reported as a limit, and Redis Pub/Sub, process memory or another fire-and-forget path cannot satisfy this durable-publication case;
- when a database transaction applies, rollback or statement failure leaves no independently visible business change or orphaned publication state according to the recorded transaction design;
- relay termination before broker publication recovers the committed outbox intent;
- relay termination after broker acceptance but before durable confirmation recording may republish, and that duplicate remains safe;
- ambiguous confirmation, reconnect, duplicate relay ownership, poison intent, backlog, retention and cleanup follow the recorded finite policy; and
- an after-commit call or publisher confirmation is never treated as atomic with an independently committed database transaction.

When no business database commit, outbox or relay applies, the application records its initiating-state semantics, including explicit non-applicability, and the exact direct-publication and recovery mechanism; it marks every database, rollback, outbox and relay case explicitly not applicable and does not simulate evidence for a mechanism it did not choose.

### Delivery module

Prove:

- a valid bounded versioned JSON envelope reaches exactly one finite code-owned dispatch branch;
- malformed, oversized, unsupported-version, unsupported-type and poison envelopes stop before handler work;
- each job type rejects forged publication authority and cross-tenant, wrong-owner, wrong-principal or wrong-action binding before effect work, while a genuinely tenantless system-owned obligation proves its recorded non-applicability;
- stale permission, revocation and relevant domain-state changes follow the recorded current-recheck policy before effect, or the handler proves it is executing the exact previously committed system-owned obligation; a broker credential alone never satisfies this evidence;
- acknowledgement occurs after the intended durable semantic effect, or in the same explicitly proved atomic boundary when the selected storage mechanism actually permits it, without implying a cross-system transaction;
- delivery may occur more than once and duplicate, overlapping and redelivered execution leaves the semantic effect duplicate-safe; the proved successful-publication invariant keeps work recoverable or deliverable within its named envelope, while an explicit recorded terminal rejection/exhaustion, finite cancellation or expiry, or catastrophic loss outside that envelope may still yield zero handler executions; a stronger provider claim never upgrades execution or external effects to exactly once;
- termination before acknowledgement and after effect completion follows the adopted recovery and idempotency path;
- the backend's applicable receipt handle, visibility expiry, lease, reclaim, pending group, partition, offset, ordering, redelivery and stale-owner behavior is exercised without translating it into a generic guarantee;
- when a semantic effect depends on ordering, the exact ordering, partition or sequence key and concurrency scope are exercised and the service-enforced order or selected monotonic/version guard, rejection or reconciliation path prevents stale, out-of-order or replayed work from replacing newer state; an order-independent effect proves its recorded non-applicability;
- where an ownership window or session-liveness requirement applies, maximum handler duration is bounded relative to that window and the exact extension, renewal, heartbeat or session-liveness owner, cadence and limits are exercised; renewal failure or expiry proves the actual stale-owner behavior; rejection or fencing is required only when supported, otherwise bounded overlapping stale work and duplicate-safe recovery are proved; non-applicability is recorded only when no such mechanism applies;
- retry and redrive are finite, version-controlled, and owner-named whether application code, broker configuration or infrastructure as code owns them;
- the exact application attempt counter, broker receive counter, redrive counter or configuration condition that owns terminal routing is exercised; when those counters differ, their mapping and precedence are explicit rather than inferred;
- exact retry exhaustion and terminal routing occur at the recorded attempt boundary;
- replay preserves or replaces the original idempotency identity only under the recorded compatibility and semantic policy;
- the semantic effect and idempotency identity have the recorded uniqueness scope, durable retention/deletion owner and protection horizon beyond every possible retry, redrive, terminal-retention window including any adopted dead-letter/DLQ, replay and provider-deduplication expiry; after that horizon work follows the recorded fail-closed rejection, reconciliation or new-semantic-operation policy rather than executing unprotected; and
- cancellation defines exact pending, claimed or in-flight, effect-started, acknowledged and terminal behavior, without claiming recall of already executing or completed work.

### Operations module

Prove:

- configured worker concurrency, prefetch or backpressure, capacity and admission bounds;
- finite process and operation timeouts, maximum handler duration relative to any delivery-ownership window, applicable extension, renewal, heartbeat or session-liveness behavior, outage, reconnect, shutdown, forced termination, supervisor restart, deployment replacement and recovery behavior;
- the exact selected service-owned or application-owned time source, representation, units, rounding, precision and accepted skew for every applicable visibility, lease, TTL, delay, retry and retention boundary, exercising only the mechanism actually selected;
- queue, stream, partition or outbox depth; oldest age or lag; terminal outcomes; retry, redrive and any adopted dead-letter/DLQ growth; and alert activation at the recorded thresholds;
- retention, replay, backup, restore and disaster-recovery expectations that apply to the selected topology;
- backup and restore preserve corresponding queued-message plus semantic-effect/idempotency state, or stop consumers and reconcile every mismatch before work resumes; restoring old queue, selected terminal-store, backup or replica state cannot resurrect an effect after its duplicate protection expired or was deleted;
- distinct least-privilege producer, consumer, relay, inspector and administrator identities or explicit non-applicability; tenant and environment isolation; network boundaries; credential rotation and revocation; TLS and authorization failures; and no fallback to broader authority;
- payload data minimization and classification; configured encryption at rest and key ownership where applicable; region and residency; and exact authorized access, retention and deletion for the normal queue plus every selected terminal or dead-letter/DLQ store, backup, replica and snapshot that may retain the full envelope beyond normal retention;
- privileged inspection, replay, cancellation and deletion require the recorded identity, authorization and audit path and preserve redaction;
- stored diagnostics, command output, logs, traces and metrics omit envelopes, payloads, idempotency identities, receipts, credentials, tokens, connection details, customer data, exception messages, stacks, SQL, bindings and external response bodies; this redaction evidence is distinct from configured access and retention evidence for durable stores that legitimately contain the full envelope; and
- every domain-policy denial or terminal routing result is generic, bounded and redacted.

Production persistence, replication, failover, network partitions, alarms, security controls, capacity and recovery drills need deployment evidence in addition to this application command.

## Recommended application-owned files

Use exactly one explicit ordered entrypoint over three cohesive application-owned modules. The filenames below are a copyable recommendation, not a PHPThis checker rule or framework-owned layout:

```text
tools/verify-jobs.php
tools/verify-jobs/publication.php
tools/verify-jobs/delivery.php
tools/verify-jobs/operations.php
```

Each module declares one exact application function and returns only a finite list of code-owned failure identifiers. The entrypoint requires the three literal paths in publication, delivery, operations order; no glob, filesystem scan, registry, reflection, dynamic callback, plugin, class-name lookup, generic backend validator, or automatic discovery participates.

The modules own the selected backend client, isolated real-service fixture, process controls, cleanup and assertions. They do not implement portable queue behavior. Modules and clients must emit no direct diagnostic bytes; execute noisy third-party processes behind an application-owned captured and bounded subprocess boundary rather than inheriting the verifier's streams. The entrypoint only aggregates their failure lists and selects one fixed terminal result.

## Exact publication module shape

```php
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function verifyJobsPublication(): array
{
    // Exercise this application's selected real publication and recovery path.
    // Return finite code-owned failure identifiers; never secrets or payloads.
    return ['jobs_publication_not_implemented'];
}
```

## Exact delivery module shape

```php
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function verifyJobsDelivery(): array
{
    // Exercise this application's real parser, dispatch, effect and backend delivery path.
    // Return finite code-owned failure identifiers; never secrets or payloads.
    return ['jobs_delivery_not_implemented'];
}
```

## Exact operations module shape

```php
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function verifyJobsOperations(): array
{
    // Exercise this application's real process, security, outage and lifecycle path.
    // Return finite code-owned failure identifiers; never secrets or payloads.
    return ['jobs_operations_not_implemented'];
}
```

These fixed `*_not_implemented` results make an unadapted copy fail closed. An adopter replaces every body with real backend-specific assertions; only a completed application-specific module returns `[]`, and only after all of that module's required assertions pass. A missing real-service configuration, topology, fixture, permission or observation remains a finite failure rather than a skip. Modules emit no stdout or stderr bytes and never switch to a mock fallback.

## Exact ordered entrypoint shape

```php
<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function runJobsVerificationModules(): array
{
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException('Jobs verification runtime warning.', 0, $severity);
        },
    );

    try {
        require dirname(__DIR__) . '/vendor/autoload.php';
        require __DIR__ . '/verify-jobs/publication.php';
        require __DIR__ . '/verify-jobs/delivery.php';
        require __DIR__ . '/verify-jobs/operations.php';

        $publicationFailures = verifyJobsPublication();
        $deliveryFailures = verifyJobsDelivery();
        $operationsFailures = verifyJobsOperations();

        return [
            ...$publicationFailures,
            ...$deliveryFailures,
            ...$operationsFailures,
        ];
    } finally {
        restore_error_handler();
    }
}

(static function (): void {
    $jobsVerificationState = new class {
        public bool $completed = false;
    };

    register_shutdown_function(
        static function () use ($jobsVerificationState): void {
            if ($jobsVerificationState->completed) {
                return;
            }

            fwrite(STDERR, "JOBS VERIFY FAIL\n");
            exit(1);
        },
    );

    try {
        $failures = runJobsVerificationModules();
    } catch (Throwable) {
        $jobsVerificationState->completed = true;
        fwrite(STDERR, "JOBS VERIFY FAIL\n");
        exit(1);
    }

    if ($failures !== []) {
        $jobsVerificationState->completed = true;
        fwrite(STDERR, "JOBS VERIFY FAIL\n");
        exit(1);
    }

    $passBytes = "JOBS VERIFY PASS\n";
    $written = fwrite(STDOUT, $passBytes);

    if ($written !== strlen($passBytes)) {
        exit(1);
    }

    $jobsVerificationState->completed = true;
})();
```

The complete entrypoint has one bounded result for an ordinary completed terminal write: exact `JOBS VERIFY PASS\n` on stdout and exit `0`, or exact `JOBS VERIFY FAIL\n` on stderr and exit `1`. Subprocess evidence asserts both streams byte-for-byte. The temporary warning-to-`ErrorException` conversion is scoped only around the verifier's literal requires and module calls and is restored in `finally` after normal return or `Throwable`; premature `exit` or hard process termination ends that process before restoration. Modules and clients must not change the error handler. A missing-module mutation therefore reaches the outer `Throwable` boundary without leaking its native path-bearing warning or other message bytes. The entrypoint emits no failure identifiers, backend exception text, skip status or other bytes. The completion guard converts a module's premature `exit(0)` or `die` without a message into fixed failure and exit `1`; mutation evidence must pin that case. Modules and their in-process clients must not call `exit`, `die`, `goto`, yield control, or write stdout/stderr. The `Throwable` boundary redacts converted runtime warnings plus ordinary module and require exceptions only; it cannot contain arbitrary direct writes, engine-fatal output, `SIGKILL`, another hard process termination, failure before PHP starts, or a partial terminal stream write. Those remain failures owned and byte-bounded by the outer CI/process boundary and cannot become a pass. Each module bounds its own work, time, collection cardinality and exact run-owned `finally` cleanup. The application owns the exact counter or configuration condition and terminal transition for every poison or exhausted delivery; when broker receive/redrive counters differ from application attempts, their mapping and precedence are recorded and proved. When the broker owns an adopted dead-letter/DLQ or other terminal-routing mechanism, its version-controlled broker or infrastructure configuration and named owner are part of the evidence; explicit non-applicability remains valid for a different selected terminal mechanism.

The single no-argument `runJobsVerificationModules()` call first requires one literal application-owned Composer autoload path, then owns the literal ordered module path; no second bootstrap, entrypoint or discovery path exists. The default example uses `dirname(__DIR__) . '/vendor/autoload.php'`. An application with a custom Composer vendor directory replaces that expression with one exact project-relative path and records it in `.ai/jobs.md`; it does not search for an autoloader or silently fall back. Modules manually compose the selected application code and backend client through those autoloaded classes. The completion state lives only inside the immediately invoked static closure, not in global or loader scope, so included modules cannot alter it through an unqualified variable or `$GLOBALS`. Mutation evidence pins both ordinary premature exit and an attempted global-state bypass to fixed failure.

## Exact Composer gate wiring

```json
{
  "scripts": {
    "profile": "phpthis check",
    "jobs:verify": "php tools/verify-jobs.php",
    "test:application": "php tests/run.php",
    "test": [
      "@jobs:verify",
      "@test:application"
    ],
    "check": [
      "@profile",
      "@test"
    ]
  }
}
```

The names above are the guidance's literal recommended chain: `jobs:verify` runs the one application-owned entrypoint; `test` invokes `jobs:verify`; and `check` invokes `test` after the installed profile. An application with an established test command may preserve its underlying runner under a different finite script name, but its `.ai/jobs.md` and `.ai/testing.md` record the exact equivalent chain and prove the jobs gate cannot be skipped by the release command.

## Optional application-owned static checks

An adopter may include one backend-specific application-owned static checker inside its `jobs:verify` evidence only for mechanically decidable local source or configuration invariants. Before adoption, record its single implementation and update owner, pinned backend and client versions, exact finite diagnostic catalogue, supported source surface, and known limitations. Supply positive and negative fixtures, exact diagnostic assertions, mutation-sensitive controls, and the same normal gate wiring as the other jobs evidence.

Such a check may, for example, reject a prohibited dynamic class name or verify a finite reviewed local configuration shape when the application can decide that fact honestly from source. It cannot prove broker durability, publication atomicity, relay recovery, publisher confirmation, acknowledgement ordering, visibility expiry, redelivery, idempotency, TLS, authorization, failover, throughput, retention or production topology. PHPThis does not host, configure, discover or execute an application checker outside the application's explicit `jobs:verify` path, and this guidance adds no generic backend-checker API.

ADR 052 adds no framework script, generic jobs test library, service provisioning, credential delivery, environment fallback, consumer-checker backend rule, or production certification.

The installed synthetic structure proof is deliberately a non-adopter. Its temporary context contains both `NOT_APPLICABLE(JOBS)` and `REFERENCE_ONLY(JOBS_VERIFICATION_STRUCTURE)`. It may replace the three fail-closed placeholder bodies only to prove literal composition order, success selection, one-module failure, exception redaction and fixed output bytes. That synthetic pass does not adopt jobs and proves no backend, publication, durability, delivery, idempotency, security or production semantics. An application may report adoption only after replacing the modules with its own real-service assertions and recording the resulting evidence.
