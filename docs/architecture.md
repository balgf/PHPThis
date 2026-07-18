# Architecture

PHPThis uses a flat request pipeline:

```text
public/index.php
  -> RequestBoundary
    -> RequestReader
      <- PHP runtime arrays and bounded input stream
    -> Application
      -> Router
        -> RequestHandler
          -> Connection (when needed)
        <- Response
    -> ErrorResponseRegistry (only after a named failure)
  -> ResponseEmitter
```

The composition root constructs the complete graph, including the body limit and exact error-response registrations. Its root route manifest explicitly combines named route-area lists, keeping the complete application map visible without placing every endpoint in one file. These lists are composition fragments, not discoverable providers.

Only `public/index.php` reads PHP superglobals. `RequestReader` receives those arrays explicitly, reads at most its configured body limit plus one byte, and creates one immutable `Request`. `RequestBoundary` then calls the application and maps only exact registered failure classes. Unknown failures are rethrown to the front controller, where `UnknownFailureBoundary` logs once without their message and returns one generic 500 response.

The router stores objects, not class names, so dispatch does not need reflection or a container. It validates the complete list and builds immutable method/path and path/method indexes once. Exact dispatch and 405 lookup then use direct array access. Handlers depend on the smallest concrete boundary needed for the current design.

## Source responsibilities

- `Application`: selects HTTP outcomes and delegates to one handler.
- `Routing`: literal method/path matching only.
- `Http`: bounded runtime ingestion, immutable request/response values, exact error mapping, and final emission.
- `Database`: explicit PDO execution and query accounting.
- `example`: proves the complete manual wiring path and the optional feature-first CRUD profile with separate bounded List and transactional Create use cases.

There are no providers, repositories, models, middleware pipelines, or controllers in the core. `RequestBoundary` is one named transport boundary, not a composable middleware chain. Those other labels may be introduced only when they represent a proven responsibility that cannot remain clear in a handler.
