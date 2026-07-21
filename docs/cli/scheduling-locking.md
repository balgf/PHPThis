# CLI scheduling and coordination

The accepted `schedule:run` pass reads an injected Unix clock as UTC, ignores seconds, and is due only when `intdiv(epoch_seconds, 60) % 5 === 0`. There is no slot ledger, catch-up, or missed-run replay. The external cron or supervisor invokes at most once per minute.

A due pass uses `RedisScheduleRunLease`, key `phpthis_example:<environment>:schedule_run:v1`, one fresh owner token, and `SET NX PX 30000`. Connect and read timeouts are each 250 milliseconds. Contention returns `overlap_skipped`. Connection, acquisition, explicit renewal, or owner-checked release failure fails closed. Every success includes a bounded `coordination` list after `command` and `outcome`; a Redis operational failure includes the same finite evidence after `error: command_failed`. Renewal and release use fixed Lua scripts that cannot change a later owner's value.

The leased pass synchronously calls the same in-process one-job operation as `jobs:run-one`, once, with at most four explicit renewals. It does not enqueue through another path, recurse into the console, loop, wait, retry, or renew in the background. Sequential invocations in the same due minute are not deduplicated and may each perform one pass. The lease does not coordinate direct job commands or fence work after its 30-second expiry; SQLite job leases and idempotency remain necessary.

The executable proof defaults the lease to `127.0.0.1:6380`, logical database `0`, on a separate single-primary `noeviction` process from the cache at `127.0.0.1:6379`, logical database `0`. The owner token is not a fencing token, and the proof does not establish safety through Redis restart, failover, replication, partitions, pauses, clock anomalies, or uncertain client timeouts.

See [the complete guide](../cli.md), [Redis cache and schedule coordination](../redis-coordination.md), [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md), and [ADR 028](../decisions/028-application-owned-redis-cache-and-schedule-lease.md).
