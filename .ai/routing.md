# Routing contract

A route is exactly three things: uppercase HTTP method, an explicit path declaration, and a constructed `RequestHandler` object. A path is either fully literal or contains one optional trailing full-segment `{name:positive-int}` placeholder. The application has one root `Routes::create()` manifest that explicitly combines narrowly named `*Routes` lists.

Add a route by:

1. Creating a final handler that implements `RequestHandler`.
2. Giving it constructor parameters for all dependencies.
3. Implementing `handle(Request $request): Response`.
4. Adding the route to the relevant final `*Routes` class, which receives constructed handlers and returns `list<Route>`.
5. Constructing the handler explicitly in the root `Routes::create()` and spreading the named route list into its result.
6. Adding handler and application tests.

The root manifest contains one visible entry per route area; endpoint definitions stay in the named area file. Do not add provider interfaces, registries, closures, invokable controllers, class-name strings, route attributes, scanning, method-name conventions, or automatic parameter binding.

The parameter name begins with a lowercase ASCII letter and then uses only lowercase letters, digits, or underscores. `positive-int` means canonical ASCII `[1-9][0-9]*` in the range 1 through `PHP_INT_MAX`. Reject zero, leading zeroes, signs, whitespace, Unicode digits, decimal or exponent notation, overflow, missing segments, and extra segments. The request path is not URL-decoded, so encoded spellings do not match. Do not add string parameters, middle or multiple parameters, regular expressions, callbacks, catch-alls, user-provided parsers, or coercive casts.

`Router` validates and indexes the complete list once at construction. Literal method/path lookup remains direct. Typed lookup uses its separate method and literal-prefix index. Matching and allowed-method lookup must not scan the route list during a request. Literal matches take precedence, construction rejects same-method typed patterns with the same literal shape even when their parameter names differ, and routes sharing one typed prefix across methods use one parameter name.

On success `Router::match` returns an immutable `RouteMatch`. `Application` creates an immutable `Request` copy carrying the match's immutable `PathParameters`; static routes receive empty parameters. Keep `RequestHandler::handle(Request): Response` unchanged. Treat path parameters only as routing metadata, and immediately wrap the validated integer in a route-specific concrete identifier before domain or database work.

Read [ADR 017](../docs/decisions/017-bounded-trailing-positive-integer-routes.md) before changing this bounded grammar or dispatch path.
