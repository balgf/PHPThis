# Static analysis

PHPThis treats PHPStan as a development-time framework component. The runtime remains independent of PHPStan, but code is not considered valid until maximum-level analysis and strict rules pass.

PHPThis additionally defines a versioned Strict Profile. Repository-owned diagnostics use permanent `PHT` identifiers and repair-oriented messages. The catalogue is in `docs/strict-profile.md`.

## Why it is mandatory

Precise static feedback narrows the repair loop for an AI agent. It catches missing and implicit types, unsafe nullable access, invalid calls, incomplete iterable types, and contradictions between PHPDoc and native declarations before execution.

PHPStan generics and array shapes are compile-time contracts only. Runtime data still passes through explicit operation-request, command, or projection factories before entering typed application code.

For ADR 021, PHPStan verifies each concrete command or request type and, when present, the typed operation signature receiving it. It cannot prove that a JSON representation is canonical, bounds are appropriate, normalization preserves product meaning, public errors are disclosure-safe, authorization occurred, or invalid input was excluded from downstream operation work. Those properties remain operation-owned runtime evidence.

## No baseline policy

This is a greenfield project. A generated baseline would turn known violations into invisible context, so baseline files are rejected by the repository guardrail. Fix the type boundary or create a narrowly tested framework rule instead.

## Consumer configuration ownership

Contract-version-10 applications do not own `phpstan.neon`. `vendor/bin/phpthis check` discovers all application PHP, creates a temporary maximum-level configuration with strict rules and the installed PHPThis extension, runs PHPStan, and removes the configuration. The application cannot weaken that command with a baseline, `ignoreErrors`, an alternate level, or an inline suppression; `PHT004` rejects those paths.

Normal checks reuse a profile-owned PHPStan cache under the resolved Composer dependency directory and use PHPStan parallel workers when the host permits a local loopback coordinator. Restricted hosts fall back to the same analysis serially. `phpthis check --debug` is an explicit diagnostic mode that prints analyzed paths and intentionally bypasses normal incremental behavior; it is not the canonical project gate.

The framework repository retains its reviewed `phpstan.neon` because it verifies maintainer source, proof fixtures, tooling, and the skeleton together. That maintainer configuration is not copied into applications.

## Consumer source manifest

`phpthis check` discovers every application-owned PHP file and gives the same deterministic manifest to structural profile checks, the report-only duplication scan, and PHPStan. Structural checks and the duplication scan reuse one captured source read. The checker excludes only the resolved Composer dependency directory and version-control metadata; source under `config/`, `bin/`, migrations, hidden directories, or `tmp/` remains application-owned and checked.

PHP files use the `.php` extension. Extensionless executables beginning with `<?php` or `#!/usr/bin/env php` followed by `<?php` are also checked. A canonical PHP opening prefix under another extension is rejected rather than silently excluded. Symlinked source directories and checked source files are rejected instead of silently skipped. PHT007 uses this complete manifest to enforce one process-environment-reading file, and the ordinary redacted Composer/configuration consistency check correlates canonical literal input names from that boundary with root Composer script text.

## Report-only duplication review

ADR 030 adds one lexical advisory over the same consumer manifest used by structural checks and PHPStan, reusing the source captured by the structural read. It requires 48 consecutive normalized tokens, ignoring ordinary `<?php` opening tags, comments, docblocks, and whitespace while preserving identifiers and literal bytes for exact internal comparison. Candidate SHA-256 indexes are always verified token-for-token and never appear in output. Long overlapping windows consolidate into maximal groups; one clone can carry several locations, including separated copies in the same file.

The scan inspects at most 4,096 manifest entries, accepts at most 32 KiB per file and 2 MiB of source in total for tokenization, and retains at most 16,384 tokens per file, 24,000 total tokens, and 24,000 windows. It evaluates at most four exact variants per candidate digest, 2,048 maximal-extension attempts, 1,000,000 exact comparisons, 256 groups, 256 locations per group, and 32,768 containment-propagation checks. Debug output shows at most ten groups and eight locations per group, with JSON-safe application-relative paths limited to 160 source bytes. Reaching a bound prints one incomplete advisory; an exception caught during report generation prints one fixed unavailable advisory without exception text. Both continue to PHPStan and have no effect on its exit status. The direct suite also exercises the near-window and dense-token limits under a 64 MiB subprocess budget.

Normal mode prints only the no-match pass or possible-group summary. `--debug` intentionally adds application-relative filenames and line ranges, normalized token and location counts, and bounded truncation notices. The duplication advisory in neither mode prints PHP source snippets or normalized token text—including source-level symbols, identifiers, literals, and comments—and it omits hashes, absolute paths, and parser details. Relative filenames and line topology are review data, so application paths must not contain secrets or customer data. The scan has no `PHT` identifier, suppression, baseline, consumer configuration, automatic rewrite, or validity effect.

This first pass intentionally misses blocks below 48 tokens, renamed identifiers or values, reordered and semantically equivalent code, non-PHP copies, and cap-skipped files. It may flag valid explicit SQL, finite unrolled behavior, security steps, or independent tests. The signal asks an AI and accountable human to review intent; it does not instruct them to generalize the code.

