# Optional development Workbench contract

Read `docs/workbench.md`, ADR 041, `.ai/application-context.md`, and `.ai/testing.md` before changing PHPThis Workbench guidance or integration. When the workspace exposes a real side effect, also read `docs/security.md` and `.ai/database.md` plus `docs/database.md` when data is involved, then inspect the corresponding `skeleton/.ai/data.md`, `skeleton/.ai/integrations.md`, `skeleton/.ai/operations.md`, `templates/application/.ai/data.md`, `templates/application/.ai/integrations.md`, and `templates/application/.ai/operations.md` consumer context. When job behavior is involved, also read `.ai/jobs.md`, `.ai/cli.md`, `docs/jobs.md`, and `docs/cli.md`.

PHPThis Workbench belongs to the separate `phpthis/workbench` development package. Keep `phpthis/framework` free of Workbench PHP, runtime dependencies, service containers, discovery, generic dispatch, and interactive or production commands. Keep framework `vendor/bin/phpthis` dedicated to `check`.

Required boundary:

- one checked project-relative application bootstrap returns exactly one concrete final named object exposed as `$workspace`;
- one expression enters a fresh strict PHP child over standard input and its result is inspected with native `var_dump()`;
- no expression argument, noninteractive batch mode, HTTP or remote endpoint, persisted variables, object state, command history, automatic application booting, or hidden infrastructure composition;
- no execution timeout or CPU, memory, resource, or operating-system termination isolation; a hanging expression blocks the next prompt until externally interrupted;
- no sandbox, redaction, dry-run, output-bound, production-safety, authorization, or validity claim;
- the operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access, native functions and Composer-autoloaded code, and explicitly composed dependencies together define authority, with production, migration, administrative, and unrelated credentials absent;
- the narrow workspace limits only the intended application surface and is not arbitrary-PHP containment;
- direct deferred-work handler exploration remains distinct from real publication through the existing adopted business producer transaction, queued delivery through the application's recorded finite one-delivery command, and production one-off execution; no Workbench-only publisher or second enqueue path; and
- retained behavior moves into checked application source and automated tests.

Skeleton and template context may contain `NOT_APPLICABLE(WORKBENCH)` without installing the package. Existing consumers are not invalid merely because `.ai/workbench.md` is absent. Do not add the dependency or Composer script to the skeleton without approved adoption, verified availability from the approved Composer source, and clean consumer-install evidence.
