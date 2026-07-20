# Durable-job testing

An adopted durable-job path requires automated behavior evidence. At minimum prove:

- commit publishes the business change and job together, while any statement failure rolls both back;
- valid envelopes dispatch through the finite map and invalid JSON, shape, version, type, and bounds never reach a handler;
- idle, success, retry, maximum-attempt dead letter, poison dead letter, and expired-final-lease termination;
- exact retry delays, no early claim, lease expiry and redelivery with a new token, and zero-row stale or expired-token finalization;
- duplicate semantic delivery records one idempotent database effect;
- a real worker subprocess terminated after claim is recoverable only after lease expiry;
- each fresh process handles at most one row and multiple rows require multiple invocations;
- durable diagnostics and terminal output remain bounded and omit all submitted, stored, SQL, credential, exception, and external-response values; and
- query budgets, bounded traces, and statement fingerprints remain constant across materially different queue cardinalities.

Use a real file-backed SQLite fixture with the deployed feature level. Tests must control the application clock explicitly and sample it again before every fenced transition; a claim-time snapshot is not sufficient proof that a lease remains current after handler work.

See [the complete durable-jobs guide](../jobs.md) and [ADR 024](../decisions/024-application-owned-sqlite-durable-jobs.md).
