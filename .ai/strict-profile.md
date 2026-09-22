# Strict profile contract

PHPThis is a checked subset of ordinary PHP. Code is not valid PHPThis merely because PHP can execute it; the complete `composer check` command must pass.

Consumer Contract version 18 carries version 17 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`.

## Universal rules

- `PHT001`: do not cast or call scalar conversion functions while the input type is still `mixed`. Validate and narrow first.
- `PHT002`: every named repository class is `final`. Use an interface for an extension point; anonymous classes remain available for local tests.
- `PHT003`: do not call `selectAllRows`, `selectOneRow`, or `executeStatement` inside the header or body of any `for`, `foreach`, `while`, or `do` loop. The universal database rule also forbids recursive per-item calls.
- `PHT004`: consuming applications cannot replace the framework-owned PHPStan configuration or suppress its profile findings.
- `PHT005`: application-owned code cannot construct `PDO` or a subclass directly. Use `Connection::connect` at the composition root.
- `PHT006`: call `Connection` directly with SQL that PHPStan natively resolves to finite non-blank compile-time constant strings. Bind data separately; do not use runtime strings, indirect calls, unpacking, or PHPDoc-only narrowing.
- `PHT007`: read application process-environment values only through `\getenv('EXACT_LITERAL_KEY')`, using one positional non-empty uppercase literal key of at most 128 bytes. All application reads belong in one PHP file; validate into process-specific final readonly values and inject them visibly. Preserve the canonical front-controller superglobal transport tuple; other environment access and mutation remain forbidden.
- `PHT008`: use a distinct exact case-sensitive named placeholder and explicit binding for every occurrence in each accepted SQL alternative, even when values repeat. Rename repeated occurrences; do not generate SQL or bindings.

## Concern detail and enforcement

The [full rule catalogue](../docs/strict-profile.md#phpthis-owned-rule-catalogue) owns exact coverage, repair guidance, and profile history. The [enforcement guide](static-analysis.md) owns checker implementation, lexical forms, and review limitations. Load those details when implementing, repairing, or reviewing the affected rule; this summary does not narrow its coverage or authorize a bypass.

PHT006 retains finite SQL-shape ownership; PHT008 alone owns distinct named-placeholder occurrences in those accepted constants. PHT007 belongs to the consumer checker's project-global structural stage. The installed PHPStan strict-rules extension owns loose comparisons, non-boolean conditions, `empty()`, short ternaries, and strict function flags; do not duplicate those rules in repository guardrails.

Rule IDs are permanent and must not be reused. Each rule needs one enforcement owner, passing and failing fixtures, exact diagnostic assertions, a catalogue update, and installed-consumer proof when PHPStan-owned. Never add baselines, inline suppressions, wildcard exclusions, or comment-based exemptions. Consumer checks use the installed checker configuration; maintainer analysis uses the reviewed `phpstan.neon`.

Runtime and deployment obligations remain in the [current Consumer Contract](../docs/consumer-contract.md) and routed guides; they are not additional PHT rules. Load the [upgrade companion](../docs/consumer-contract-upgrades.md) for contract evolution or upgrades.
