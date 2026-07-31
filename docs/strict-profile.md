# PHPThis Strict Profile

Profile version: 3

PHPThis runs as ordinary PHP 8.4.x. The profile is a smaller set of accepted programs enforced during development; it is not a PHP fork, transpiler, runtime wrapper, or second language. The supported Composer range excludes PHP 8.5 until that runtime receives separate review and CI evidence.

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
| `PHT007` | Process-environment reads use exactly `\getenv('EXACT_LITERAL_KEY')`, with one positional non-empty uppercase literal key of at most 128 bytes, and every read in the application-owned Composer project occurs in one PHP file. Unqualified direct calls, non-literal arguments, imports, literal callable indirection passed directly as a positional or named argument to supported native callback APIs under their built-in names, or held in local literal assignments later invoked directly before another assignment-operator occurrence, including through explicit closure/arrow capture, mutation, `$_ENV`, direct, imported, or aliased references to the global `INPUT_ENV` constant, literal `constant('INPUT_ENV')` and `constant('\\INPUT_ENV')` lookups, Apache access, indexed `$_SERVER`, and bare `$_SERVER` outside the exact front-controller transport tuple are rejected. | Consumer checker's structural `EnvironmentAccessProfile`, including its project-wide one-file check. | Put all exact reads in one application-owned configuration-boundary file, validate and narrow immediately into process-specific final readonly types, and inject only the required type through visible composition. |

PHT identifiers are permanent. Wording may become clearer, but a materially different or broader rule receives a new identifier or a new profile version. Profile rules have no inline suppression, baseline, wildcard exclusion, or comment-based exemption mechanism.

ADR 030's possible-duplication output is deliberately absent from this catalogue. It is a bounded report-only review advisory with no `PHT` identifier, suppression mechanism, or effect on program validity. Promoting it into this profile requires a separate decision and migration; a possible group does not authorize automatic refactoring.

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

Profile v3 does not yet ban every raw mixed array, associative domain array, mutable object inside a readonly value, unbounded result, dependency crossing, or undeclared exception. Current infrastructure has legitimate instances of several of those shapes. They need narrower contracts before enforcement can avoid false positives.

PHT006 is deliberately limited to the three canonical direct `Connection` calls. It does not parse SQL, prove that a finite statement is safe or authorized, inspect stored procedures or server-side dynamic SQL, validate database grants, or claim coverage for reflection and non-canonical invocation. Behavior tests, engine integration tests, least-privilege verification, and security review remain required.

PHT007 proves canonical direct process-environment access, direct positional or named arguments to supported native callback consumers under their built-in names, directly invoked local literal assignments before another assignment-operator occurrence, and one-file confinement only. It does not detect hard-coded secrets, dynamically constructed function or constant names, aliased native callback API names, application-defined, anonymous, or variable-dispatched callable consumers, reassigned callable variables, argument-unpacked callable values, local callable variables passed onward as callback arguments, arbitrary secret-manager APIs, downstream misuse after the accepted transport handoff, leaks, correct parsing, deployment permissions, or least database privilege. Variables implicitly reassigned through `foreach`, destructuring, or by-reference mutation may be conservatively reported from their earlier literal assignment. Application-defined unqualified functions or `Closure` classes deliberately sharing supported native callback names are conservatively treated as those native callbacks. Bare `$_SERVER` remains valid only as the exact `$coordinator->handle($_SERVER, $_GET, $_POST, $_FILES)` front-controller transport tuple; indexed access and other bare uses are rejected.

## Upgrade from profile version 2

Profile version 3 carries PHT001 through PHT006 forward unchanged and adds PHT007. Before upgrading, inventory every process-environment read, choose one application-owned PHP file, replace each read with a direct `\getenv('EXACT_LITERAL_KEY')` call, and immediately narrow the results into process-specific final readonly types. Remove imports, wrappers, superglobal indexing, environment mutation, and callable indirection. Keep runtime, migration, and administrative names and factories separate without fallback, then run the complete application gate.

## Upgrade from profile version 1

Profile version 2 carries PHT001 through PHT005 forward unchanged and adds PHT006. Before upgrading, audit every direct `Connection` database call. Replace arbitrary SQL variables, dynamic interpolation, argument unpacking, and callable indirection with direct calls whose SQL resolves from native PHP code to a finite non-blank constant-string set. Keep all data in unique named parameters and reject unknown structural choices at the input boundary. Run the complete project check after removing any PHPDoc annotation that merely claims a dynamic SQL string is constant.
