# ADR 021: Application-owned typed input boundaries

Status: accepted

## Context

`RequestReader` bounds and normalizes PHP runtime transport values, but an operation still receives JSON strings, query values, and other external representations whose fields may be absent, explicitly `null`, wrongly typed, oversized, nested, or malformed. PHP strict types do not validate those values. A generic validator, rule-string language, sanitizer, automatic hydrator, or mass-assignment API would hide the operation's accepted representations and create another inference surface.

Validation, normalization, output encoding, and authorization are different decisions. Treating one as another can silently change user data, leak information, or admit a valid value to an operation the current principal may not perform.

## Decision

Each accepting operation owns one named parser factory that turns its complete raw representation into a concrete final readonly command or request. Its constructor is private. An inbound request or command factory applies a recorded total representation bound plus the depth, field, list, and scalar bounds required by that operation; distinguishes an absent key with `array_key_exists` from a present `null`; rejects unknown fields; checks runtime types before any conversion; and validates fields in a deterministic code-owned order. Individual field bounds are application decisions, and a deliberately recorded total representation bound may be their effective ceiling. Database projections retain their exact shape, type, and range rules and add a field bound only when the selected schema or operation supplies one.

After successful parsing, downstream operation behavior uses only that concrete value. It may remain directly in a small handler, as the existing List proof does, or cross one narrowly typed operation seam when HTTP adaptation and an independently meaningful business or transaction responsibility need separate ownership. Create uses that seam because its HTTP media/parsing/response work and its transaction have different responsibilities; ADR 024 later adds commit-visible job publication as that transaction's third statement. Zero-call rejection evidence follows from that design rather than justifying an otherwise empty interface. Invalid input makes zero calls to such a seam and performs no operation-owned database work, session or cache mutation, filesystem or network action, or other downstream business side effect.

ADR 029 later supplies explicit principal, tenant, and account context to that same operation seam and adds the separate `account_users` relation as the fourth transaction statement. The typed-input decision and its zero-operation-call rejection boundary remain unchanged.

Parsing order relative to security policy is recorded per route. ADR 020's protected-request order remains `authenticate -> resolve tenant -> authorize -> protected handler`; those separately budgeted policy reads or other approved policy work may therefore occur before a protected handler parses its operation input. Input rejection still prevents the protected operation and its side effects, but it does not retroactively claim that earlier transport, session, authentication, tenant, or authorization work never occurred.

Canonical representations are operation policy. JSON booleans require `is_bool`; JSON integers require `is_int` plus an explicit range; string-encoded integers require a complete lexical check before a range-checked conversion; backed enums require the exact backing type and an accepted case; dates require one recorded format, timezone or offset rule, parser-error check, and exact round trip; arrays require an explicit list or object decision, applicable count bound, and per-element parsing. A string's grammar and any separate byte or character bound are recorded when the operation needs them. PHPThis adds no generic validator, result wrapper, collection, sanitizer, or hydrator.

No field normalization occurs by default. When an application deliberately normalizes a field, the exact transformation, ordering, bounds, collision semantics, and retained canonical value are recorded and tested for that operation. Validation decides whether a representation is accepted. Output encoding or escaping occurs only for the eventual HTML, JSON, shell, header, or other sink. SQL data remains bound even after validation. Authorization remains a current action decision and is never inferred from a valid command.

The first public proof keeps the existing prebuilt generic `400 invalid_request` response. Validation order and internal fixed messages may aid development, but submitted values, field contents, credentials, and internal messages do not enter public responses or logs. A field-addressable error schema, different status, or localized message contract is an application API decision, not a framework validation mechanism.

The Create proof deliberately defines email as an unmodified string for which PHP's `filter_var($value, FILTER_VALIDATE_EMAIL, 0)` returns that same string. It does not trim, lowercase, convert an internationalized domain, or claim a framework-owned email grammar. This policy is tied to the certified PHP runtime behavior and its representative acceptance and rejection tests; an application requiring a protocol-stable grammar across runtime upgrades records and tests that grammar itself.

Native `json_decode` retains the final value for a repeated object key. PHPThis continues to disclose that limitation rather than claiming duplicate-key rejection. Applications that require duplicate-key detection need a separately reviewed parser decision.

This decision adds application-owned example evidence and authoring guidance only. It adds no core PHP file, runtime dependency, automatic request binding, or PHPThis diagnostic. Consumer Contract version 4 and Strict Profile version 2 remain unchanged.

## Consequences

Boundary code remains repetitive and operation-specific, so an AI can inspect the complete accepted shape, limits, transformations, error exposure, and point at which typed behavior begins. PHPStan verifies the resulting command types; adversarial runtime tests verify external representations and exclusion from downstream operation work. A typed operation interface is introduced only for a concrete tested responsibility, not automatically for every parser. The generic public error is stable and disclosure-safe but intentionally does not tell a client which field failed.

## Reconsider when

At least two independent applications prove the same smaller typed parser contract across materially different operations, including bounds, normalization, errors, and adversarial input; a client contract requires field-addressable failures that cannot remain application-owned; or interoperability requires duplicate-key-aware JSON parsing. Reconsider one narrow evidence-backed boundary, not a rule DSL, reflection hydration, mass assignment, sanitization magic, or automatic discovery.
