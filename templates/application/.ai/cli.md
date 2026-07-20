# Application CLI and scheduler contract

- Adoption or `NOT_APPLICABLE(CLI)`: {{CLI_ADOPTION_OR_NOT_APPLICABLE}}
- Sole application console and deployment process identity: {{CLI_CONSOLE_AND_IDENTITY_OR_NOT_APPLICABLE}}
- Finite command names, operation owners, and one-pass resource bounds: {{CLI_COMMAND_MAP_AND_BOUNDS_OR_NOT_APPLICABLE}}
- Typed argument spellings, defaults, byte and value bounds, and pre-I/O rejection: {{CLI_ARGUMENT_POLICY_OR_NOT_APPLICABLE}}
- Exit codes, stdout and stderr JSON bytes, finite outcomes, redaction, and compatibility: {{CLI_EXIT_STREAM_POLICY_OR_NOT_APPLICABLE}}
- Immutable configuration shared with HTTP and fresh invocation-state ownership: {{CLI_COMPOSITION_POLICY_OR_NOT_APPLICABLE}}
- Explicit clock, timezone, cadence, due test, missed-run, catch-up, and repeated-slot policy: {{CLI_SCHEDULE_TIME_POLICY_OR_NOT_APPLICABLE}}
- App-private same-host lock path, permissions, filesystem topology, acquisition, contention, failure, and release: {{CLI_OVERLAP_POLICY_OR_NOT_APPLICABLE}}
- Cron or supervisor frequency, timeout, forced termination, restart, and incident policy: {{CLI_SUPERVISOR_POLICY_OR_NOT_APPLICABLE}}
- Distributed coordination: {{CLI_DISTRIBUTED_COORDINATION_OR_NOT_APPLICABLE}}
- Real-console test command and exact behavior evidence: {{CLI_EVIDENCE_OR_NOT_APPLICABLE}}

Before adoption, read installed `vendor/phpthis/framework/docs/cli.md` and ADR 025. PHPThis provides no core application CLI or scheduler API; framework `vendor/bin/phpthis` remains the checker. Keep one finite explicit console, parse arguments once into typed values, use one closed exit and stream contract, compose fresh invocation state, and keep every time, lock, supervisor, side effect, and topology decision visible.

Do not add command discovery, dynamic class or service resolution, a generic console or scheduler facade, daemon, hidden loop, unrecorded slot or catch-up behavior, or a distributed-coordination claim unsupported by a separately accepted backend-specific decision.
