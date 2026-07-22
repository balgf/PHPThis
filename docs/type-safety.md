# Type safety

PHP scalar casts are coercive even under `declare(strict_types=1)`. Strict types protect typed calls and returns; they do not validate PDO rows, decoded JSON, superglobals, environment values, array elements, or PHPDoc generics.

PHPThis therefore treats external data as untrusted `mixed` and uses one transition:

```text
external mixed data -> named parser factory -> final readonly value -> native typed code
```

Each parser and value is owned by one operation, and its private constructor prevents partially validated instances. After inbound parsing, downstream operation behavior uses the concrete command or request rather than the raw body, `Request`, or mixed array. A small handler may continue directly with that value; a separate typed operation seam is added only when HTTP adaptation and an independently meaningful business or transaction responsibility need separate ownership. Invalid input makes zero seam calls when one exists and cannot trigger operation-owned downstream I/O or mutation. On protected routes, separately bounded transport, session, authentication, tenant, or authorization work may deliberately occur before the handler parses operation input. A database projection necessarily parses after its explicit query and prevents an invalid row from entering later typed behavior.

## Database projections

A selected row is parsed immediately by a concrete `fromDatabaseRow` factory. The factory requires an exact column set and rejects null, unknown columns, invalid types, and out-of-range values. A database adapter may return an integer as an integer or canonical base-10 string; the projection documents which representations it accepts. Leading zeros, signs, whitespace, decimals, exponent notation, suffixes, and overflow are not integers.

Large identifiers beyond `PHP_INT_MAX` must remain validated string-backed values. Decimal and money values must not pass through binary floating point. Booleans, enums, and dates each require an exact documented representation.

## JSON commands

A JSON body is byte-bounded before decoding, decoded with `JSON_THROW_ON_ERROR` and an explicit depth, required to be an object, checked for an exact field set, and converted into a concrete command only after every field succeeds. Unknown fields are errors rather than ignored input. The outer `RequestReader` body limit and any smaller operation limit are separate visible bounds. Individual field limits are application decisions; a deliberately recorded total body bound may be the effective ceiling for every field when that is sufficient.

Decode objects as `stdClass` when object-versus-list identity matters, then inspect `get_object_vars`. A required field is present only when `array_key_exists` returns true. `isset` is not sufficient because it treats a present `null` as absent. Each field independently records whether it is required or optional and whether explicit `null` is accepted. An optional field's absence may select one documented default; `null` does not select that default unless the contract explicitly says so.

Validate fields in a fixed schema order and keep unknown-field handling deterministic rather than following submitted property order. A fail-fast parser therefore returns the same public failure for equivalent invalid objects regardless of key ordering. Do not construct a command incrementally or call its use case until the entire representation succeeds.

## Canonical scalar and collection recipes

- A JSON boolean is accepted only after `is_bool`. Strings such as `"true"`, integers, and `null` are not booleans.
- A JSON integer is accepted only after `is_int` and an explicit range check. A decoded float, including a number outside PHP's integer range, is rejected. A string-backed large identifier stays a string.
- An integer transported as a string first passes a complete canonical lexical grammar, then a range-checked conversion whose result is verified as `int`. Signs, leading zeroes, whitespace, decimal points, exponents, suffixes, and overflow fail unless the operation explicitly defines another representation.
- A backed enum first receives the exact backing runtime type, then uses an exhaustive `match` or `tryFrom`; `null` or an unknown case fails. Do not silently select a default case.
- A date or timestamp has one recorded format and timezone or offset rule. `DateTimeImmutable::createFromFormat` must succeed without warnings or errors, and formatting the result back with the same format must equal the input. Generic date guessing is not validation.
- An array field is first classified as a list or object. Lists require `array_is_list`, an explicit item-count bound, and parsing of every element into its concrete type. Objects require an exact key set. Nested arrays or objects fail unless the field contract explicitly describes and bounds them.
- A string requires `is_string`, an explicit empty-value policy, and a grammar where applicable. When it needs a separate limit, record its unit: `strlen` is a byte bound, while character or grapheme policy needs a named runtime capability and deployment evidence. A deliberately recorded total representation bound may instead be its effective ceiling. A JSON decoder's UTF-8 acceptance is not Unicode normalization.

## Validation, normalization, encoding, and authorization

