# Terminal database evidence authority

Every request-scoped connection that can execute inside one terminal coordinator path is registered in its finite code-owned source list. The list has at most eight unique names matching `[a-z][a-z0-9_]{0,31}`; no two sources share a `QueryBudget` or `QueryTrace`.

Each source exposes only its bounded budget state and unchanged redacted `QueryTrace` version-1 snapshot. Aggregates saturate at `PHP_INT_MAX`. A rejected over-budget call sets exceeded state but remains absent from the trace because PDO was not attempted.

The fingerprint identifies exact SQL shape pseudonymously; it is not encryption or anonymity. SQL, parameter names and values, DSNs, drivers, credentials, and connection metadata remain absent.

Observability does not change database execution. Keep complete raw engine-specific SQL and explicit named parameter arrays at direct `Connection` call sites. Do not add an ORM, repository, query builder, SQL generator, SQL/binding/placeholder helper, connection registry, dialect layer, per-query event, or hidden instrumentation.
