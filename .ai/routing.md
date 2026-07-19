# Routing contract

A route is exactly three things: uppercase HTTP method, an explicit path declaration, and a constructed `RequestHandler` object. A path is fully literal or contains at most two named typed placeholders. Every placeholder occupies one complete segment and uses only `{name:positive-int}` or `{name:token}`. The application has one root `Routes::create()` manifest that explicitly combines narrowly named `*Routes` lists.

Add a route by:

1. Creating a final handler that implements `RequestHandler`.
2. Giving it constructor parameters for all dependencies.
3. Implementing `handle(Request $request): Response`.
4. Adding the route to the relevant final `*Routes` class, which receives constructed handlers and returns `list<Route>`.
5. Constructing the handler explicitly in the root `Routes::create()` and spreading the named route list into its result.
6. Adding handler and application tests for success, malformed values, method rejection, overlap rejection, and applicable resource bounds.

The root manifest contains one visible entry per route area; endpoint definitions stay in the named area file. Do not add provider interfaces, registries, closures, invokable controllers, class-name strings, route attributes, scanning, method-name conventions, or automatic parameter binding.

Each parameter name begins with a lowercase ASCII letter and then uses only lowercase letters, digits, or underscores; names are unique within one route. `positive-int` means canonical ASCII `[1-9][0-9]*` in the range 1 through `PHP_INT_MAX`. `token` means a case-sensitive 1-to-64-byte ASCII value matching `[A-Za-z0-9][A-Za-z0-9_-]{0,63}`. The request path is not URL-decoded, so encoded spellings and encoded separators do not match. Do not add a third parameter, another type, regular expressions, callbacks, catch-alls, user-provided parsers, or coercive casts.

`Router` validates and indexes the complete list once at construction. Fully literal method/path lookup remains separate and direct. Parameterized lookup uses the deterministic state index. Exact literal routes win before parameterized matching. Parameterized declarations that can overlap fail at construction; registration order, names, and type preference do not resolve them. One state cannot contain both parameter types or contain a typed transition beside a parameterized literal transition accepted by that type, even when later segments would differ; give those routes non-overlapping literal structure instead. Every declaration sharing a typed transition reuses its name and type regardless of method or later branch. Request-time matching and allowed-method lookup may traverse the bounded request path and compiled state transitions but must not scan the route list or an index collection. Use immutable `Route::segments()` metadata when a declaration must be inspected; do not add generated route source, persisted route caches, or a second report API.

On success `Router::match` returns an immutable `RouteMatch`. `Application` creates an immutable `Request` copy carrying the match's immutable `PathParameters`; static routes receive empty parameters. Keep `RequestHandler::handle(Request): Response` unchanged. Read values only through `positiveInteger(name): int` or `token(name): string`, and immediately wrap each value in a route-specific concrete identifier before domain or database work. Path parameters remain routing metadata, never a mixed bag, authorization result, tenant scope, record lookup, or domain binding.

The representative two-identifier declaration is `/accounts/{account_id:positive-int}/documents/{document_key:token}`. Read [ADR 019](../docs/decisions/019-bounded-multiple-typed-routes.md) before changing this bounded grammar or state index. Read [ADR 017](../docs/decisions/017-bounded-trailing-positive-integer-routes.md) for the retained positive-integer, raw-path, immutable-delivery, and no-domain-binding rationale.
