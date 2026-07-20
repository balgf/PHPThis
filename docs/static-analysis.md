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

Contract-version-4 applications do not own `phpstan.neon`. `vendor/bin/phpthis check` discovers all application PHP, creates a temporary maximum-level configuration with strict rules and the installed PHPThis extension, runs PHPStan, and removes the configuration. The application cannot weaken that command with a baseline, `ignoreErrors`, an alternate level, or an inline suppression; `PHT004` rejects those paths.

Normal checks reuse a profile-owned PHPStan cache under the resolved Composer dependency directory and use PHPStan parallel workers when the host permits a local loopback coordinator. Restricted hosts fall back to the same analysis serially. `phpthis check --debug` is an explicit diagnostic mode that prints analyzed paths and intentionally bypasses normal incremental behavior; it is not the canonical project gate.

The framework repository retains its reviewed `phpstan.neon` because it verifies maintainer source, proof fixtures, tooling, and the skeleton together. That maintainer configuration is not copied into applications.

## Division of responsibility

- PHPStan owns static type correctness and type-aware architectural rules.
- `PHT001` owns non-ignorable detection of scalar coercion from values still typed as `mixed`.
- `PHT005` owns non-ignorable, type- and name-aware detection of application PDO or PDO-subclass construction.
- `PHT006` owns non-ignorable detection of non-finite, blank, annotation-only, unpacked, or indirectly invoked SQL at the three canonical `Connection` database methods.
- `tools/guardrails.php` owns small repository invariants that are not yet PHPStan extensions.
- The application checker's structural stage owns the native-session restrictions introduced by Contract version 3 and carried by version 4: rejection of `$_SESSION`, direct/imported native session calls, and literal indirect references in consumer code; dynamically obscured calls remain a contract violation. This carries Strict Profile version 2 forward without adding a `PHT` rule.
- `QueryBudget` owns actual runtime statement limits.
- `QueryTrace` owns bounded runtime query fingerprints, execution timing, and failure counts without logging I/O.
- Integration tests own database-specific behavior and query-count invariance.

Over time, syntax checks that need type information should move from the handwritten guardrail into tested PHPStan extensions.

ADR 021 does not add `PHT007`. `PHT001` already rejects scalar coercion while an external value remains `mixed`, and maximum-level PHPStan verifies the typed result after explicit narrowing. A broad ban on trimming, enum conversion, date parsing, array functions, or other potentially valid boundary operations would have no reliable understanding of application policy. Consumer Contract version 4 and Strict Profile version 2 remain unchanged.

PHT006 uses PHPStan's native inferred type rather than trusting PHPDoc to turn an arbitrary string into a constant. It accepts finite compile-time choices, not merely one literal spelling, so an operation may select between reviewed engine-specific statements without adding a query builder. It does not parse SQL or perform general taint analysis: stored procedures, server-side dynamic SQL, reflection, authorization, and actual database privileges remain outside its proof.

Phase 2 should add a type-aware rule for raw `array<string, mixed>` escaping named boundaries after the intended request and database boundaries are narrower. `PHT001` already rejects scalar coercion only when the operand remains `mixed`, so validated internal conversions such as timing calculations remain legal.
