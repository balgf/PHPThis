# Testing contract

The repository currently uses a zero-dependency test runner in `tests/run.php`.

Every behavior test should name one outcome and arrange dependencies directly. For database handlers, include:

- expected rows or affected count;
- expected query count;
- a small fixture and a materially larger fixture;
- an assertion that both fixture sizes execute the same number of statements;
- an assertion that query fingerprints do not repeat unexpectedly;
- the query-budget failure path.

Inspect the in-memory query trace directly and print its redacted JSON snapshot only when useful for a focused failure. Do not write every query during the full test suite.

Boundary factories require adversarial tests for missing and unknown fields, null, arrays, objects, coercive strings, numeric overflow, malformed JSON, invalid UTF-8, and excessive nesting as applicable.

A Strict Profile rule requires at least one failing fixture, one passing fixture, and assertions for its permanent identifier and source line. Type-aware fixtures run through PHPStan; syntax guard fixtures call the same token-aware implementation used by the repository guardrail.

Do not mock fluent APIs or framework internals. Prefer real value objects and an in-memory database when its behavior matches the production database feature under test. Database-specific SQL still requires integration tests against that database.
