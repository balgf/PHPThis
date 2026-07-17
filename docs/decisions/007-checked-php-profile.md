# ADR 007: Checked PHP profile

Status: accepted

## Context

PHP permits coercion, dynamic mechanisms, broad arrays, and runtime-only failures that create a large search space for AI-generated code. Prose conventions alone do not reliably prevent those programs, while replacing PHP or adding another runtime would increase operational and contextual complexity.

## Decision

PHPThis defines a versioned, checked subset of ordinary PHP. `composer check` is the compiler-like validity gate. PHPThis-owned rules use permanent `PHT` identifiers, one enforcement owner, deterministic repairs, and positive and negative fixtures.

Profile v0 contains `PHT001` for mixed scalar coercion, `PHT002` for final named classes, and `PHT003` for database calls in loops. Existing PHPStan strict rules continue to own loose comparisons, boolean conditions, ambiguous shorthand, and strict standard-library calls.

The runtime remains plain PHP. PHPThis does not introduce a fork, transpiler, runtime reflection layer, universal result or collection abstraction, or another language in the normal request path.

## Consequences

Some legal PHP programs are intentionally invalid PHPThis. Static-analysis and guardrail tooling becomes part of the framework interface and requires the same review and testing discipline as runtime code. Diagnostics give an AI a bounded repair path, while known-type conversions and other legitimate base-PHP behavior remain available.

## Reconsider when

Real applications demonstrate that a rule blocks a necessary explicit design, or PHP gains a native guarantee that fully replaces a profile rule. A public `phpthis check` binary may be added when the profile is consumed outside this repository; until then, the Composer command remains the single entry point.
