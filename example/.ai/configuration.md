# Example application configuration context

This bundled example is repository-owned executable evidence. It is excluded from the installed framework package and is not the standalone skeleton consumer checked by `ApplicationChecker`; those consumers still require their own Contract-version-10 `.ai/configuration.md`. This file records the example's actual configuration boundary without inventing deployment credentials or forcing process-environment use.

## Sources and typed values

- Process-environment boundary: `NOT_APPLICABLE(PROCESS_ENVIRONMENT)`. No PHP file under `example/` calls `getenv`, reads `$_ENV`, uses `INPUT_ENV`, or loads dotenv.
- HTTP composition: `example/bootstrap.php` creates one validated `ApplicationDatabasePath` from the code-owned local `tmp/example.sqlite` path, passes it to `ApplicationComposition`, and calls only `http()`.
- CLI and migration composition: `ApplicationCommandLine::fromArguments()` validates the code-owned default or the optional exact `--database=/absolute/path` argument into `ApplicationDatabasePath`; `example/bin/console.php` passes that value to a fresh `ApplicationComposition` and calls only `commands()`.
- Redis cache and schedule lease: `ApplicationComposition` receives validated non-secret host, port, and environment values. The checked-in executable entrypoints use the documented code-owned local defaults; focused tests may construct the composition with explicit synthetic values.
- Credentials and elevated secret values: `NOT_APPLICABLE`. SQLite uses a local file path and the proof supplies no username, password, token, private key, or administrative fallback.

`ApplicationComposition` is visible construction code, not an environment reader, configuration service, generic bag, facade, container, or object injected into behavior. Sharing this class between entrypoints does not share a process or mutable state. HTTP reaches only `http()`; migration construction is reachable only from the explicit CLI `database:migrate` branch. The local SQLite proof separates those paths in code and policy but does not prove production operating-system identities or database grants; `.ai/data.md` owns that limitation.

## Validation, failure, lifecycle, and disclosure

`ApplicationDatabasePath`, `ApplicationCommandLine`, and the `ApplicationComposition` constructor validate their inputs before the affected database, Redis, or migration work. HTTP composition rejects an unavailable code-owned database file with a generic application exception before request handling or database I/O; this bundled proof does not claim fixed real-SAPI startup bytes for that pre-coordinator failure. The console maps malformed arguments to its fixed exit-2 error and operational failures to its finite redacted exit-1 contract.

Configuration is immutable for one fresh HTTP or console composition. A path, endpoint, port, or environment change takes effect only in a newly constructed process; there is no hidden reload. Application-owned console output, terminal summaries, traces, and durable diagnostics omit database paths, Redis endpoints, lease keys and tokens, DSNs, credentials, submitted option bytes, exceptions, SQL, and bindings.

Evidence lives in the repository behavior tests for application database-path parsing, command-line parsing, distinct and fresh HTTP/CLI composition, migration exclusion from HTTP startup, Redis endpoint separation, exact failures, and redaction. These tests exercise synthetic local values only.
