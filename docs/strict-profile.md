# PHPThis Strict Profile

Profile version: 0

PHPThis runs as ordinary PHP 8.4. The profile is a smaller set of accepted programs enforced during development; it is not a PHP fork, transpiler, runtime wrapper, or second language.

`composer check` is the compiler-like gate. It runs repository guardrails, PHPStan at maximum level with strict and PHPThis-owned rules, strict-profile fixture tests, and behavior tests. A program that skips this gate may be valid PHP but is not verified PHPThis.

## PHPThis-owned rule catalogue

| ID | Rule | Enforcement | Repair |
| --- | --- | --- | --- |
| `PHT001` | Scalar coercion from `mixed` is forbidden. This covers `(int)`, `(float)`, `(string)`, `(bool)`, `intval`, `floatval`, `doubleval`, `strval`, `boolval`, and `settype`, including template-mixed inputs. | Non-ignorable, type-aware PHPStan rule `phpthis.pht001`. | Check the runtime type and accepted representation first, then convert only the narrowed value. Known-type internal conversions remain valid. |
| `PHT002` | Every named class in repository-owned PHP is `final`; abstract classes also fail. | Token-aware repository guardrail. | Mark the class final or expose an interface as the explicit extension point. Anonymous classes, interfaces, traits, and enums are not rejected. |
| `PHT003` | `selectAllRows`, `selectOneRow`, and `executeStatement` cannot occur lexically in a `for`, `foreach`, `while`, or `do` header or body, including a nested closure. | Token-aware repository guardrail. | Replace per-item I/O with one set-based query before the loop. Recursive query execution remains a review limitation. |

PHT identifiers are permanent. Wording may become clearer, but a materially different or broader rule receives a new identifier or a new profile version. Profile rules have no inline suppression, baseline, wildcard exclusion, or comment-based exemption mechanism.

## Inherited strict rules

The PHPStan strict-rules extension remains the sole owner of these type-aware language restrictions:

- loose `==` and `!=` comparisons;
- non-boolean conditions;
- `empty()` and short ternaries;
- omitted strict flags for `in_array`, `array_search`, `base64_decode`, and value-filtered `array_keys`;
- variable variables, dynamic static calls, and other enabled strict-rules checks.

PHPThis explicitly fixes `strictRules.allRules` to `true` so dependency defaults cannot silently weaken the profile.

## Adding a rule

A proposed rule must begin with a demonstrated failure that matters to AI-generated application code. It must have one clear repair, low false-positive risk, one enforcement owner, failing and passing fixtures, and an architectural decision when it changes the accepted programming model.

Type-sensitive behavior belongs in PHPStan. Fast syntax or repository-shape invariants may remain in `tools/guardrails.php`. Runtime cost and external representations require executable runtime tests rather than static claims.

## Deliberately deferred

Profile v0 does not yet ban every raw mixed array, associative domain array, mutable object inside a readonly value, unbounded result, dependency crossing, or undeclared exception. Current infrastructure has legitimate instances of several of those shapes. They need narrower contracts before enforcement can avoid false positives.
