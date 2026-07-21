# CLI arguments and output

The accepted example has one application entrypoint and three commands:

```text
php example/bin/console.php <jobs:run-one|schedule:run|database:migrate> [--database=/absolute/path]
```

The command is first. At most one exact `--database=` token follows. Its value is 1–4,096 bytes, host-absolute, free of ASCII control bytes and DEL, and has no trailing slash or backslash. Unknown command and invalid, duplicate, reordered, alternate, or extra arguments fail before application I/O. Submitted text never selects a class, callback, service, executable, or SQL.

Every expected result exits `0` and writes one newline-terminated stdout object; stderr is empty. Job and migration success use exact key order `command`, then `outcome`. Schedule success adds `coordination` as the third field: `not_due` uses `[]`, contention uses `["connected","contended"]`, and the demonstrated owned pass uses `["connected","acquired","renewed","released"]`. Unknown command and invalid arguments exit `2` with only `unknown_command` or `invalid_arguments` on stderr. Non-Redis operational and unexpected failures exit `1` with only `command_failed`. A Redis lease operational failure exits `1` with exact key order `error`, then `coordination`; the error remains `command_failed` and the list contains at most eight code-owned outcomes. Every migration failure exits `1` with exact key order `error`, `reason`, then `migration`; `error` is `migration_failed`, the finite reasons are `busy`, `checksum_drift`, `history_invalid`, `ledger_unavailable`, `apply_failed`, and `lock_failed`, and `migration` is a code-owned manifest identifier or `null`. Stored or submitted identifiers are never reflected. Error exits leave stdout empty.

The finite job outcomes are `idle`, `completed`, `retry_scheduled`, and `dead_lettered`; scheduling also permits `not_due` and `overlap_skipped`; migration success is `applied` or `up_to_date`. Coordination output excludes Redis endpoints, keys, values, owner tokens, raw replies, and exception details. All output excludes submitted values, paths, DSNs, credentials, exception details, SQL, bindings, ledger and schema contents, job data, and domain values.

See [the complete guide](../cli.md), [ADR 025](../decisions/025-application-owned-explicit-cli-and-scheduler.md), and [ADR 027](../decisions/027-application-owned-explicit-sqlite-migrations.md).
