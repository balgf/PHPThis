# Guardrails

`php tools/guardrails.php` currently enforces repository-level invariants without third-party packages:

- every PHP file declares strict types;
- every named repository class is final (`PHT002`);
- magic methods other than constructors are absent;
- dangerous dynamic mechanisms such as `eval` and variable variables are absent;
- PDO construction occurs only inside the connection boundary;
- calls to `selectAllRows`, `selectOneRow`, and `executeStatement` do not occur inside loop headers or bodies (`PHT003`);
- exact route matching and allowed-method lookup do not contain request-time loops;
- PHP superglobals are read only in `example/public/index.php`;
- Markdown files outnumber PHP files;
- core source stays within 900 physical lines during Phase 1;
- PHPStan baseline files are absent.
- `phpstan.neon` keeps strict-rules, every strict rule, and the PHPThis extension enabled without `ignoreErrors`.

Runtime query budgets enforce a separate limit before each statement executes. Request-scoped query traces add bounded, redacted evidence about executed statement shapes, repetition, timing, and failures.

PHPStan runs separately at maximum level with strict rules. It owns static type correctness; the repository guardrail retains only lightweight structure checks until equivalent type-aware PHPStan rules exist.

`php tools/test-strict-profile.php` exercises passing and failing fixtures against the same syntax guard and PHPStan extension used by the canonical check. PHPThis-owned rule IDs are permanent and have no suppression mechanism.

`php tools/test-query-scaling.php` verifies that the accepted read remains constant at one query and explicitly submits its `.php.fixture` N+1 negative control to `PHT003`. The fixture is not accepted repository PHP; proving its rejection is part of the test.

The loop check is deliberately narrow and syntax-aware. It covers the canonical database methods; review still has to reject aliases, dynamic calls, and inefficient single statements. Guardrails should remain deterministic, fast, and locally runnable.
