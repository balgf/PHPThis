# ADR 017: Bounded trailing positive-integer routes

Status: accepted

## Context

Literal route lookup keeps dispatch local, deterministic, and independent of route-table size, but a database-backed item operation needs one value from the request path. Leaving every item URL as a manually enumerated literal is not viable. Adopting a general pattern router would introduce a much larger inference surface: arbitrary regular expressions, user callbacks, decoded-path ambiguity, multiple parameter shapes, automatic domain binding, and potentially a request-time scan of the route table.

The first item proof needs only one canonical positive integer at the end of an otherwise literal path. PHPThis can support that case without turning routing into a general parsing language.

## Decision

A route remains an uppercase HTTP method, an explicit path declaration, and an already-constructed `RequestHandler`. Its path is either fully literal or contains exactly one trailing full-segment placeholder written `{name:positive-int}`. The name uses lowercase ASCII beginning with a letter, followed only by lowercase letters, digits, or underscores. A parameter cannot appear in the middle of a path, share a segment with literal text, or be combined with another parameter.

`positive-int` accepts only the canonical ASCII decimal spelling `[1-9][0-9]*` whose value is between 1 and `PHP_INT_MAX`, inclusive. Zero, leading zeroes, signs, whitespace, Unicode digits, decimal or exponent notation, overflow, an empty segment, and extra path segments do not match. The request path is not URL-decoded by the framework, so encoded digits such as `%31` and encoded separators do not become matching values.

PHPThis does not accept arbitrary string parameters, regular-expression routes, callback or user-provided parsers, catch-all segments, or automatic domain-object binding. Parameter names do not make otherwise identical patterns distinct.

`Router` validates and indexes the complete route list at construction. Literal routes retain their direct method/path index. Typed routes use a separate method and literal-prefix index; neither successful matching nor allowed-method lookup scans the route table. A literal route has precedence when a literal path and a typed pattern can both match. Two same-method typed declarations with the same literal shape are ambiguous and are rejected during construction, even when their parameter names differ. Typed routes sharing one literal prefix across different methods must use one parameter name across those methods so routing metadata does not change with the method.

A successful `Router::match` returns an immutable `RouteMatch`. `Application` creates an immutable copy of the normalized `Request` carrying the match's immutable `PathParameters` and passes that request to the matched handler. Static routes carry an empty `PathParameters`. The handler interface remains `RequestHandler::handle(Request): Response`.

Path parameters are routing metadata, not a mixed application context bag. The router supplies only the already-validated positive integer. Route-specific code immediately converts that integer into its concrete application identifier, such as `UserId`, before database or domain work. Authorization, tenant scope, record existence, and other domain policy remain application-owned decisions.

The first proof is the item endpoint `GET /users/{user_id}`, declared with the route path `/users/{user_id:positive-int}`. It proves canonical matching, immediate conversion to a concrete identifier, and one bounded item query with a concrete projection. It does not claim complete Get authorization or tenant policy, and it does not claim Update or Delete support.

This bounded routing capability does not change Consumer Contract version 3 or Strict Profile version 2. It adds no new accepted PHP syntax class or checker rule.

The Phase 1 core-source ceiling increases from 1,700 to 2,050 physical lines only for this routing slice. The reviewed implementation occupies 2,010 core lines. The remaining 40-line maintenance margin does not fund a general dynamic router or pre-authorize any adjacent mechanism.

## Consequences

An item route gains one useful typed value without route discovery, coercive casts, arbitrary parsing, or handler-signature variation. Static and typed dispatch remain indexable, and literal behavior remains unchanged.

The deliberately narrow grammar means applications may need literal routes or a later decision for slugs, UUIDs, nested resources, multiple parameters, or other identifier forms. A route parameter's integer type proves only its syntax and range; it does not prove authorization, tenancy, existence, or database validity.

Because paths are matched without URL decoding, deployments must preserve the request-target semantics expected by `RequestReader`. An intermediary rewrite that changes the path is deployment behavior and must be recorded and tested by the application.

## Reconsider when

At least two independent applications need the same additional identifier shape or route position and can preserve construction-time validation, direct indexed lookup, literal precedence, immutable typed delivery, and unambiguous failure behavior; or measured routing evidence shows that the prefix index does not preserve the stated scaling properties. Reconsider one bounded grammar extension, not an open-ended pattern API.
