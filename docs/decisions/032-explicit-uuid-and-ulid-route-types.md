# ADR 032: Explicit UUID and ULID route types

Status: accepted

## Context

ADR 019 accepts at most two full-segment route parameters using canonical positive integers or bounded opaque tokens. UUID and ULID row identifiers fit the broad token alphabet, but `{name:token}` neither communicates their intended representation nor rejects another token-shaped value. That ambiguity gives an AI and reviewer less useful local evidence and allows malformed identifiers to reach application code.

ADR 019 names repeated independent consumer evidence as the normal reconsideration trigger. Two independent PHPThis consumer proofs have not been claimed here. The accountable maintainer approved a bounded Alpha-stage exception after reviewing the established UUID and ULID identifier shapes, correcting the public routing contract before a stable release. This acceptance does not relax the evidence requirement for another parameter type or a third parameter.

## Decision

A route remains an uppercase method, one explicit path declaration, and one already-constructed `RequestHandler`. A path remains literal or contains at most two full-segment placeholders. The fixed parameter types are now `positive-int`, `token`, `uuid`, and `ulid`.

Applications use the narrowest type that represents the path value. `token` remains the case-sensitive bounded fallback for a genuinely opaque identifier such as a slug, username, invitation code, hash, or external reference. It is not a shortcut for a positive integer, UUID, or ULID.

`uuid` accepts exactly this lowercase grammar:

```text
[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}
```

The version nibble accepts 1 through 8, including version 2 as the reserved DCE Security form recorded by RFC 9562. The variant nibble is the RFC variant `8`, `9`, `a`, or `b`. The grammar rejects Nil, Max, version 0, versions 9 through 15, non-RFC variants, uppercase, compact hex, braces, URN prefixes, and every other alternate spelling. Although UUID hexadecimal is generally case-insensitive, lowercase is PHPThis's one canonical path representation. The router never normalizes a value.

`ulid` accepts exactly this lowercase grammar:

```text
[0-7][0-9abcdefghjkmnpqrstvwxyz]{25}
```

The first character bound rejects values above 128 bits. The remaining alphabet is lowercase Crockford Base32 without `i`, `l`, `o`, or `u`. The ULID specification is case-insensitive and commonly displays uppercase; lowercase is PHPThis's one canonical path representation. The router never case-folds a value.

The request path remains undecoded. Percent-encoded spellings and separators do not become UUIDs or ULIDs. A syntax mismatch produces ordinary route-miss behavior before the handler and therefore before handler-owned database or external work. A valid path registered only under another method retains the ordinary ordered `405` behavior.

`PathParameters` adds only `uuid(name): string` and `ulid(name): string`; it retains `positiveInteger(name): int` and `token(name): string` and adds no mixed getter. Values are returned unchanged. Route-specific application code immediately wraps the value in a concrete identifier and may apply a narrower domain rule, such as UUID version 7 only, before authorization, SQL, or other domain work.

The deterministic state index keeps one typed transition per state. Every differing sibling parameter type remains a construction-time conflict, even where two accepted languages are disjoint. `token` therefore cannot sit beside `uuid` or `ulid`, and no failed UUID or ULID match falls back to token matching. Exact literal routes retain precedence. Registration order and type preference never resolve a conflict.

No identifier library, generator, global factory, route builder, regular-expression API, callback parser, automatic binding, record lookup, storage policy, database cast, schema abstraction, or ORM behavior is added.

Consumer Contract version 8 accepts the two fixed route types and carries Strict Profile version 2 forward unchanged. Existing literal, `positive-int`, and genuinely opaque `token` routes remain valid. An adopting application audits identifier-shaped token routes, changes only those whose declared syntax is UUID or ULID, switches to the matching accessor, immediately wraps the value, adds boundary and no-downstream-work tests, and runs its complete gate. No data rewrite, schema change, identifier-generation policy, or runtime dependency is implied.

The core-source ceiling increases from 2,500 to 2,600 physical lines only for these fixed types, their canonical validation, immutable type-specific delivery, and deterministic matching. The reviewed implementation occupies 2,592 core lines and leaves eight lines of maintenance margin. That margin authorizes no additional route type, parameter position, parser API, binding behavior, compatibility spelling, or adjacent request mechanism.

## Consequences

An explicit declaration such as `new Route('GET', '/accounts/{account_id:uuid}', $handler)` now communicates and enforces the path representation before handler work. ULID routes receive the same benefit. Applications retain responsibility for identifier meaning, generation, accepted UUID version within a domain, authorization, tenancy, existence, storage, and SQL.

The lowercase-only policy deliberately rejects otherwise parseable external representations. Applications integrating a system that exposes another representation must make a human-approved boundary decision rather than silently normalizing inside routing.

The conservative single-transition rule may require distinct literal structure for routes using different identifier types. That cost preserves the existing inspectable index and avoids hidden match priority or backtracking.

## Reconsider when

At least two independent applications demonstrate the same additional finite identifier grammar or a need for a third parameter while preserving construction-time conflict detection, raw-path matching, immutable type-specific delivery, zero downstream work for malformed values, and request-time work independent of route-table size; or measured evidence shows that the current state index fails its construction, memory, matching, or allowed-method contract. Reconsider one bounded extension, not an open route-pattern language.

## References

- [RFC 9562: Universally Unique IDentifiers](https://www.rfc-editor.org/rfc/rfc9562.html)
- [ULID canonical specification](https://github.com/ulid/spec)
