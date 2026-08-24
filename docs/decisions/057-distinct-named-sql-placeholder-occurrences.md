# ADR 057: Distinct named SQL placeholder occurrences

Status: accepted

## Context

PHPThis already requires every SQL placeholder occurrence to have a distinct portable name. ADR 012 records that repeated named placeholders behave differently across native PDO drivers, and ADR 014 keeps application data in unique named parameters while `PHT006` limits each canonical direct `Connection` call to a finite set of non-blank compile-time constant SQL strings.

That contract was not mechanically complete. `Connection` rejects invalid parameter-array names and duplicate keys after normalizing one optional leading colon, but it does not compare those keys with occurrences in SQL. `PHT006` proves only the native constant-string shape and deliberately does not parse SQL. A consumer could therefore pass `composer check` with one name used more than once in an otherwise finite statement and then depend on driver-specific prepared-statement behavior.

Adding a runtime SQL parser would conflict with ADR 014's check-time boundary, would inspect arbitrary strings after the accepted finite-static boundary, and would consume framework core reserved for unrelated accepted work. Folding occurrence analysis into `PHT006` would materially broaden a permanent diagnostic whose documented responsibility is finite SQL shape.

On 2026-08-23 in Asia/Manila, the accountable human approved a separate check-time rule, its compatibility migration, Strict Profile version 4, and Consumer Contract version 15. This approval accepts the framework boundary only. It selects no release identity and authorizes no candidate commit, tag, package, GitHub release, announcement, or other publication operation.

## Decision

Strict Profile version 4 carries `PHT001` through `PHT007` forward unchanged and adds permanent diagnostic `PHT008`. At each direct `Connection::selectAllRows`, `Connection::selectOneRow`, or `Connection::executeStatement` call, `PHT008` examines every non-blank compile-time constant SQL alternative already accepted by `PHT006`. Alternatives are checked independently. If the same exact portable named data placeholder occurs more than once in one alternative, that call is invalid PHPThis.

A named data placeholder begins with one colon followed by an ASCII letter or underscore and then zero or more ASCII letters, digits, or underscores. Names are compared exactly and case-sensitively. Runs of `::` structural colons are not placeholders. This is a fail-closed common lexical subset, not a dialect parser. It ignores colon spellings inside single- and double-quoted segments using doubled terminators; boundary-valid PostgreSQL `E'...'` escape strings; terminated non-nested `/* */` comments whose opener is neither `/*!` nor `/*+`; and `--` only when the following byte is `0x00` through `0x20` or input ends. Ambiguous SQLite bracket text, backslash quote escapes in ordinary quotes, every backtick and `#` spelling, PostgreSQL dollar-quote-looking text, no-space `--`, MySQL executable or optimizer-hint block comments, nested block comments, and unterminated lexical forms receive no dialect-specific exclusion and are scanned fail-closed. After one of those unsupported or ambiguous openers is encountered outside an already ignored region, the rest of that SQL alternative is scanned without further lexical exclusions, so nested quote- or comment-looking bytes cannot hide occurrences. It does not parse statement meaning or positional `?` parameters, validate SQL syntax, select an engine dialect, compare the parameter array with SQL, validate stored procedures or server-side dynamic SQL, or accept arbitrary runtime SQL.

The repair is explicit at the application call site. Rename each occurrence and provide each value under its corresponding distinct parameter key, even when two occurrences deliberately receive the same value:

```php
$connection->selectOneRow(
    'SELECT :first_value AS first_value, :second_value AS second_value',
    [':first_value' => $value, 'second_value' => $value],
);
```

The parameter array retains `Connection`'s existing grammar and normalization: a key may omit or include one leading colon, while prefixed and unprefixed forms resolving to the same key remain invalid. `PHT008` adds no placeholder-renaming helper, binding helper, statement rewrite, query builder, SQL generator, sanitizer, dialect abstraction, or runtime fallback.

`PHT008` is enforced by the installed PHPStan extension under identifier `phpthis.pht008` with the non-ignorable diagnostic:

```text
[PHT008] Connection SQL must use a distinct named placeholder for each occurrence; rename repeated placeholders and bind each value separately.
```

