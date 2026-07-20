# Correlation ID authority

The application generates 16 cryptographically secure random bytes during request-scoped composition before bounded request ingestion and encodes them with lowercase hexadecimal output. The accepted grammar is exactly `[0-9a-f]{32}`.

The coordinator owns the response field: it removes every case-insensitive application response spelling of `X-Request-ID` and writes the generated value once. It never derives the value from request data or echoes an arbitrary incoming header.

The terminal scope begins when `TerminalRequestCoordinator::handle` starts. Composition or bootstrap failure before that call has no summary or response-correlation guarantee. See ADR 023 for scope and `docs/security.md` for disclosure constraints.
