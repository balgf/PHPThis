# Application migration contract

`NOT_APPLICABLE(MIGRATIONS)`: the health-only starter has no database, migration source directory or namespace, migration command, namespace or object transition, elevated identity, authority activation path, manifest, checksum policy, ledger, migration lock, release sequence, forward-recovery policy, or migration evidence. No migration directory, code, or dependency is included, and HTTP startup performs no data-definition or authority-transition work.

Database migrations are specialized application-owned database evolution. On first adoption with no established structure, PHPThis recommends `src/Database/Migrations/` and the skeleton's matching `App\Database\Migrations` namespace. Record the actual adopted directory and namespace in this file. A coherent consumer-selected alternative is authoritative; neither PHPThis nor the consumer checker enforces the recommendation or discovers migration files, and AI must not relocate an established structure without explicit human approval.

If multiple named database connections later adopt independent migration histories, record each connection's explicit source path, namespace, command ownership, manifest, ledger, authority, and exact-engine evidence. Create an application-selected connection-owned subdivision only for an adopted independent history; do not pre-create or prescribe connection subdivisions for this database-free skeleton or a single-database application.

Before adoption, read installed `vendor/phpthis/framework/docs/migrations.md` and record:

- the actual adopted migration source directory and matching application namespace;
- the exact engine and supported version; accepted engine-specific database definition or provisioning, supported namespace/control model, data-definition, authority, locking, recovery, and integration decision; sole application migration command; and separately authorized process identity;
- the migration configuration source, factory, final readonly type, injection, failure, rotation/restart, and secret redaction in `.ai/configuration.md`;
- exact required and explicitly prohibited migration capabilities, their isolation from HTTP runtime, and one owner and complete non-HTTP path for each authority activation and deactivation, with `GRANT` and `REVOKE` only where supported;
- one final application-owned coordinator, permanent identifier grammar, finite manifest maximum, explicit migration-step order, unrolled private step methods, and canonical SHA-256 checksum byte sequence;
- complete engine-specific compile-time-constant SQL at direct `Connection` calls with no database call in a loop;
- the bounded ledger schema, row maximum, parser bounds, and explicit timestamp source and representation;
- one explicit transaction per migration and ledger insert, immutable history, forward correction, and backup or restore policy;
- one application-private nonblocking same-host lock path, permissions, filesystem topology, contention, and failure policy;
- the runtime-authority activation handoff, exact-engine positive and negative verification, application rollout and traffic-enablement order, old/new-code compatibility, abort behavior, later authority deactivation, dependent-code drain, and namespace/object-removal order;
- exact finite success and error outputs with complete redaction; and
- empty-database, rerun, drift, malformed and overflowing ledger, overlap, partial-failure, forward-recovery, real-console, and no-HTTP-startup tests.

Migration success alone does not prove runtime authority is active. Complete engine-supported transition statements belong visibly and checksum-covered in a migration, in a separately authorized application stage, or in a named external provisioning source. `GRANT` and `REVOKE` are transition forms only where the engine supports them. Activate and verify required authority before dependent code receives traffic, stop the dependent stage on failure, and drain or remove dependent code before authority deactivation or namespace/object removal.

Do not add a framework migration API, schema builder, DSL, discovery, runtime `.sql` loading, stored executable SQL or class names, generic database facade, permission helper, role registry, runtime authority introspection, automatic privilege hook, transaction callback, inferred down migration, hidden retry, HTTP-startup migration, or cross-engine claim. A non-SQLite adoption requires its own engine-specific DDL, transaction, lock, privilege, recovery, and integration decision.
