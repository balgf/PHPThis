# Application CLI and scheduler contract

- Adoption or `NOT_APPLICABLE(CLI)`: {{CLI_ADOPTION_OR_NOT_APPLICABLE}}
- Sole application console executable: `{{CLI_CONSOLE_EXECUTABLE_OR_NOT_APPLICABLE}}`
- Command-specific configuration-profile and authority references, including a separate reference for every adopted migration history: {{CLI_COMMAND_PROFILE_AND_AUTHORITY_REFERENCES_OR_NOT_APPLICABLE}}
- Finite command names, operation owners, and one-pass resource bounds: {{CLI_COMMAND_MAP_AND_BOUNDS_OR_NOT_APPLICABLE}}
- Typed argument spellings, defaults, byte and value bounds, and pre-I/O rejection: {{CLI_ARGUMENT_POLICY_OR_NOT_APPLICABLE}}
- Exit codes, stdout and stderr JSON bytes, finite outcomes, redaction, and compatibility: {{CLI_EXIT_STREAM_POLICY_OR_NOT_APPLICABLE}}
- Exact process identity, process-specific configuration factory, and final readonly type: `.ai/configuration.md`; database-authority facts: `.ai/data.md`
- Fresh invocation-state ownership and visible composition: {{CLI_COMPOSITION_POLICY_OR_NOT_APPLICABLE}}
- Scheduled-pass clock, timezone, cadence, due test, missed-run, catch-up, and repeated-slot policy: {{CLI_SCHEDULE_TIME_POLICY_OR_NOT_APPLICABLE}}
- Scheduled-pass overlap mechanism and namespace, topology, acquisition, expiry or cleanup, contention, failure, release, crash behavior, and coordination limit: {{CLI_OVERLAP_POLICY_OR_NOT_APPLICABLE}}
- Scheduled-pass cron or supervisor frequency, timeout, forced termination, restart, and incident policy: {{CLI_SUPERVISOR_POLICY_OR_NOT_APPLICABLE}}
- Scheduled-pass distributed coordination: {{CLI_DISTRIBUTED_COORDINATION_OR_NOT_APPLICABLE}}
- Real-console test command and exact behavior evidence, plus scheduled-pass evidence when adopted: {{CLI_EVIDENCE_OR_NOT_APPLICABLE}}

Before adoption, read installed `vendor/phpthis/framework/docs/cli.md` and ADR 025. PHPThis provides no core application CLI or scheduler API; framework `vendor/bin/phpthis` remains the checker. Keep one finite explicit console, parse arguments once into typed values, use one closed exit and stream contract, compose fresh invocation state, and bound each command to one pass. Complete the clock, cadence, overlap, and supervisor fields only when a scheduled pass is adopted; otherwise record them as not applicable. A migration-only console records writer coordination or serialization in `.ai/migrations.md` under ADR 043 rather than inventing a CLI overlap lock. Record configuration names, factories, validation, injection, exact process identity, failure, rotation/restart, and secret redaction only in `.ai/configuration.md`; record database-authority facts only in `.ai/data.md`; keep only command-to-profile and authority references here.

Do not add command discovery, dynamic class or service resolution, a generic console or scheduler facade, daemon, hidden loop, unrecorded slot or catch-up behavior, or a distributed-coordination claim unsupported by a separately accepted backend-specific decision.