One direct call produces one finding when any accepted finite alternative repeats an exact name. The rule does not change `Connection`, PDO options, query-budget accounting, query-trace recording, exception propagation, framework runtime dependencies, or the 2,618-line core. A consumer that bypasses the complete check has bypassed the accepted enforcement boundary and is not verified PHPThis; this decision does not claim per-call runtime rejection.

## Compatibility migration

Before adopting Consumer Contract version 15 and Strict Profile version 4, an application must:

1. Run the installed `phpthis check` over every application-owned PHP file and inventory each `PHT008` finding.
2. For each finding, rename every repeated placeholder occurrence so that the complete statement alternative uses one distinct portable name per occurrence.
3. Add the corresponding explicit parameter-array entry for each renamed occurrence. Preserve the original value and type when several occurrences intentionally receive the same data; do not rewrite or generate SQL.
4. Keep each SQL argument within `PHT006`'s finite non-blank constant-string boundary, retain application-owned engine-specific statements, and verify every changed statement against each recorded engine and version.
5. Retain or add tests for SQL-looking bound values, every structural selector, rejected unsupported shapes, query budgets, bounded traces, and the application's exact statement behavior, then run the complete application gate on PHP 8.4.x.

When a finding arises from dialect-ambiguous placeholder-looking text rather than a data placeholder, rewrite that text into an unambiguous accepted form: use doubled single or double quote terminators, a boundary-valid PostgreSQL `E'...'` escape string, an ordinary terminated non-nested block comment, or `--` followed by byte `0x00` through `0x20` as applicable. Do not add a suppression or depend on bracket, ordinary-quote backslash-escape, backtick, MySQL `#` comment, PostgreSQL dollar-quote, no-space-`--`, MySQL executable or optimizer-hint block-comment, nested-comment, or unterminated interpretation.

A later prerelease containing this decision must identify newly rejected repeated placeholder occurrences and the migration above as an intentional prerelease compatibility break.

## Evidence and limits

Permanent PHPStan fixtures prove one exact `PHT008` finding for repeated occurrences and accept distinct occurrences, finite alternatives, doubled-terminator single and double quoted segments, boundary-valid PostgreSQL `E'...'` escape strings, ordinary terminated non-nested block comments, `--` followed by byte `0x00` through `0x20`, PostgreSQL casts, and structural multi-colon runs. Negative controls prove that ambiguous SQLite bracket text, ordinary-quote backslash escapes, backticks, line-leading and inline `#`, PostgreSQL dollar-quote-looking text, no-space `--`, MySQL executable and optimizer-hint block comments, nested block comments, and unterminated lexical forms are scanned fail-closed, including compositions that place otherwise ignored quote- or comment-looking bytes after the ambiguous opener. Installed-consumer evidence submits the direct repeated and repaired shapes through the public checker.

The SQLite runtime boundary and the maintained SQLite, MySQL, and PostgreSQL transport harness bind the same logical value through separately named occurrences, including parameter keys with and without one optional leading colon. The existing invalid and normalized-duplicate parameter-key controls still fail before budget or trace changes. The driver harness retains its exact statement, failure, over-budget, and trace accounting.

This evidence proves only distinct occurrence names at the checked direct calls and the exercised transport shape. It does not prove that every placeholder has a matching parameter key, that no unused key exists, that a statement is safe, authorized, logically correct, or efficient, or that stored procedures and server-side dynamic SQL are safe. Parameterization, PHT006, PHT008, application behavior tests, exact-engine integration tests, least privilege, and security review remain complementary evidence rather than universal SQL-injection certification.

## Consequences

The complete application gate now rejects a documented portability violation before application execution while preserving raw engine-specific SQL and explicit parameter ownership at each direct call. Existing contract-compliant consumers change no SQL. A consumer relying on repeated placeholder names must make the duplicated data flow visible through distinct names and bindings.

Strict Profile version 4 and Consumer Contract version 15 are intentional prerelease compatibility changes. They add one PHPStan-owned validity rule and no runtime parser, framework database abstraction, public runtime API, dependency, or core line.

## Reconsider when

A certified engine changes the accepted portable placeholder grammar, PHPStan cannot preserve finite alternatives needed by ordinary reviewed application SQL, or real application evidence exposes a lexical ambiguity that cannot be handled without rejecting valid recorded engine-specific statements. Reconsider the narrow checked grammar and its exact migration; do not add runtime SQL rewriting, generated placeholders, a query builder, or a dialect abstraction.
