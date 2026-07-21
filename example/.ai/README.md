# Example AI context index

This compact context routes work in the executable example. It is evidence-oriented application context, not a traditional framework manual.

Always read `../AGENTS.md`, then load only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Change document SQL, ordering, cursor, categories, query cost, or engine claims | `.ai/data.md`, `../../docs/decisions/022-application-owned-finite-data-paths.md` | `src/Documents/ListDocuments/`, `src/Documents/DocumentRoutes.php`, composition in `bootstrap.php`, and document-list tests |
| Change document-file upload, storage, download, or emission | `.ai/file-transfers.md`, `../../docs/file-transfers/README.md` | `public/index.php`, `ApplicationComposition`, `src/DocumentFiles/`, and document-file, upload-boundary, and response-emitter tests |
| Change document authentication, tenant resolution, or authorization | `.ai/data.md`, `../../docs/request-policy.md`, ADR 020 | shared types under `src/Documents/`, action-specific authorization interface, handler order, error registration, and policy tests |
| Change the protected document cache, cache invalidation, Redis failure behavior, or schedule lease | `.ai/cache.md`, `.ai/data.md`, `.ai/cli.md`, `.ai/observability.md`, `../../docs/redis-coordination.md`, ADR 028 | protected Get handler order, bounded cache parser, Redis cache and lease endpoints, owner-token scripts, terminal evidence, cold-cache SQL proof, cache tests, and Redis integration |
| Change user Create, List, or Get | `../../.ai/crud.md` and the relevant installed decision | concrete `src/Users/` operation and tests |
| Change request correlation, terminal summaries, or query-source registration | `.ai/observability.md`, `../../docs/observability/README.md`, ADR 023 | `src/Observability/`, `bootstrap.php`, `public/index.php`, distinct operation budgets and traces, summary and throwing-sink tests |
| Change durable-job publication, envelopes, worker lifecycle, retries, or dead letters | `.ai/jobs.md`, `.ai/data.md`, `.ai/observability.md`, `../../docs/jobs.md`, ADR 024 | Create transaction, `src/Jobs/`, one-shot worker entrypoint, complete SQLite SQL and bindings, job tests, and subprocess crash proof |
| Change an application command, argument, exit, stream, cadence, or overlap policy | `.ai/cli.md`, `.ai/jobs.md`, `.ai/observability.md`, `../../docs/cli.md`, ADR 025, ADR 028 | `bin/console.php`, `ApplicationComposition`, `src/Cli/`, `src/Coordination/`, explicit clock, one-job operation, Redis lease endpoint and outcome trace, and real-console tests |
| Change schema migrations, migration history, or migration recovery | `.ai/migrations.md`, `.ai/data.md`, `.ai/cli.md`, `../../docs/migrations.md`, ADR 027 | `bin/console.php`, `ApplicationComposition`, `src/Migrations/`, finite manifest, exact SQLite SQL and checksums, bounded ledger, migration lock, setup delegation, and migration tests |
| Change framework behavior | leave the example boundary and follow `../../.ai/README.md` | framework `src/`, contract, decisions, and repository tests |

The document-list proof keeps complete raw SQLite SQL and explicit named parameter arrays at direct `Connection` call sites. It has no ORM, query builder, repository, generic paginator, SQL/binding/placeholder helper, generated or dynamic SQL, transaction callback, or dialect abstraction.
