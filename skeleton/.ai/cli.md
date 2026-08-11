# Application CLI and scheduler contract

`NOT_APPLICABLE(CLI)`: the health-only starter has no operational application console, command map, typed command argument, scheduled pass, application clock, overlap lock, cron policy, supervisor, or CLI-specific output. Composer scripts and `vendor/bin/phpthis check` are development and validity gates, not an adopted application CLI.

`NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`: the starter has no local launcher command, process-profile map, private-child handoff, or launcher PHP file. A launcher used only for an adopted HTTP development process would not by itself adopt an operational application CLI.

Before adoption, read installed `vendor/phpthis/framework/docs/cli.md`, then replace this marker with:

- the sole console path and finite command-to-operation map, plus each command's configuration-profile and authority references; every adopted migration history has its own separately scoped references;
- every typed argument, exact spelling, bound, default, and pre-I/O rejection rule;
- exact exit codes, stdout and stderr JSON bytes, finite outcomes, and redaction;
- exact process identity, process-specific configuration factory, and final readonly type recorded in `.ai/configuration.md`, database-authority facts recorded in `.ai/data.md`, plus fresh per-invocation state;
- one-pass work and resource bounds; and
- real-console subprocess, failure, redaction, and resource tests.

When a scheduled pass is adopted, additionally record its explicit clock, timezone, cadence, due test, missed-run, catch-up, and repeated-slot policy; app-private overlap mechanism and namespace, topology, acquisition, expiry or cleanup, contention, failure, release, and crash behavior; cron or supervisor frequency, timeout, restart, and incident ownership; and time-boundary, not-due, overlap, and release evidence. Otherwise record those schedule-only facts as not applicable.

A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043. Do not invent a scheduler cadence, supervisor, or overlap lock for that command.

Keep framework `phpthis` dedicated to `check`. Do not add command discovery, class-name dispatch, a service-container resolver, generic console or scheduler facade, daemon, hidden loop, or distributed-coordination claim.

Record configuration names, factories, validation, injection, exact process identity, failure, rotation/restart, and secret redaction only in `.ai/configuration.md`; record database-authority facts only in `.ai/data.md`; keep only command-to-profile and authority references here.

When a local PHP launcher is deliberately adopted, read installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md` and record here only its exact `php ./bin/application <command>`-style invocation, finite launcher-command to selected-profile, exact private child entrypoint, and unchanged application-command map. It prepares the selected local environment and hands off; it does not create a second console, command alias, handler, or composition path. Keep absolute root/`PHP_BINARY`/private-child resolution and production non-use in `.ai/operations.md`, the shared canonical environment reader in `.ai/configuration.md`, and array-form shell-free `proc_open` evidence in `.ai/testing.md`.