Validation accepts or rejects one external representation. Normalization changes an accepted representation into a recorded canonical value. No field normalization is implicit: trimming, case folding, Unicode normalization, date timezone conversion, and deduplication each require an operation-owned policy covering transformation order, pre- and post-transform bounds, collisions, and the retained value. Rejecting a padded value by comparing it with `trim($value)` is validation, not normalization. The Create proof rejects only PHP `trim`'s default ASCII control/space charlist and deliberately preserves valid non-breaking space; it claims no general Unicode-whitespace policy.

Encoding or escaping is selected for the eventual output sink and occurs there; it must not mutate the command as a substitute for validation. JSON output uses `json_encode` with `JSON_THROW_ON_ERROR`, HTML uses the selected HTML context's encoder, and SQL data always remains in named parameters even after validation. A syntactically valid command also grants no permission: authentication, tenant resolution, and authorization remain separate current request decisions.

## Stable failures

The first proof maps every malformed create-user representation to the prebuilt generic `400 invalid_request` response and oversized input to the generic `413 request_body_too_large` response. Internal fixed exception messages may identify the failed rule for development, but submitted values, field contents, credentials, and those messages do not enter the public response or log. A field-addressable issue list, localized message, or different status is an application API decision rather than a generic framework validator.

The Create email field accepts only an unmodified string for which `filter_var($value, FILTER_VALIDATE_EMAIL, 0)` returns the identical value on the certified PHP runtime. Tests preserve case and `+` addressing while rejecting a Unicode local part, repeated domain dot, local-only domain, trailing domain dot, and padding. This is an explicit runtime-primitive policy, not a claim that PHPThis owns one complete email grammar; applications needing a runtime-independent protocol contract must record a finite grammar and recertification policy.

Native `json_decode` keeps the final value when a JSON object repeats a key. This first slice documents that limitation rather than claiming duplicate keys are rejected. A duplicate-key-aware parser requires a separate decision if interoperability proves the need.

## Query parameters

When constructed through `RequestReader`, `Request::$query` receives at most 64 string-named top-level entries with `mixed` values. Each accepting operation parses the complete array through a concrete factory before I/O, rejects unknown keys and non-canonical representations, and exposes only native typed state afterward. The example `ListUsersPageRequest::fromQuery` turns its optional canonical decimal `after_user_id` string into `?int`; it does not create a generic query bag or coercive input helper.

## Multipart upload values

`$_FILES` is an irregular external array rather than a static generic contract. ADR 026 narrows it once in `RequestReader`: one PHP-normalized flat field with exact known metadata keys and runtime types becomes `RequestUpload`, while nested and multiple normalized shapes fail. Duplicate raw scalar parts may already have collapsed and are an explicit proof limit. `RequestUploadError` is a backed enum covering PHP's recognized finite outcomes; an unknown integer remains an operational failure rather than an invented default.

The resulting value is still transport state, not an application command or stored-file object. Its filename and media type are explicitly named untrusted, its size is explicitly reported rather than verified, and client `full_path` is discarded. The operation parses the complete upload map into a narrower application value only after exact field, error, operation limit, PHP provenance, and actual-size checks. No PHPStan annotation can prove those runtime properties or make the client metadata trustworthy.

## Static generics

PHPStan types such as `list<UserSummary>`, array shapes, `class-string<T>`, and `@template T` provide compile-time relationships but have no runtime effect. PHPThis reserves templates for genuinely generic infrastructure. Domain data uses concrete operation requests, commands, and projections; a generic collection is not the default.

## Enforcement

Factories use private constructors, exact validation, precise return types, PHPStan maximum-level analysis, and adversarial tests. Strict Profile rule `PHT001` rejects scalar coercion while the operand remains `mixed`; explicit validation must narrow it first. In the Create proof, PHPStan proves that `CreateUserOperation` receives a `CreateUserCommand`, not that its field policies, bounds, normalization, errors, or authorization are correct. Runtime tests own those claims and assert zero operation calls and database work for invalid Create input. Handler-local operations instead prove that rejection occurs before their own I/O and mutation.

ADR 021 adds no generic core input API or new diagnostic. ADR 026 adds only the concrete multipart transport value and local-file response value. ADR 032 adds immutable type-specific `uuid()` and `ulid()` string access without a generic identifier value, normalization, automatic domain conversion, binding, or lookup. ADR 033 adds only an optional application composition constraint around the existing typed handler interface, not a new context value, type conversion, core API, or diagnostic. Consumer Contract version 9 carries Strict Profile version 2 forward unchanged; ADR 023 changes only the required application-owned terminal summary path. Phase 2 may prevent other `array<string, mixed>` values from escaping named boundaries after current raw request and database APIs are narrowed. A token-wide cast ban remains incorrect because validated internal numeric conversions are legitimate.
