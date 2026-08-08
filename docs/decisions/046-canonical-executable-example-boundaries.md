# ADR 046: Canonical executable-example application boundaries

Status: accepted

## Context

The executable example is intended to demonstrate one coherent PHPThis application pattern. Repository-wide review found four places where the same application concern had more than one apparent owner or an unstated sink precondition.

`ListDocumentsHandler` caught `InvalidRequest` and constructed the generic `400` response locally even though the composition root already owned that exact-class mapping. Three user projections independently parsed the same positive `users.id` representation while the existing `UserId` was nested under one operation. `schedule:run` resolved its database path before checking its explicit cadence even though the accepted scheduler decision says a non-due pass returns without scheduled application work. Document database projections accepted invalid UTF-8 titles that could fail later at JSON encoding, while the intentionally narrower Redis cache admitted at most 512 title bytes.

On 2026-08-09 in Asia/Manila, the accountable human approved Issue #34 and this application-example consolidation. The correction must preserve explicit policy order, direct SQL, query budgets, stable response and console bytes, and the distinction between authoritative data and cache admission without adding a framework abstraction.

## Decision

### One exact-class error-response owner

Application handlers do not reproduce a response already owned by the composition root's `ErrorResponseRegistry`. `ListDocumentsPageRequest` continues to throw `InvalidRequest`; `ListDocumentsHandler` lets that exact failure reach the request boundary after authenticate, tenant resolution, and current action authorization and before protected SQL. The composition-root registry remains the sole owner of the generic private `400` response.

This preserves the established terminal-summary contract: a registered exact failure records only the selected status and known-failure outcome. A direct test that deliberately bypasses `RequestBoundary` may observe the exception, but production response selection has one canonical path.

### One semantic user identifier

The example owns one final readonly `Example\Users\UserId` for the positive `users.id` invariant. Get and List projections remain operation-specific because their selected fields and responsibilities differ, but each constructs the same identifier type from the accepted native-integer or canonical-decimal database representation. List continuation also carries that semantic identifier after its query-specific parsing succeeds.

This narrowly refines ADR 013's historical executable-example tree by moving `UserId.php` from `GetUser/` to the feature-level `Users/` directory. It does not alter ADR 013's optional feature-first placement, operation-specific projection ownership, or application-selected coherent-alternative boundary; historical release inspection continues to use the exact tagged ADR 013 bytes.

SQL bindings, JSON fields, and cursor text receive the identifier's explicit native value. The object is never passed implicitly to PDO or JSON encoding. This adds no framework identifier, base class, trait, interface, cast helper, binding behavior, repository, or ORM.

### Cadence before dependency work

The scheduled command samples its explicit clock and evaluates the five-minute cadence before resolving the application database path or constructing Redis, PDO, lease-token, or job state. A non-due pass returns the exact successful `not_due` result with an empty coordination list and performs no dependency work.

A due pass then resolves the database path before Redis coordination and job work. A missing due-path dependency returns the existing generic redacted operational failure before Redis or SQL. Contention, owned execution, renewal, release, one-job bounds, and output bytes remain unchanged. `not_due` is only a cadence outcome; it is not database, Redis, job, or application readiness evidence. Deployment health monitoring remains a separately recorded operational responsibility.

### Authoritative title and cache-admission boundaries

The authoritative document projections accept a title only when it is a non-empty valid UTF-8 string. They do not normalize it or inherit the Redis cache's 512-byte title-admission limit. Invalid stored UTF-8 fails through the named operation projection before JSON response construction and remains an unknown server invariant failure with the existing generic redacted `500` path.

The Redis document-details cache retains its narrower 512-byte title and 1,024-byte complete-payload admission limits. A valid larger authoritative title remains usable by the application and is deliberately not cached. The existing title-update proof may retain its own 512-byte operation-input decision; that does not redefine the storage or projection invariant.

## Consequences

The example now has one visible response-mapping owner, one application-owned semantic type for one user identity, one scheduler preflight order, and one explicit UTF-8 precondition before its JSON sink. Distinct projections, operation-specific input policies, and backend-specific cache bounds remain distinct rather than being deduplicated into generic infrastructure.

Tests must prove exact error ownership, identifier parsing and explicit scalar delivery, non-due zero dependency work, due missing-database failure, invalid stored UTF-8 fail-closed behavior, valid titles beyond cache admission, stable output bytes, policy order, redaction, and unchanged query bounds.

This decision changes only the executable example, its current guidance, and repository evidence. Consumer Contract version 11, Strict Profile version 3, PHPThis core at 2,600 lines, runtime dependencies, consumer checker validity, and the skeleton/template application API remain unchanged.

## Reconsider when

Independent applications show that the same semantic identifier needs a different recorded invariant between operations, a scheduled cadence genuinely requires an unconditional dependency preflight, or an authoritative title needs an application-wide byte or character limit. Reconsider the narrow application decision with exact compatibility and evidence; do not add a generic framework identifier, error renderer, validator, scheduler facade, cache helper, repository, ORM, or response magic.
