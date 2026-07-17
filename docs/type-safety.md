# Type safety

PHP scalar casts are coercive even under `declare(strict_types=1)`. Strict types protect typed calls and returns; they do not validate PDO rows, decoded JSON, superglobals, environment values, array elements, or PHPDoc generics.

PHPThis therefore treats external data as untrusted `mixed` and uses one transition:

```text
external mixed data -> named parser factory -> final readonly value -> native typed code
```

## Database projections

A selected row is parsed immediately by a concrete `fromDatabaseRow` factory. The factory requires an exact column set and rejects null, unknown columns, invalid types, and out-of-range values. A database adapter may return an integer as an integer or canonical base-10 string; the projection documents which representations it accepts. Leading zeros, signs, whitespace, decimals, exponent notation, suffixes, and overflow are not integers.

Large identifiers beyond `PHP_INT_MAX` must remain validated string-backed values. Decimal and money values must not pass through binary floating point. Booleans, enums, and dates each require an exact documented representation.

## HTTP commands

A JSON body is decoded with `JSON_THROW_ON_ERROR` and an explicit depth limit, required to be an object, checked for an exact field set, and then converted into a concrete command. Unknown fields are errors rather than ignored input. Request byte-size limits belong before decoding.

Native `json_decode` keeps the final value when a JSON object repeats a key. This first slice documents that limitation rather than claiming duplicate keys are rejected. A duplicate-key-aware parser requires a separate decision if interoperability proves the need.

## Static generics

PHPStan types such as `list<UserSummary>`, array shapes, `class-string<T>`, and `@template T` provide compile-time relationships but have no runtime effect. PHPThis reserves templates for genuinely generic infrastructure. Domain data uses concrete projections and commands; a generic collection is not the default.

## Enforcement

Factories use private constructors, exact validation, precise return types, PHPStan maximum-level analysis, and adversarial tests. Strict Profile rule `PHT001` rejects scalar coercion while the operand remains `mixed`; explicit validation must narrow it first. Phase 2 may prevent `array<string, mixed>` from escaping named boundaries after current raw request and database APIs are narrowed. A token-wide cast ban remains incorrect because validated internal numeric conversions are legitimate.
