# Static analysis

PHPThis treats PHPStan as a development-time framework component. The runtime remains independent of PHPStan, but code is not considered valid until maximum-level analysis and strict rules pass.

PHPThis additionally defines a versioned Strict Profile. Repository-owned diagnostics use permanent `PHT` identifiers and repair-oriented messages. The catalogue is in `docs/strict-profile.md`.

## Why it is mandatory

Precise static feedback narrows the repair loop for an AI agent. It catches missing and implicit types, unsafe nullable access, invalid calls, incomplete iterable types, and contradictions between PHPDoc and native declarations before execution.

PHPStan generics and array shapes are compile-time contracts only. Runtime data still passes through explicit projection and command factories before entering typed application code.

## No baseline policy

This is a greenfield project. A generated baseline would turn known violations into invisible context, so baseline files are rejected by the repository guardrail. Fix the type boundary or create a narrowly tested framework rule instead.

## Consumer configuration ownership

Contract-version-1 applications do not own `phpstan.neon`. `vendor/bin/phpthis check` discovers all application PHP, creates a temporary maximum-level configuration with strict rules and the installed PHPThis extension, runs PHPStan, and removes the configuration. The application cannot weaken that command with a baseline, `ignoreErrors`, an alternate level, or an inline suppression; `PHT004` rejects those paths.

Normal checks reuse a profile-owned PHPStan cache under the resolved Composer dependency directory and use PHPStan parallel workers when the host permits a local loopback coordinator. Restricted hosts fall back to the same analysis serially. `phpthis check --debug` is an explicit diagnostic mode that prints analyzed paths and intentionally bypasses normal incremental behavior; it is not the canonical project gate.

The framework repository retains its reviewed `phpstan.neon` because it verifies maintainer source, proof fixtures, tooling, and the skeleton together. That maintainer configuration is not copied into applications.

## Division of responsibility

- PHPStan owns static type correctness and type-aware architectural rules.
- `PHT001` owns non-ignorable detection of scalar coercion from values still typed as `mixed`.
- `PHT005` owns non-ignorable, type- and name-aware detection of application PDO or PDO-subclass construction.
- `tools/guardrails.php` owns small repository invariants that are not yet PHPStan extensions.
- `QueryBudget` owns actual runtime statement limits.
- `QueryTrace` owns bounded runtime query fingerprints, execution timing, and failure counts without logging I/O.
- Integration tests own database-specific behavior and query-count invariance.

Over time, syntax checks that need type information should move from the handwritten guardrail into tested PHPStan extensions.

Phase 2 should add a type-aware rule for raw `array<string, mixed>` escaping named boundaries after the intended request and database boundaries are narrower. `PHT001` already rejects scalar coercion only when the operand remains `mixed`, so validated internal conversions such as timing calculations remain legal.
