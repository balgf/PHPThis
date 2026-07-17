# Routing contract

A route is exactly three things: uppercase HTTP method, literal path, and a constructed `RequestHandler` object. The application has one root `Routes::create()` manifest that explicitly combines narrowly named `*Routes` lists.

Add a route by:

1. Creating a final handler that implements `RequestHandler`.
2. Giving it constructor parameters for all dependencies.
3. Implementing `handle(Request $request): Response`.
4. Adding the route to the relevant final `*Routes` class, which receives constructed handlers and returns `list<Route>`.
5. Constructing the handler explicitly in the root `Routes::create()` and spreading the named route list into its result.
6. Adding handler and application tests.

The root manifest contains one visible entry per route area; endpoint definitions stay in the named area file. Do not add provider interfaces, registries, closures, invokable controllers, class-name strings, route attributes, scanning, method-name conventions, or automatic parameter binding.

`Router` validates and indexes the complete list once at construction. Exact matching and allowed-method lookup use those indexes and must not scan the route list during a request.

The current router intentionally accepts only exact paths. Typed dynamic parameters require a decision record and are scheduled for Phase 1.
