# Architecture

PHPThis uses a flat request pipeline:

```text
public/index.php
  -> RequestBoundary
    -> RequestReader
      <- PHP runtime arrays and bounded input stream
    -> SessionLifecycle (optional; lazy storage access)
    -> Application
      -> Router
        <- RouteMatch (Route + immutable PathParameters)
      -> immutable Request copy
        -> RequestHandler
          -> typed application session service (when needed)
          -> typed application cache service (when deliberately adopted)
          -> Connection (when needed)
        <- Response
    -> ErrorResponseRegistry (only after a named failure)
    -> SessionLifecycle finish or abort (when configured)
  -> ResponseEmitter
```

The composition root constructs the complete graph, including the body limit and exact error-response registrations. Its root route manifest explicitly combines named route-area lists, keeping the complete application map visible without placing every endpoint in one file. These lists are composition fragments, not discoverable providers.

Only `public/index.php` reads PHP superglobals. `RequestReader` receives those arrays explicitly, reads at most its configured body limit plus one byte, and creates one immutable `Request`. `RequestBoundary` then begins the optional session lifecycle, calls the application, maps only exact registered failure classes, and performs one finalization outside that error-mapping catch. Unknown failures abort active and never-issued session work before being rethrown to the front controller, where `UnknownFailureBoundary` logs once without their message and returns one generic 500 response with `Cache-Control: no-store`. A completed update to a browser-owned identifier is already committed and is not rolled back. Beginning a session lifecycle does not open native storage; only an explicit application session operation does.

The router stores objects, not class names, so dispatch does not need reflection or a container. It validates the complete list once. Literal routes use immutable method/path and path/method indexes; the single accepted trailing `{name:positive-int}` shape uses a separate method and literal-prefix index. Literal matches take precedence, ambiguous typed declarations fail at construction, and dispatch plus 405 lookup do not scan the route list. A successful lookup returns immutable `RouteMatch` metadata. `Application` copies the normalized request with the match's immutable `PathParameters` and passes it to the existing `RequestHandler::handle(Request)` interface; static routes receive empty parameters. Handlers depend on the smallest concrete boundary needed for the current design and immediately convert a validated path integer into a route-specific concrete identifier.

## Source responsibilities

- `Application`: selects HTTP outcomes, copies matched routing metadata onto the immutable request, and delegates to one handler.
- `Routing`: directly indexed literal matching plus the one bounded indexed trailing positive-integer shape; no route-table scan or domain binding.
- `Http`: bounded runtime ingestion, immutable request/response values, exact error mapping, and final emission.
- `Session`: bounded immutable snapshots and one lazy native-file session lifecycle; authentication, authorization, expiry, and CSRF remain application policy.
- `Database`: explicit PDO execution and query accounting.
- `example`: proves the complete manual wiring path and the optional feature-first CRUD profile with bounded List, transactional Create, and a first bounded typed-item Get use case.

There is no cache namespace or cache mechanism in the core. HTTP response policy remains an explicit property of the response-producing path. Framework-owned 404, 405, and unknown-failure 500 responses explicitly prohibit storage; the skeleton and example do the same for their current handlers. PHPThis does not rewrite arbitrary handler responses, so every additional application path still owns and tests its policy. If an application later adopts server-side caching, it manually wires a narrowly named typed application service at the handler boundary; that service owns one cache-aside execution path and its backend-specific policy. It is not a generic key-value facility, middleware, or replacement for the authoritative data path.

There are no providers, repositories, models, middleware pipelines, or controllers in the core. `RequestBoundary` is one named transport boundary, not a composable middleware chain. Routing metadata enters only through immutable `PathParameters`; session state does not enter `Request`. An application places narrowly named typed services with explicit non-overlapping key ownership in front of one `SessionLifecycle` instead of adding helpers or a generic key-value repository. Other labels may be introduced only when they represent a proven responsibility that cannot remain clear in a handler.