## Division of responsibility

- PHPStan owns static type correctness and type-aware architectural rules.
- `PHT001` owns non-ignorable detection of scalar coercion from values still typed as `mixed`.
- `PHT005` owns non-ignorable, type- and name-aware detection of application PDO or PDO-subclass construction.
- `PHT006` owns non-ignorable detection of non-finite, blank, annotation-only, unpacked, or indirectly invoked SQL at the three canonical `Connection` database methods.
- `PHT007` owns canonical direct process-environment reads and their confinement to one application-owned PHP file. `EnvironmentAccessProfile` runs in the consumer checker's structural stage because the one-file decision needs the complete application manifest.
- `PHT008` owns non-ignorable detection of a repeated exact case-sensitive portable named placeholder within any one PHT006-finite SQL alternative at the three canonical `Connection` database methods.
- `tools/guardrails.php` owns small repository invariants that are not yet PHPStan extensions.
- `ApplicationDuplicationScanner` owns a bounded lexical review advisory; it does not own valid-code diagnostics or refactoring policy.
- The application checker's structural stage also owns the native-session restrictions introduced by Contract version 3: rejection of `$_SESSION`, direct/imported native session calls, and literal indirect references in consumer code; dynamically obscured calls remain a contract violation without a separate `PHT` rule.
- `QueryBudget` owns actual runtime statement limits.
- `QueryTrace` owns bounded runtime query fingerprints, execution timing, and failure counts without logging I/O.
- Integration tests own database-specific behavior and query-count invariance.

Over time, syntax checks that need type information should move from the handwritten guardrail into tested PHPStan extensions.

ADR 021 adds no general input-parsing diagnostic. `PHT001` already rejects scalar coercion while an external value remains `mixed`, and maximum-level PHPStan verifies the typed result after explicit narrowing. A broad ban on trimming, enum conversion, date parsing, array functions, or other potentially valid boundary operations would have no reliable understanding of application policy. PHT007 is reserved for canonical process-environment access; it does not validate the resulting application-specific value. ADR 032 route representations and ADR 033 decorator behavior remain contract, runtime, test, and review responsibilities rather than extensions of PHT007.

ADR 022 adds no data-path diagnostic. PHT006 proves the eight document-list SQL arguments remain a finite compile-time set at direct calls; PHT008 separately proves that every recognized named-placeholder occurrence within each alternative is distinct. Neither inspects the explicit parameter arrays, SQLite semantics, tenant predicates, list cardinalities, cursor traversal, query counts, authorization, or injection safety. Those remain application runtime and review evidence. ADR 026 likewise uses native `RequestUpload`, `RequestUploadError`, and `LocalFileBody` types plus runtime transport, provenance, filesystem, framing, and real-SAPI tests; it does not broaden PHT007. ADR 053 adds no core type or diagnostic. Consumer Contract version 15 carries version 14 forward under Strict Profile version 4; PHT007 remains configuration-boundary-only.

PHT006 uses PHPStan's native inferred type rather than trusting PHPDoc to turn an arbitrary string into a constant. It accepts finite compile-time choices, not merely one literal spelling, so an operation may select between reviewed engine-specific statements without adding a query builder. It does not parse SQL or perform general taint analysis: stored procedures, server-side dynamic SQL, reflection, authorization, and actual database privileges remain outside its proof.

PHT008 examines each PHT006-finite alternative independently through a fail-closed common lexical subset. It recognizes exact case-sensitive `:[A-Za-z_][A-Za-z0-9_]*` names while ignoring doubled-terminator single- and double-quoted segments; boundary-valid PostgreSQL `E'...'` escape strings; terminated non-nested block comments whose opener is neither `/*!` nor `/*+`; `--` only when the following byte is `0x00` through `0x20` or input ends; and structural multi-colon runs. It scans ambiguous SQLite bracket text, backslash quote escapes in ordinary quotes, every backtick and `#` spelling, PostgreSQL dollar-quote-looking text, no-space `--`, MySQL executable or optimizer-hint block comments, nested block comments, and unterminated lexical forms fail-closed. After one of those unsupported or ambiguous openers is encountered outside an already ignored region, the rest of that SQL alternative is scanned without further lexical exclusions, so nested quote- or comment-looking bytes cannot hide occurrences. It does not compare SQL with the bindings array, normalize case, parse positional placeholders or SQL syntax, select a dialect, or install a runtime parser. The repair is explicit: rename repeated occurrences and bind the repeated logical value separately under every distinct name, or rewrite ambiguous placeholder-looking text into an unambiguous accepted lexical form.

PHT007 accepts only direct `\getenv('EXACT_LITERAL_KEY')` reads in one file and rejects the fixed alternate forms catalogued in the Strict Profile. It does not search for secret-looking literals, infer which values are credentials, inspect arbitrary secret-manager APIs, or prove validation, non-disclosure, deployment safety, or privilege separation.

Phase 2 should add a type-aware rule for raw `array<string, mixed>` escaping named boundaries after the intended request and database boundaries are narrower. `PHT001` already rejects scalar coercion only when the operand remains `mixed`, so validated internal conversions such as timing calculations remain legal.
