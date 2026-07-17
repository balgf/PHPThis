# Static analysis

PHPThis treats PHPStan as a development-time framework component. The runtime remains independent of PHPStan, but code is not considered valid until maximum-level analysis and strict rules pass.

PHPThis additionally defines a versioned Strict Profile. Repository-owned diagnostics use permanent `PHT` identifiers and repair-oriented messages. The catalogue is in `docs/strict-profile.md`.

## Why it is mandatory

Precise static feedback narrows the repair loop for an AI agent. It catches missing and implicit types, unsafe nullable access, invalid calls, incomplete iterable types, and contradictions between PHPDoc and native declarations before execution.

PHPStan generics and array shapes are compile-time contracts only. Runtime data still passes through explicit projection and command factories before entering typed application code.

## No baseline policy

This is a greenfield project. A generated baseline would turn known violations into invisible context, so baseline files are rejected by the repository guardrail. Fix the type boundary or create a narrowly tested framework rule instead.

## Division of responsibility

- PHPStan owns static type correctness and type-aware architectural rules.
- `PHT001` owns non-ignorable detection of scalar coercion from values still typed as `mixed`.
- `tools/guardrails.php` owns small repository invariants that are not yet PHPStan extensions.
- `QueryBudget` owns actual runtime statement limits.
- `QueryTrace` owns bounded runtime query fingerprints, execution timing, and failure counts without logging I/O.
- Integration tests own database-specific behavior and query-count invariance.

Over time, syntax checks that need type information should move from the handwritten guardrail into tested PHPStan extensions.

Phase 2 should add a type-aware rule for raw `array<string, mixed>` escaping named boundaries after the intended request and database boundaries are narrower. `PHT001` already rejects scalar coercion only when the operand remains `mixed`, so validated internal conversions such as timing calculations remain legal.
