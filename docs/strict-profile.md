# PHPThis Strict Profile

Profile version: 2

PHPThis runs as ordinary PHP 8.4. The profile is a smaller set of accepted programs enforced during development; it is not a PHP fork, transpiler, runtime wrapper, or second language.

`composer check` is the compiler-like gate. In an application, it runs the installed `phpthis check` profile stage followed by application-owned behavior tests. In the framework repository, it additionally runs maintainer guardrails, permanent profile fixtures, the query-scaling proof, and framework behavior tests. A program that skips its complete gate may be valid PHP but is not verified PHPThis.

## PHPThis-owned rule catalogue

| ID | Rule | Enforcement | Repair |
| --- | --- | --- | --- |
| `PHT001` | Scalar coercion from `mixed` is forbidden. This covers `(int)`, `(float)`, `(string)`, `(bool)`, `intval`, `floatval`, `doubleval`, `strval`, `boolval`, and `settype`, including template-mixed inputs. | Non-ignorable, type-aware PHPStan rule `phpthis.pht001`. | Check the runtime type and accepted representation first, then convert only the narrowed value. Known-type internal conversions remain valid. |
| `PHT002` | Every named class in checked PHP is `final`; abstract classes also fail. | Shared token-aware syntax profile used by repository and application guardrails. | Mark the class final or expose an interface as the explicit extension point. Anonymous classes, interfaces, traits, and enums are not rejected. |
| `PHT003` | `selectAllRows`, `selectOneRow`, and `executeStatement` cannot occur lexically in a `for`, `foreach`, `while`, or `do` header or body. Matching is case-insensitive and covers braced bodies, direct and compound unbraced statements, PHP alternative syntax, and closures declared inside the loop. | Shared token-aware syntax profile plus the framework's executable N+1 negative control. | Replace per-item I/O with one set-based query before the loop. Recursive execution and dynamically named method calls remain review limitations. |
| `PHT004` | A consuming application cannot replace or suppress the framework-owned static-analysis profile with a reserved `phpstan*.neon`, `phpstan*.neon.dist`, or `phpstan*baseline*.php` artifact, or an inline `@phpstan-ignore` comment. | The installed application checker rejects alternate configuration and suppression; the maintainer guard rejects baselines and inline suppression in framework source. | Remove the alternate configuration or suppression, run the complete check, and repair the underlying diagnostic. |
| `PHT005` | Application-owned code cannot construct `PDO` or a `PDO` subclass directly, including through an import, alias, fully qualified name, anonymous subclass, or a value known to be `class-string<PDO>`. The framework `Connection` is the sole PDO-construction boundary. | Non-ignorable, type- and name-resolving PHPStan rule `phpthis.pht005`. | Call `PHPThis\Database\Connection::connect` in the composition root and inject that connection into database behavior. |
| `PHT006` | Direct calls to `Connection::selectAllRows`, `selectOneRow`, and `executeStatement` require SQL whose native inferred type is one or more non-blank compile-time constant strings. Dynamic strings, blank variants, interpolation or concatenation with a non-constant value, argument unpacking, PHPDoc-only narrowing, first-class callables, and callable-array indirection are rejected. | Non-ignorable, type- and name-aware PHPStan rules under `phpthis.pht006`. | Keep a literal, native constant, or non-interpolated nowdoc/heredoc at the direct call. Map a genuine structural choice to a finite set of complete reviewed constant statements, and pass every data value separately through a unique named parameter. |

PHT identifiers are permanent. Wording may become clearer, but a materially different or broader rule receives a new identifier or a new profile version. Profile rules have no inline suppression, baseline, wildcard exclusion, or comment-based exemption mechanism.

## Inherited strict rules

The PHPStan strict-rules extension remains the sole owner of these type-aware language restrictions:

- loose `==` and `!=` comparisons;
- non-boolean conditions;
- `empty()` and short ternaries;
- omitted strict flags for `in_array`, `array_search`, `base64_decode`, and value-filtered `array_keys`;
- variable variables, dynamic static calls, and other enabled strict-rules checks.

PHPThis explicitly fixes `strictRules.allRules` to `true` so dependency defaults cannot silently weaken the profile. In consuming applications, the installed checker creates this configuration outside the project and forwards no user PHPStan options.

## Adding a rule

A proposed rule must begin with a demonstrated failure that matters to AI-generated application code. It must have one clear repair, low false-positive risk, one enforcement owner, failing and passing fixtures, and an architectural decision when it changes the accepted programming model.

Type-sensitive behavior belongs in PHPStan. Fast syntax or repository-shape invariants may remain in `tools/guardrails.php`. Runtime cost and external representations require executable runtime tests rather than static claims.

## Deliberately deferred

Profile v2 does not yet ban every raw mixed array, associative domain array, mutable object inside a readonly value, unbounded result, dependency crossing, or undeclared exception. Current infrastructure has legitimate instances of several of those shapes. They need narrower contracts before enforcement can avoid false positives.

PHT006 is deliberately limited to the three canonical direct `Connection` calls. It does not parse SQL, prove that a finite statement is safe or authorized, inspect stored procedures or server-side dynamic SQL, validate database grants, or claim coverage for reflection and non-canonical invocation. Behavior tests, engine integration tests, least-privilege verification, and security review remain required.

## Upgrade from profile version 1

Profile version 2 carries PHT001 through PHT005 forward unchanged and adds PHT006. Before upgrading, audit every direct `Connection` database call. Replace arbitrary SQL variables, dynamic interpolation, argument unpacking, and callable indirection with direct calls whose SQL resolves from native PHP code to a finite non-blank constant-string set. Keep all data in unique named parameters and reject unknown structural choices at the input boundary. Run the complete project check after removing any PHPDoc annotation that merely claims a dynamic SQL string is constant.
