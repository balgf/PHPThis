# Static analysis contract

PHPStan is part of the definition of valid PHPThis code, not an optional review tool.

Rules:

- Run PHPStan at `level: max` with the strict-rules extension.
- Keep `src/`, `example/`, `tests/`, `tools/`, and `autoload.php` free of findings.
- Do not create a PHPStan baseline.
- Do not add broad `ignoreErrors` patterns or weaken the configured level.
- Resolve `mixed` at an input boundary with validation and a typed value.
- Add precise PHPDoc generics or array shapes only when native PHP cannot express the type.
- Prefer a tested custom PHPStan rule over a text-based exception for framework architecture.
- Give every PHPThis-owned rule a permanent `PHT` identifier and positive and negative fixtures.
- Make PHPThis-owned PHPStan findings non-ignorable; do not duplicate a rule already owned by PHPStan strict-rules.
- Consumer applications run the installed `phpthis check` binary; they do not own a PHPStan configuration, baseline, or inline suppression path.
- The consumer checker must build one application-file manifest and pass that same manifest to syntax checks and PHPStan.

PHPStan proves static type and code-shape properties. Runtime query budgets and database integration tests remain mandatory because static analysis cannot prove actual statement counts or SQL plans.
