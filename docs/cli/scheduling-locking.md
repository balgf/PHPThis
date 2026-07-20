# CLI scheduling and locking

The accepted `schedule:run` pass reads an injected Unix clock as UTC, ignores seconds, and is due only when `intdiv(epoch_seconds, 60) % 5 === 0`. There is no slot ledger, catch-up, or missed-run replay. The external cron or supervisor invokes at most once per minute.

A due pass appends `.schedule.lock` to the canonical database path and attempts one nonblocking exclusive `flock` on an application-private filesystem shared by cooperating scheduled invocations on that host. Contention returns `overlap_skipped`. Open, non-contention acquisition, close, or unlock failure fails closed. An acquired lock is released in `finally`.

The locked pass synchronously calls the same in-process one-job operation as `jobs:run-one`, once. It does not enqueue through another path, recurse into the console, or loop. Sequential invocations in the same due minute are not deduplicated and may each perform one pass. The lock does not coordinate hosts or direct job commands; job leases and idempotency remain necessary.

See [the complete guide](../cli.md) and [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md).
