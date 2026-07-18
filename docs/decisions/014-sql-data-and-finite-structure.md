# ADR 014: Bound SQL data and finite SQL structure

Status: accepted

## Context

Visible SQL is not automatically safe SQL. PDO placeholders can represent complete data literals, but they cannot represent identifiers, keywords, operators, ordering directions, or arbitrary fragments. Passing a final SQL string to `Connection` also cannot prove how that string was assembled.

PHPThis needs a rule that an AI can apply and the complete check can verify without adding a query builder, dialect abstraction, generic SQL sanitizer, or runtime parser. Database authorization remains a separate defense: safe statement construction does not justify giving a web process schema-owner or administrative credentials.

## Decision

Strict Profile version 2 adds `PHT006`. Direct calls to `Connection::selectAllRows`, `Connection::selectOneRow`, and `Connection::executeStatement` must receive SQL whose native inferred type is one or more non-blank compile-time constant strings. A literal, a native constant, a nowdoc or heredoc with no dynamic interpolation, and a finite `match` or conditional composed only of constant strings are valid. A general `string`, concatenation or interpolation involving a non-constant value, an argument unpack, an annotation that merely claims a dynamic string is constant, a first-class callable, or callable-array indirection is rejected.

Application data never enters SQL structure. Every data value uses a unique named parameter, including values that were already validated. Each occurrence has its own placeholder. PDO parameters are not used for identifiers, keywords, operators, directions, or fragments because PDO does not support those positions.

When an operation genuinely needs variable SQL structure, application code maps a typed choice to a finite set of complete, code-owned, reviewed statements. A finite code-owned fragment choice is acceptable when keeping the complete statement repeated would be less clear, but the resulting SQL must still be inferred as a finite constant-string set. Unknown external choices are rejected at the boundary rather than sanitized, stripped, escaped, or silently converted to a default. PHPThis does not add a generic identifier validator, SQL sanitizer, query builder, SQL template engine, or driver dialect layer.

Applications also apply least privilege per runtime connection. Runtime credentials receive only the database objects and actions required by that process. Schema ownership, migrations, role or grant administration, and other administrative authority use a separately delivered credential that is unavailable to the web runtime. Applications record the engine-specific authority and verification evidence in `.ai/data.md`; systems without database roles, such as SQLite, record the equivalent file and process boundary instead of claiming grant enforcement.

## Evidence and limits

PHT006 proves the inferred finite shape of SQL passed through the three canonical direct `Connection` calls. Runtime tests separately prove that SQL-looking strings round-trip as bound data, remain outside query traces, and do not change statement count. Engine integration tests prove the application SQL and the recorded runtime authority against the deployed database version.

This is not universal SQL-injection proof. PHT006 does not parse SQL, prove authorization, inspect stored procedures, audit dynamic SQL executed inside the database, validate grants, or follow reflection and other non-canonical invocation mechanisms. A finite statement can still be destructive, logically wrong, overprivileged, or vulnerable inside a stored procedure. Security review and engine-specific tests remain required.

The framework transport harness uses fixed, code-owned table names so it also passes PHT006. It creates and drops those tables, so MySQL and PostgreSQL certification must run only against a disposable or dedicated test database with credentials intentionally authorized for that fixture. It does not pre-drop a table and cleans up only after that run created it; an interrupted run can therefore require a reset of the dedicated database. Those credentials are not a production least-privilege example.

## Consequences

The accepted database call site exposes a small finite statement set to static analysis while keeping SQL ordinary, engine-specific PHP strings. Data binding and structure selection have distinct repairs, which reduces the chance that an AI treats validation or escaping as permission to interpolate.

Applications may repeat complete statements when that is the clearest finite choice. Least-privilege policy remains application-owned because PDO transport cannot create a portable authorization model.

## References

- [PHP manual: `PDO::prepare`](https://www.php.net/manual/en/pdo.prepare.php)
- [PHP manual: SQL injection](https://www.php.net/manual/en/security.database.sql-injection.php)
- [OWASP SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html)

## Reconsider when

PHPStan cannot preserve the finite native type through an ordinary reviewed PHP construct, an engine requires a statement mechanism outside the three canonical calls, or application evidence demonstrates a smaller rule with equal protection and fewer false positives. Do not reconsider in order to accept arbitrary strings or hide SQL construction behind a generic helper.
