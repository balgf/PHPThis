# Example application CLI source

This directory is application-owned evidence for ADR 025 and ADR 028, not PHPThis core runtime code. `ApplicationCommandLine` parses the sole bounded argument grammar, `ApplicationCommandName` and `ApplicationCommands` own the finite map, `ApplicationCommandExecution` owns the stable success line, and the Redis schedule lease owns one app-private nonblocking owner-token boundary.

`example/bin/console.php` is the only operational entrypoint. It composes through `ApplicationComposition::commands()`, maps unknown and invalid input separately, emits exactly one redacted stdout or stderr JSON line, and runs zero or one job operation before exit. Every schedule success includes a bounded `coordination` list; a Redis operational failure includes the finite list beside `command_failed`. Implementation source is under `example/`, while executable evidence is under `tests/`. Read `example/.ai/cli.md`, `example/.ai/cache.md`, `docs/cli.md`, ADR 025, and ADR 028 before changing this path.

Do not add command discovery, dynamic class or service resolution, a second console, generic parser or scheduler facade, daemon, polling or renewal loop, subprocess recursion, persistent slot ledger, catch-up, generic lease API, fencing-token claim, or exactly-once claim.
