# Redis schedule-lease lifecycle

`RedisScheduleRunLease` uses this exact key:

```text
phpthis_example:<environment>:schedule_run:v1
```

Its value is one fresh 128-bit lowercase-hex owner token. Acquisition is one nonblocking `SET key token NX PX 30000`. Both connect and read timeout are 250 milliseconds, and the phpredis maximum-retries option is zero. Contention returns `overlap_skipped` before job work.

The executable proof defaults this lease to `127.0.0.1:6380`, logical database `0`, on a process separate from the cache process at `127.0.0.1:6379`, logical database `0`.

Renewal and release each use one fixed code-owned Lua script that acts only when the stored token equals the caller's token. One command invocation performs at most four explicit renewals. There is no wait, retry, polling, timer, background heartbeat, or renewal loop.

The token prevents a stale owner from changing a later owner's current key. It is not ordered and is not a fencing token. The protected SQLite job path does not accept it and retains its own claim, expiry, transaction, and idempotency checks.

Integration tests renew TTL on the real lease endpoint, let a first real owner expire before proving it cannot change its successor, and force a real Redis server rejection that must be reported as `acquire_failed` rather than contention.
