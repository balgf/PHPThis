# Application CLI and scheduler contract

`NOT_APPLICABLE(CLI)`: the health-only starter has no operational application console, command map, typed command argument, scheduled pass, application clock, overlap lock, cron policy, supervisor, or CLI-specific output. Composer scripts and `vendor/bin/phpthis check` are development and validity gates, not an adopted application CLI.

Before adoption, read installed `vendor/phpthis/framework/docs/cli.md`, then replace this marker with:

- the sole console path and finite command-to-operation map;
- every typed argument, exact spelling, bound, default, and pre-I/O rejection rule;
- exact exit codes, stdout and stderr JSON bytes, finite outcomes, and redaction;
- immutable configuration shared with HTTP plus fresh per-invocation state;
- explicit clock, timezone, cadence, one-pass bound, missed-run, catch-up, and repeated-slot policy;
- app-private same-host lock path, permissions, filesystem topology, contention, failure, and release behavior;
- cron or supervisor frequency, timeout, restart, and incident ownership; and
- real-console subprocess, time-boundary, overlap, failure, redaction, and resource tests.

Keep framework `phpthis` dedicated to `check`. Do not add command discovery, class-name dispatch, a service-container resolver, generic console or scheduler facade, daemon, hidden loop, or distributed-coordination claim.
