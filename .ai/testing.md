# Testing contract

The repository currently uses a zero-dependency test runner in `tests/run.php`.

Every behavior test should name one outcome and arrange dependencies directly. For database handlers, include:

- expected rows or affected count;
- expected query count;
- a small fixture and a materially larger fixture;
- an assertion that both fixture sizes execute the same number of statements;
- an assertion that query fingerprints do not repeat unexpectedly;
- the query-budget failure path.

`composer test:database-drivers` certifies the narrow PDO transport boundary. It defaults to SQLite; the dedicated CI job sets `PHPTHIS_DATABASE_TEST_DRIVERS=sqlite,mysql,pgsql` and supplies real services. Do not treat an unconfigured driver as a pass or fold driver-specific SQL into a runtime dialect abstraction.

Inspect the in-memory query trace directly and print its redacted JSON snapshot only when useful for a focused failure. Do not write every query during the full test suite.

Boundary factories require adversarial tests for missing and unknown fields, null, arrays, objects, coercive strings, numeric overflow, malformed JSON, invalid UTF-8, and excessive nesting as applicable.

`RequestReader` tests must cover method/path normalization, header normalization and collisions, non-string runtime values, query-key and metadata-count bounds, an exact-limit body, an oversized declared body, an oversized actual body, and a mismatched `Content-Length`. Error-boundary tests must prove exact-class mapping, public-message non-disclosure, zero side effects for rejected client input, and unchanged rethrowing of unknown failures.

A Strict Profile rule requires at least one failing fixture, one passing fixture, and assertions for its permanent identifier and source line. Type-aware fixtures run through PHPStan; syntax guard fixtures call the same token-aware implementation used by the repository guardrail.

The consumer proof must install a mirrored framework package into a fresh temporary project, run the public checker and application behavior tests, execute the real front controller, and submit adversarial files outside conventional source roots. It must also prove that consumer PHPStan configuration, baselines, and inline ignores cannot weaken the profile. The local package-archive proof must compare the complete Composer and Git export inventory with the explicit release allowlist. Alpha publication separately verifies the actual Packagist-preferred dist because a local archive cannot prove hosting-provider output.

An intentionally invalid performance control uses a `.php.fixture` suffix so it cannot be mistaken for accepted repository PHP. A proof must pass its source to the real Strict Profile checker, assert the stable rejection, execute it only in an isolated subprocess, and compare its output with the accepted implementation. Never exclude an invalid `.php` file from guardrails.

Do not mock fluent APIs or framework internals. Prefer real value objects and an in-memory database when its behavior matches the production database feature under test. Database-specific SQL still requires integration tests against that database.
