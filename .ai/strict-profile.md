# Strict profile contract

PHPThis is a checked subset of ordinary PHP. Code is not valid PHPThis merely because PHP can execute it; the complete `composer check` command must pass.

Rules carried forward from profile v0:

- `PHT001`: do not cast or call scalar conversion functions while the input type is still `mixed`. Validate and narrow first.
- `PHT002`: every named repository class is `final`. Use an interface for an extension point; anonymous classes remain available for local tests.
- `PHT003`: do not call `selectAllRows`, `selectOneRow`, or `executeStatement` inside any loop.

Profile v1 carries `PHT001` through `PHT003` forward and adds:

- `PHT004`: consuming applications cannot supply a PHPStan configuration, baseline, or inline PHPStan suppression comment in place of the framework-owned analysis profile.
- `PHT005`: application-owned code cannot construct `PDO` or a subclass directly, including through an imported alias, anonymous subclass, or a value known to be `class-string<PDO>`. Use `Connection::connect` at the composition root.

Rule IDs are permanent and must not be reused. A rule needs failing and passing fixtures, exact diagnostic assertions, one enforcement owner, and a catalogue update. Do not add baselines, inline suppressions, wildcard exclusions, or comment-based exemptions for a profile rule. Consumer checks use the installed checker configuration; framework-maintainer analysis continues to use the repository's reviewed `phpstan.neon`.

The installed PHPStan strict-rules extension remains the owner of loose comparisons, non-boolean conditions, `empty()`, short ternaries, and strict flags for functions such as `in_array` and `array_search`. Do not duplicate those rules in the repository guardrail.
