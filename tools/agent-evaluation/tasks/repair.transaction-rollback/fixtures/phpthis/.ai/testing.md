# Testing

Run `composer check` from the candidate root. Public smoke tests exercise the existing behavior; add tests for the requested change. Use the supplied data-only case format and real SQLite. Do not modify observation infrastructure or rely on hidden checks.

Before fixing a PHPStan diagnostic, read `docs/phpstan/README.md` and its exact identifier file from the candidate root. This authorized offline reference read satisfies the fetch-before-fix requirement. Keep the network disabled and preserve the installed analyzer/profile; upstream ignore examples do not permit suppressions. Stop and report an uncovered identifier or unavailable reference.

Before writing or changing typed test helpers, read “Local type aliases” in `docs/phpstan/phpdoc-types.md`, plus “Array shapes”, “General arrays” or “Lists” only when that type is used. Locate those headings within this one file and read the relevant sections. This read belongs before authoring helpers even when no diagnostic has appeared.

Assign the return from `Observation::run(CaseInput)` and use its typed `response`, `query_trace`, `durable_state` and `policy_steps` members directly. For helper methods, put `@phpstan-import-type ObservationResult from App\Evaluation\Observation` in the PHPDoc on the final test class, then use `@param ObservationResult` or `@return ObservationResult` on its methods. The alias is class-scoped, not file- or free-function-scoped. Durable table entries remain `array<array-key, mixed>` because PDO's type contract does not prove a list or row value types. Counts and whole-table equality checks work directly; validate individual row values before inspecting them.

## Observation input boundaries

The case is a JSON transport envelope. Every string passed to `json_encode(..., JSON_THROW_ON_ERROR)` must be valid UTF-8. A raw malformed UTF-8 value fails serialization; malformed UTF-8 bytes or an unpaired surrogate escape in the outer JSON fail `CaseInput::fromJson()` parsing. These failures occur before the application handler and do not prove its `400` behavior. Do not catch a transport exception and count it as an application response.

ASCII control characters remain valid UTF-8 and can travel losslessly: a PHP value such as `"line\nbreak"` is escaped during JSON encoding and restored during decoding. Use such values, JSON-representable wrong types, empty strings and oversized valid strings to test application validation only in fields and sizes the case driver accepts and the task permits. Preserve the driver's schema, type and size limits.

The request body is itself a string inside the outer case. ASCII text containing malformed application JSON, including a literal unpaired `\uD800` escape, can travel in that body string when encoded normally into the outer case. Where the task accepts a JSON body, this can exercise its own parser. An unpaired escape interpreted by the outer case parser cannot deliver a malformed string to a query field.

When required bytes cannot be represented through this driver, preserve the API validation requirement and report the exact observation-transport coverage limitation. Do not silently drop the requirement or claim it was tested. Do not ignore, replace or substitute invalid UTF-8, enable partial JSON output, add a base64 convention without schema authority, construct another connection, use reflection or bypass protected observation infrastructure. This guidance authorizes no driver, schema or application-contract change.

Maintainer provenance: PHP's [json_encode](https://www.php.net/manual/en/function.json-encode.php), [json_decode](https://www.php.net/manual/en/function.json-decode.php) and [JSON constants](https://www.php.net/manual/en/json.constants.php) references. These links do not require or authorize candidate network access.
