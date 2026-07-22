# Application data contract

`NOT_APPLICABLE`: the starter has no database, persisted resource, or CRUD-shaped behavior. It therefore has no SQL, structural selectors, bounded data lists, database identities or privileges, migrations, CRUD resource identifiers or item/collection routes, pagination, create identity or conflicts, `PUT`/`PATCH` or concurrency policy, missing-resource semantics, deletion or retention policy, resource authorization, or audit events.

`NOT_APPLICABLE(MIGRATIONS)`: `.ai/migrations.md` owns any future migration engine, command, manifest, checksum, ledger, authority, transaction, lock, recovery, and evidence decision. Do not infer schema authority from this general data file.

`NOT_APPLICABLE(CACHE)`: the starter has no typed cache projection, authoritative rebuild source, versioned key schema, environment or tenant cache namespace, payload parser or bound, TTL or staleness bound, invalidation policy, or corruption and eviction behavior.

`NOT_APPLICABLE(REQUEST_POLICY)`: the starter has no authentication, tenant-resolution, authorization, or protected-handler connection, query budget, trace, protected write, or authorization-to-write race.

Before adding data access, record every connection's engine and version, PDO extension, configuration source, schema and dialect authority, integration command, scale, query budget, indexes, and transaction constraints. Also record each SQL structural choice and its finite code-owned mapping, every bounded-list shape and maximum cardinality, each cursor's version, stable tie-break and snapshot policy, the least-privileged runtime identity and required capabilities, explicitly prohibited runtime capabilities, the separate migration or administrative identity, the isolation mechanism, and the evidence source and verification date.

Every SQL data value must use a distinct named binding for each occurrence. SQL must execute through a direct `Connection` call and resolve natively in PHPStan to finite non-blank compile-time constants. Unknown selectors and unsupported list shapes must fail before database work; do not introduce a sanitizer. Before CRUD-shaped behavior, also record all operation semantics above; the optional layout does not decide them.

`NOT_APPLICABLE(RESOURCE_ROUTE_IDENTIFIERS)`: before adopting a resource route, record its narrowest fixed declaration among `positive-int`, `uuid`, `ulid`, and genuinely opaque `token`, the matching `PathParameters` accessor, the application-owned route-specific identifier wrapper, and any narrower validation performed before database work. Route matching returns unchanged routing metadata; it does not normalize, bind or look up a record, generate an identifier, choose persistence, or fall back between types.

Before cache adoption, record each narrowly named service and owned projection, the authoritative source that can reproduce it, a versioned environment- and tenant-scoped key schema, bounded parsed payload shape, finite TTL and staleness limit, post-commit invalidation and failure policy, and corruption, eviction, and miss behavior. Treat cache payloads as untrusted external input and never as a source of truth.
