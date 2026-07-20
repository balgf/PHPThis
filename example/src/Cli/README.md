# Example application CLI source

This directory is application-owned evidence for ADR 025, not PHPThis core runtime code. `ApplicationCommandLine` parses the sole bounded argument grammar, `ApplicationCommandName` and `ApplicationCommands` own the finite map, `ApplicationCommandExecution` owns the stable success line, and `LocalScheduleLock` owns the app-private nonblocking same-host `flock` boundary.

`example/bin/console.php` is the only operational entrypoint. It composes through `ApplicationComposition::commands()`, maps unknown and invalid input separately, emits exactly one redacted stdout or stderr JSON line, and runs zero or one job operation before exit. Read `example/.ai/cli.md`, `docs/cli.md`, and ADR 025 before changing this path.

Do not add command discovery, dynamic class or service resolution, a second console, generic parser or scheduler facade, daemon, polling loop, subprocess recursion, persistent slot ledger, catch-up, or distributed-coordination claim.
