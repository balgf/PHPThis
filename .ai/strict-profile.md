# Strict profile contract

PHPThis is a checked subset of ordinary PHP. Code is not valid PHPThis merely because PHP can execute it; the complete `composer check` command must pass.

Rules carried forward from profile v0:

- `PHT001`: do not cast or call scalar conversion functions while the input type is still `mixed`. Validate and narrow first.
- `PHT002`: every named repository class is `final`. Use an interface for an extension point; anonymous classes remain available for local tests.
- `PHT003`: do not call `selectAllRows`, `selectOneRow`, or `executeStatement` inside the header or body of any `for`, `foreach`, `while`, or `do` loop, including compound unbraced statements, PHP alternative syntax, and closures declared inside the loop.

Profile v1 carries `PHT001` through `PHT003` forward and adds:

- `PHT004`: consuming applications cannot supply a PHPStan configuration, baseline, or inline PHPStan suppression comment in place of the framework-owned analysis profile.
- `PHT005`: application-owned code cannot construct `PDO` or a subclass directly, including through an imported alias, anonymous subclass, or a value known to be `class-string<PDO>`. Use `Connection::connect` at the composition root.

Strict Profile v2 carries `PHT001` through `PHT005` forward and adds:

- `PHT006`: the SQL argument of a direct `Connection::selectAllRows`, `selectOneRow`, or `executeStatement` call must resolve natively in PHPStan to a finite set of non-blank compile-time constant strings. Arbitrary strings, runtime interpolation or concatenation, sanitizer results, argument unpacking, PHPDoc-only narrowing, first-class method callables, and callable-array indirection are rejected. Bind data values and map structural choices to finite reviewed code-owned statements or fragments, preferably complete statements.

Strict Profile v3 carries `PHT001` through `PHT006` forward and adds:

- `PHT007`: application process-environment reads use exactly `\getenv('EXACT_LITERAL_KEY')`, with one positional non-empty uppercase literal key of at most 128 bytes, and every read occurs in one application-owned PHP file. Reject unqualified direct calls, non-literal arguments, imports, literal callable indirection passed directly as a positional or named argument to supported native callback APIs under their built-in names, or held in local literal assignments later invoked directly before another assignment-operator occurrence, including through explicit closure/arrow capture, environment mutation, `$_ENV`, direct, imported, or aliased references to the global `INPUT_ENV` constant, literal `constant('INPUT_ENV')` and `constant('\\INPUT_ENV')` lookups, Apache access, indexed `$_SERVER`, and bare `$_SERVER` outside the exact `$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)` front-controller transport tuple. Dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, and downstream misuse after that handoff remain review limitations. Variables implicitly reassigned through `foreach`, destructuring, or by-reference mutation may be conservatively reported from their earlier literal assignment. Application-defined unqualified functions or `Closure` classes deliberately sharing supported native callback names are conservatively treated as those native callbacks. The consumer checker owns this project-global structural rule; PHPStan and `SyntaxProfile` do not.

Consumer Contract version 11 carries Strict Profile version 3 forward unchanged. Selecting the narrowest fixed route type, using its matching immutable accessor, keeping an application-owned request-handler decorator within ADR 033's explicit bounded shape, and ADR 045's response/session runtime behavior remain contract behavior; they are not part of PHT007.

Rule IDs are permanent and must not be reused. A rule needs failing and passing fixtures, exact diagnostic assertions, one enforcement owner, a catalogue update, and installed-consumer proof when it is PHPStan-owned. Do not add baselines, inline suppressions, wildcard exclusions, or comment-based exemptions for a profile rule. Consumer checks use the installed checker configuration; framework-maintainer analysis continues to use the repository's reviewed `phpstan.neon`.

The installed PHPStan strict-rules extension remains the owner of loose comparisons, non-boolean conditions, `empty()`, short ternaries, and strict flags for functions such as `in_array` and `array_search`. Do not duplicate those rules in the repository guardrail.
