# ADR 019: Bounded multiple typed routes

Status: accepted

Superseded in part by [ADR 032](032-explicit-uuid-and-ulid-route-types.md), which retains this decision's parameter count, state index, opaque-token, conflict, and immutable-delivery constraints while extending the fixed parameter-type set with canonical UUID and ULID values.

## Context

ADR 017 proved that one trailing canonical positive integer could enter a handler without route discovery, automatic binding, request-time route-table traversal, or a second handler interface. A consumer capability audit exposed the next repeated need: an explicitly nested resource path can require two identifiers, and an externally assigned identifier may be an opaque path token rather than an integer.

Literal enumeration cannot represent that path. The accepted one-parameter grammar cannot place an identifier before a later literal segment or carry a second identifier. A general pattern language, arbitrary regular expressions, callbacks, user-provided parsers, or unbounded parameter lists would recreate the inference and cost problems PHPThis is designed to avoid.

## Decision

A route remains an uppercase HTTP method, an explicit absolute path declaration, and an already-constructed `RequestHandler`. A path is fully literal or contains at most two named typed placeholders. Every placeholder occupies one complete path segment and may appear in any segment position. Placeholder names are unique within the route and match `[a-z][a-z0-9_]*`.

The only accepted parameter types are:

- `positive-int`: canonical ASCII `[1-9][0-9]*` in the range 1 through `PHP_INT_MAX`, unchanged from ADR 017;
- `token`: a case-sensitive string of 1 through 64 ASCII bytes matching `[A-Za-z0-9][A-Za-z0-9_-]{0,63}`.

The router returns token bytes unchanged. It does not URL-decode the request path, so percent-encoded spellings and encoded separators do not become parameter values. Whitespace, Unicode characters, an empty token, a token beginning with `_` or `-`, a token containing another punctuation character, and a token longer than 64 bytes do not match. A token may contain only digits; because its accepted language then overlaps `positive-int`, declarations cannot use type precedence to resolve an otherwise overlapping pattern.

`Router` validates the complete declaration list and builds a deterministic state index once at construction. Fully literal method/path lookup remains separate and an exact literal route wins before parameterized matching. Parameterized declarations whose accepted paths overlap for the same method are rejected at construction. Each compiled state has at most one typed transition. A sibling parameter type or parameterized literal transition accepted by that type is rejected regardless of method or later branch, and every declaration sharing the typed transition reuses its parameter name and type. Registration order and parameter-type preference never resolve these conflicts.

Request-time matching may traverse the bounded request path and its compiled state transitions. It must not traverse the declared route list or scan an index collection. Allowed-method lookup follows the same indexed states and retains explicit route-registration order in the `Allow` value. A syntactically invalid or oversized parameter does not reach a handler and produces the ordinary route-miss behavior; a syntactically valid path registered under another method produces the ordinary method-rejection behavior.

On success, `Router::match` still returns immutable `RouteMatch` metadata. `Application` still creates an immutable `Request` copy carrying immutable `PathParameters` and calls `RequestHandler::handle(Request): Response`. `PathParameters` exposes only type-specific access: `positiveInteger(name): int` and `token(name): string`. It has no mixed getter and performs no record lookup, authorization, tenant resolution, or domain conversion. Route-specific code immediately wraps each value in its concrete identifier before domain or database work.

The old `Route::literalPrefix()` and `Route::parameterName()` metadata cannot truthfully describe middle or multiple parameters. One canonical immutable `Route::segments()` representation replaces them rather than retaining a partial compatibility representation. Existing literal and one-trailing-positive-integer route declarations remain valid, but code that directly called those metadata methods must migrate to `segments()`.

Consumer Contract version 4 accepts this bounded route grammar and carries Strict Profile version 2 forward unchanged. The change adds no PHPThis syntax diagnostic or checker rule. Contract version 4 does not authorize another parameter type, a third parameter, automatic input or domain binding, or another routing API.

The Alpha 2 core-source ceiling increases from 2,050 to 2,300 physical lines only for this multiple-typed-routing slice. The reviewed implementation occupies 2,246 core lines and leaves 54 lines of maintenance margin. That margin does not fund an adjacent request mechanism, route language, discovery facility, compatibility API, or framework policy.

## Consequences

The representative declaration is:

```php
new Route(
    'GET',
    '/accounts/{account_id:positive-int}/documents/{document_key:token}',
    $handler,
);
```

It carries two validated routing values without loading application objects. Literal routes and the accepted ADR 017 route form keep their existing behavior. The compiled state remains derived from the same explicit route objects and is inspectable through immutable `Route::segments()` metadata; PHPThis adds no generated route source, persisted route cache, discovery pass, or string-based handler resolution.

The conservative overlap rejection may forbid declarations that a backtracking or precedence-heavy router could distinguish. That cost is accepted so an AI and a reviewer can explain one deterministic route without depending on registration order or hidden fallback work. Applications use distinct literal structure when two proposed patterns overlap.

This decision supersedes ADR 017 only where ADR 017 limited parameters to one trailing positive integer and described the corresponding prefix index and one-value route metadata. ADR 017 remains the accepted history and contract for canonical positive integers, raw-path matching, literal precedence, immutable delivery, explicit handlers, and the absence of domain binding.

## Reconsider when

At least two independent applications demonstrate the same additional parameter type or a third parameter and can preserve a finite grammar, construction-time overlap rejection, immutable type-specific delivery, and request-time work independent of route-table size; or measured evidence shows that the state index fails its construction, memory, matching, or allowed-method cost contract. Reconsider one bounded extension and its migration impact, not an open-ended pattern API.
