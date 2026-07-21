# Redis schedule-lease failures

Connection, acquisition other than ordinary contention, renewal, and release failures fail `schedule:run` closed with one newline-terminated stderr object whose exact leading field is `"error":"command_failed"` and whose `coordination` field is a finite list of code-owned outcomes. The list has at most eight entries and never contains a Redis error, reply, endpoint, key, or owner token. The command does not attempt an alternate lock. Contention alone is the expected `overlap_skipped` outcome and appears on stdout with its own bounded coordination list.

Process termination leaves the key until its 30-second expiry. A later cooperating process may then acquire it. A pause or timeout can also let the lease expire while old work continues, so overlap remains possible.

The proof does not establish the outcome of a client timeout after the server may have applied a command, nor safety through restart, failover, asynchronous replication, partitions, clock anomalies, or an unavailable endpoint. SQLite job fencing and idempotency remain required even when every Redis call succeeds.
