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
        -> RequestHandler
          -> typed application session service (when needed)
          -> Connection (when needed)
        <- Response
    -> ErrorResponseRegistry (only after a named failure)
    -> SessionLifecycle finish or abort (when configured)
  -> ResponseEmitter
```

The composition root constructs the complete graph, including the body limit and exact error-response registrations. Its root route manifest explicitly combines named route-area lists, keeping the complete application map visible without placing every endpoint in one file. These lists are composition fragments, not discoverable providers.

Only `public/index.php` reads PHP superglobals. `RequestReader` receives those arrays explicitly, reads at most its configured body limit plus one byte, and creates one immutable `Request`. `RequestBoundary` then begins the optional session lifecycle, calls the application, maps only exact registered failure classes, and performs one finalization outside that error-mapping catch. Unknown failures abort active and never-issued session work before being rethrown to the front controller, where `UnknownFailureBoundary` logs once without their message and returns one generic 500 response. A completed update to a browser-owned identifier is already committed and is not rolled back. Beginning a session lifecycle does not open native storage; only an explicit application session operation does.

The router stores objects, not class names, so dispatch does not need reflection or a container. It validates the complete list and builds immutable method/path and path/method indexes once. Exact dispatch and 405 lookup then use direct array access. Handlers depend on the smallest concrete boundary needed for the current design.

## Source responsibilities

- `Application`: selects HTTP outcomes and delegates to one handler.
- `Routing`: literal method/path matching only.
- `Http`: bounded runtime ingestion, immutable request/response values, exact error mapping, and final emission.
- `Session`: bounded immutable snapshots and one lazy native-file session lifecycle; authentication, authorization, expiry, and CSRF remain application policy.
- `Database`: explicit PDO execution and query accounting.
- `example`: proves the complete manual wiring path and the optional feature-first CRUD profile with separate bounded List and transactional Create use cases.

There are no providers, repositories, models, middleware pipelines, or controllers in the core. `RequestBoundary` is one named transport boundary, not a composable middleware chain. Session state does not enter `Request`; an application places narrowly named typed services with explicit non-overlapping key ownership in front of one `SessionLifecycle` instead of adding helpers or a generic key-value repository. Other labels may be introduced only when they represent a proven responsibility that cannot remain clear in a handler.
