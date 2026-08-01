# Application migration contract

- Adoption or `NOT_APPLICABLE(MIGRATIONS)`: {{MIGRATION_ADOPTION_OR_NOT_APPLICABLE}}
- Actual adopted migration source directory: `{{MIGRATION_SOURCE_DIRECTORY_OR_NOT_APPLICABLE}}`
- Matching application namespace: `{{MIGRATION_APPLICATION_NAMESPACE_OR_NOT_APPLICABLE}}`
- Named-connection migration ownership and explicit subdivisions or not applicable: {{MIGRATION_CONNECTION_OWNERSHIP_OR_NOT_APPLICABLE}}
- Exact engine and supported version: {{MIGRATION_ENGINE_AND_VERSION_OR_NOT_APPLICABLE}}
- Accepted engine-specific database definition or provisioning, supported namespace/control model, data-definition, authority, locking, recovery, and integration decision: {{MIGRATION_ENGINE_DECISION_SOURCE_OR_NOT_APPLICABLE}}
- Sole application console command: `{{MIGRATION_COMMAND_OR_NOT_APPLICABLE}}`
- Separately authorized migration identity and isolation from web runtime: {{MIGRATION_AUTHORITY_AND_ISOLATION_OR_NOT_APPLICABLE}}
- Exact required and explicitly prohibited migration capabilities: {{MIGRATION_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}}
- Authority activation and deactivation owner, selected transition source, and complete non-HTTP path; `GRANT` and `REVOKE` only where supported: {{MIGRATION_AUTHORITY_TRANSITION_PATH_OR_NOT_APPLICABLE}}
- Migration configuration source, factory, final readonly type, injection, failure, rotation/restart, and secret redaction: `.ai/configuration.md`
- Permanent identifier grammar and finite manifest maximum: {{MIGRATION_IDENTIFIER_AND_MANIFEST_BOUND_OR_NOT_APPLICABLE}}
- Concrete ordered unrolled manifest source: `{{MIGRATION_MANIFEST_SOURCE_OR_NOT_APPLICABLE}}`
- Canonical SHA-256 checksum byte sequence and source: {{MIGRATION_CHECKSUM_POLICY_OR_NOT_APPLICABLE}}
- Ledger schema, row maximum, parser bounds, and explicit timestamp source and representation: {{MIGRATION_LEDGER_POLICY_OR_NOT_APPLICABLE}}
- Per-migration transaction and partial-failure behavior: {{MIGRATION_TRANSACTION_POLICY_OR_NOT_APPLICABLE}}
- Same-host lock path, permissions, filesystem topology, contention, and failure behavior: {{MIGRATION_LOCK_POLICY_OR_NOT_APPLICABLE}}
- Immutable-history, forward-correction, backup, restore, and recovery policy: {{MIGRATION_RECOVERY_POLICY_OR_NOT_APPLICABLE}}
- DDL lock, timeout, maintenance-window, availability, and capacity policy: {{MIGRATION_OPERATIONS_POLICY_OR_NOT_APPLICABLE}}
- Runtime-authority activation handoff and exact-engine positive and negative verification: {{MIGRATION_RUNTIME_AUTHORITY_HANDOFF_AND_EVIDENCE_OR_NOT_APPLICABLE}}
- Release order, old/new-code compatibility, abort behavior, traffic enablement, later authority deactivation, and namespace/object-removal policy: {{MIGRATION_RELEASE_SEQUENCE_OR_NOT_APPLICABLE}}
- Exact exit, stdout/stderr, finite diagnostic, and redaction contract: {{MIGRATION_OUTPUT_POLICY_OR_NOT_APPLICABLE}}
- Integration and real-console test commands: `{{MIGRATION_TEST_COMMANDS_OR_NOT_APPLICABLE}}`
- Operational source and verified date: {{MIGRATION_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Database migrations are specialized application-owned database evolution. Read installed `vendor/phpthis/framework/docs/migrations.md` before adoption. When the application has no established migration layout, PHPThis recommends `src/Database/Migrations/` and the matching `{{APPLICATION_ROOT_NAMESPACE}}\Database\Migrations` namespace. Record the actual adopted directory and namespace above. A coherent consumer-selected alternative is authoritative; neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files, and AI must not relocate an established structure without explicit human approval.

When multiple named database connections genuinely own independent migration histories, record each connection's source path, namespace, command ownership, manifest, ledger, authority, and exact-engine evidence, plus any application-selected connection-owned subdivision. Do not prescribe or create subdivisions for a single-database application or a connection without an independently adopted migration history.

Keep every migration step in one final application-owned coordinator with one permanent identifier, complete engine-specific compile-time-constant statements at direct `Connection` calls, explicit named bindings where data exists, and a checksum covering the identifier and exact statement sequence. Name and invoke every private step method explicitly in a finite ordered unrolled manifest; no database call occurs in a loop.

Validate the complete bounded ledger and every applied checksum before pending work. Commit each pending migration and its ledger row in one visible transaction. Applied history is immutable; corrections are new forward migrations. The migration command uses fresh separately authorized state and one application-private nonblocking same-host lock and never runs during HTTP startup. Migration success alone does not prove runtime authority is active. If an authority transition is owned by a migration, its complete engine-supported transition statements are visible and checksum-covered with that step; otherwise a separately authorized application stage or named external provisioning source owns it. `GRANT` and `REVOKE` are transition forms only where the engine supports them. Activate and verify required authority before dependent code receives traffic, stop the dependent stage on failure, and drain or remove dependent code before authority deactivation or namespace/object removal.

Do not add a framework migration API, schema builder, DSL, discovery, runtime `.sql` loading, stored executable SQL or class names, generic database facade, permission helper, role registry, runtime authority introspection, automatic privilege hook, transaction callback, inferred down migration, hidden retry, or portable-DDL claim. A non-SQLite adoption requires a separate engine-specific DDL, transaction, locking, privilege, recovery, and integration decision.
