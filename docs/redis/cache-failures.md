# Redis document-cache failures

Read outcomes distinguish hit, miss, corrupt value, and backend failure. Miss, expiry, eviction, corruption, wrong ownership, and backend outage each perform the same one bounded authoritative SQLite read. The phpredis maximum-retries option is zero, so there is no hidden reconnect or Redis command retry and no default document value.

A successful SQLite read remains the selected result if the following cache write fails, and the trace records `write: backend_unavailable`. If the authoritative read itself throws, the original failure propagates after the already-determined cache read outcome is recorded with `write: not_attempted`. A committed title update remains committed if invalidation fails. `DocumentDetailsCacheTrace` records each finite outcome without retaining keys, values, endpoints, credentials, exception messages, or domain values.

Cache failure may increase latency and SQLite load. Capacity, timeout, alerting, and overload policy remain deployment-owned. A warm-cache availability test does not replace cold-cache SQL scaling or query-budget evidence.
