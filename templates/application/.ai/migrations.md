# Application migration contract

- Adoption or `NOT_APPLICABLE(MIGRATIONS)`: {{MIGRATION_ADOPTION_OR_NOT_APPLICABLE}}
- Exact engine and supported version: {{MIGRATION_ENGINE_AND_VERSION_OR_NOT_APPLICABLE}}
- Sole application console command: `{{MIGRATION_COMMAND_OR_NOT_APPLICABLE}}`
- Separately authorized migration identity and isolation from web runtime: {{MIGRATION_AUTHORITY_AND_ISOLATION_OR_NOT_APPLICABLE}}
- Permanent identifier grammar and finite manifest maximum: {{MIGRATION_IDENTIFIER_AND_MANIFEST_BOUND_OR_NOT_APPLICABLE}}
- Concrete ordered unrolled manifest source: `{{MIGRATION_MANIFEST_SOURCE_OR_NOT_APPLICABLE}}`
- Canonical SHA-256 checksum byte sequence and source: {{MIGRATION_CHECKSUM_POLICY_OR_NOT_APPLICABLE}}
- Ledger schema, row maximum, parser bounds, and explicit timestamp source and representation: {{MIGRATION_LEDGER_POLICY_OR_NOT_APPLICABLE}}
- Per-migration transaction and partial-failure behavior: {{MIGRATION_TRANSACTION_POLICY_OR_NOT_APPLICABLE}}
- Same-host lock path, permissions, filesystem topology, contention, and failure behavior: {{MIGRATION_LOCK_POLICY_OR_NOT_APPLICABLE}}
- Immutable-history, forward-correction, backup, restore, and recovery policy: {{MIGRATION_RECOVERY_POLICY_OR_NOT_APPLICABLE}}
- DDL lock, timeout, maintenance-window, availability, and capacity policy: {{MIGRATION_OPERATIONS_POLICY_OR_NOT_APPLICABLE}}
- Exact exit, stdout/stderr, finite diagnostic, and redaction contract: {{MIGRATION_OUTPUT_POLICY_OR_NOT_APPLICABLE}}
- Integration and real-console test commands: `{{MIGRATION_TEST_COMMANDS_OR_NOT_APPLICABLE}}`
- Operational source and verified date: {{MIGRATION_SOURCE_AND_VERIFIED_DATE_OR_NOT_APPLICABLE}}

Read installed `vendor/phpthis/framework/docs/migrations.md` before adoption. Keep every migration step in one final application-owned coordinator with one permanent identifier, complete engine-specific compile-time-constant statements at direct `Connection` calls, explicit named bindings where data exists, and a checksum covering the identifier and exact statement sequence. Name and invoke every private step method explicitly in a finite ordered unrolled manifest; no database call occurs in a loop.

Validate the complete bounded ledger and every applied checksum before pending work. Commit each pending migration and its ledger row in one visible transaction. Applied history is immutable; corrections are new forward migrations. The migration command uses fresh separately authorized state and one application-private nonblocking same-host lock and never runs during HTTP startup.

Do not add a framework migration API, schema builder, DSL, discovery, runtime `.sql` loading, stored executable SQL or class names, generic database facade, transaction callback, inferred down migration, hidden retry, or portable-DDL claim. A non-SQLite adoption requires a separate engine-specific DDL, transaction, locking, privilege, recovery, and integration decision.
