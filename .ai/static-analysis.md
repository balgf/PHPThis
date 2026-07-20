# Static analysis contract

PHPStan is part of the definition of valid PHPThis code, not an optional review tool.

Rules:

- Run PHPStan at `level: max` with the strict-rules extension.
- Keep `src/`, `example/`, `tests/`, `tools/`, and `autoload.php` free of findings.
- Do not create a PHPStan baseline.
- Do not add broad `ignoreErrors` patterns or weaken the configured level.
- Resolve `mixed` at an input boundary with validation and a typed value.
- For an ADR 021 input boundary, use PHT001 and maximum-level PHPStan to prove narrowing, the concrete command or request type, and any typed operation signature; use adversarial runtime tests for representations, bounds, deterministic generic errors, zero typed-seam calls when present, and zero operation-owned downstream work. Do not invent PHT007 for application policy.
- Add precise PHPDoc generics or array shapes only when native PHP cannot express the type.
- Prefer a tested custom PHPStan rule over a text-based exception for framework architecture.
- Give every PHPThis-owned rule a permanent `PHT` identifier and positive and negative fixtures.
- Make PHPThis-owned PHPStan findings non-ignorable; do not duplicate a rule already owned by PHPStan strict-rules.
- Enforce `PHT006` at direct `Connection` call sites: the SQL argument must be a finite union of non-blank constant strings inferred by PHPStan itself. Reject argument unpacking and first-class or callable-array indirection. Do not accept a PHPDoc assertion, `literal-string`, sanitizer return type, path exception, or suppression as a substitute.
- Treat a `match` or other finite mapping from an external structural selector to reviewed code-owned SQL as valid only when PHPStan infers the final SQL as constant strings and the unknown branch rejects the request.
- Consumer applications run the installed `phpthis check` binary; they do not own a PHPStan configuration, baseline, or inline suppression path.
- Keep Contract version 4's carried-forward `$_SESSION`, direct/imported native session call, and literal indirect-reference restrictions in the application checker's structural stage; dynamically obscured calls remain forbidden by contract, and this is not a new Strict Profile v2 `PHT` rule.
- The consumer checker must build one application-file manifest and pass that same manifest to syntax checks and PHPStan.

PHPStan proves static type and code-shape properties. It does not prove that bound data is semantically valid, a selector policy is complete, database privileges are least-privileged, statement counts are bounded, or SQL plans are acceptable. Runtime adversarial tests, authority verification, query budgets, and database integration tests remain mandatory.

ADR 021 adds no diagnostic. Consumer Contract v4 and Strict Profile v2 remain unchanged.

ADR 022 likewise adds no diagnostic. PHT006 still proves only the finite constant-string SQL argument at direct calls; runtime tests own exact SQLite semantics, explicit parameter arrays, bounded category cardinalities, cursor traversal, tenant predicates, query counts, and proof limits. Consumer Contract v4 and Strict Profile v2 remain unchanged.
