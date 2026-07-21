# Application migration contract

`NOT_APPLICABLE(MIGRATIONS)`: the health-only starter has no database, migration command, schema authority, migration identity, manifest, checksum policy, ledger, migration lock, forward-recovery policy, or migration evidence. No migration code or dependency is included, and HTTP startup performs no schema work.

Before adoption, read installed `vendor/phpthis/framework/docs/migrations.md` and record:

- the exact engine and supported version, sole application migration command, and separately authorized process identity;
- one final application-owned coordinator, permanent identifier grammar, finite manifest maximum, explicit migration-step order, unrolled private step methods, and canonical SHA-256 checksum byte sequence;
- complete engine-specific compile-time-constant SQL at direct `Connection` calls with no database call in a loop;
- the bounded ledger schema, row maximum, parser bounds, and explicit timestamp source and representation;
- one explicit transaction per migration and ledger insert, immutable history, forward correction, and backup or restore policy;
- one application-private nonblocking same-host lock path, permissions, filesystem topology, contention, and failure policy;
- exact finite success and error outputs with complete redaction; and
- empty-database, rerun, drift, malformed and overflowing ledger, overlap, partial-failure, forward-recovery, real-console, and no-HTTP-startup tests.

Do not add a framework migration API, schema builder, DSL, discovery, runtime `.sql` loading, stored executable SQL or class names, generic database facade, transaction callback, inferred down migration, hidden retry, HTTP-startup migration, or cross-engine claim. A non-SQLite adoption requires its own engine-specific DDL, transaction, lock, privilege, recovery, and integration decision.
