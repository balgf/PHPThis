# Application CLI knowledge index

Use this index when a task concerns an operational application command, scheduled pass, or the explicit SQLite migration command. Read [the complete CLI and scheduler guide](../cli.md), ADR 025, and [ADR 027](../decisions/027-application-owned-explicit-sqlite-migrations.md) when migration work applies; these smaller pages route an AI to the relevant invariant without inventing a framework console.

- [Arguments and output](arguments-output.md): sole entrypoint, finite job, scheduler, and migration commands, bounded arguments, exit codes, stream bytes, and redaction.
- [Scheduling and locking](scheduling-locking.md): explicit UTC cadence, one-pass work, app-private same-host `flock`, and known non-deduplication limits.
- [Composition](composition.md): immutable configuration shared between fresh HTTP, CLI, and migration graphs without a container or mutable request state.
- [Testing](testing.md): real-console subprocess, time, overlap, migration-history, failure, redaction, and resource-bound evidence.

All pages describe one application-owned pattern. Framework `bin/phpthis` remains the installed checker. PHPThis adds no core command, registry, parser, scheduler, clock, lock, migration, schema, daemon, process manager, discovery, or distributed-coordination API.
