# CLI testing

For production adoption, execute the real application console in fresh subprocesses. Add evidence for every accepted command and applicable outcome, the complete argument grammar and byte limits, rejection before application I/O, exact exit codes, exclusive stdout/stderr bytes, stable key order, final newline, missing-resource and deliberately injected unexpected-failure behavior, and complete secrets redaction.

Use an explicit deterministic clock to exercise UTC minutes immediately before, on, and after the five-minute boundary. Prove not-due performs no scheduled operation, a held same-host lock produces immediate `overlap_skipped`, injected lock failures are generic and fail-closed, and release occurs after success and failure.

Prove one due pass calls the same one-job operation at most once and handles at most one delivery. Record and test that there is no catch-up or slot ledger and that sequential invocations in one due minute are not deduplicated. Verify fresh HTTP and CLI mutable state under only the recorded immutable shared configuration.

For `database:migrate`, prove fresh-database manifest order, exact bounded ledger state, unchanged no-op rerun, edited-content and malformed or overflowing ledger rejection before pending work, immediate same-host lock contention with no database change, per-migration rollback with earlier commits preserved, forward continuation, and complete output redaction. Prove ordinary HTTP startup performs no migration work. Treat SQLite and local `flock` results as application evidence only.

These are consumer evidence requirements. The current example proof is intentionally narrower: its real-console cases cover invalid, unknown, missing-database, `completed`, and `idle`; direct command tests cover deterministic cadence and contention; ADR 024 worker tests plus exhaustive typed mapping and static analysis cover retry and dead-letter outcomes. It does not inject lock-operation or arbitrary throwable failures. The focused console proof does not replace the complete application gate, and a same-host lock test is not distributed-coordination or exactly-once evidence.

See [the complete guide](../cli.md), [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md), and [ADR 027](../decisions/027-application-owned-explicit-sqlite-migrations.md).
