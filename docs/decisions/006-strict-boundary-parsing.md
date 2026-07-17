# ADR 006: Strict boundary parsing

Status: accepted

## Context

PHP has no native generic collections, and strict types do not validate values returned by PDO or JSON decoding. Scalar casts can silently turn malformed input into plausible values.

## Decision

External `mixed` data is parsed exactly once by a named factory into a concrete final readonly projection or command. Factories reject missing and unknown fields, validate before conversion, accept only documented canonical representations, and have private constructors. PHPStan generics remain static supplements.

The first examples are `UserSummary::fromDatabaseRow` and `CreateUserCommand::fromJson`. PHPThis does not add reflection hydration, a generic collection, or a generic result wrapper for this capability.

## Consequences

Boundary code is more explicit and repetitive, but invalid values cannot enter typed application code through ordinary construction. Adapter-specific representations remain visible and testable. Native JSON duplicate-key handling remains a documented limitation.

## Reconsider when

Measured repetition justifies checked-in generated mappers, or interoperability requires a duplicate-key-aware JSON parser. Any replacement must preserve exact validation and reviewable generated PHP without runtime discovery.
