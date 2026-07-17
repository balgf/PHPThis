# Strict profile contract

PHPThis is a checked subset of ordinary PHP. Code is not valid PHPThis merely because PHP can execute it; the complete `composer check` command must pass.

PHPThis-owned rules in profile v0:

- `PHT001`: do not cast or call scalar conversion functions while the input type is still `mixed`. Validate and narrow first.
- `PHT002`: every named repository class is `final`. Use an interface for an extension point; anonymous classes remain available for local tests.
- `PHT003`: do not call `selectAllRows`, `selectOneRow`, or `executeStatement` inside any loop.

Rule IDs are permanent and must not be reused. A rule needs failing and passing fixtures, exact diagnostic assertions, one enforcement owner, and a catalogue update. Do not add baselines, inline suppressions, wildcard exclusions, or comment-based exemptions for a profile rule.

The installed PHPStan strict-rules extension remains the owner of loose comparisons, non-boolean conditions, `empty()`, short ternaries, and strict flags for functions such as `in_array` and `array_search`. Do not duplicate those rules in the repository guardrail.
