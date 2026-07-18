# Architecture

PHPThis uses a flat request pipeline:

```text
public/index.php
  -> Application
    -> Router
      -> RequestHandler
        -> Connection (when needed)
      <- Response
  -> ResponseEmitter
```

The composition root constructs the graph. Its root route manifest explicitly combines named route-area lists, keeping the complete application map visible without placing every endpoint in one file. These lists are composition fragments, not discoverable providers.

The router stores objects, not class names, so dispatch does not need reflection or a container. It validates the complete list and builds immutable method/path and path/method indexes once. Exact dispatch and 405 lookup then use direct array access. Handlers depend on the smallest concrete boundary needed for the current design.

## Source responsibilities

- `Application`: selects HTTP outcomes and delegates to one handler.
- `Routing`: literal method/path matching only.
- `Http`: request/response values and final emission.
- `Database`: explicit PDO execution and query accounting.
- `example`: proves the complete manual wiring path with a bounded read and transactional write.

There are no providers, repositories, models, middleware pipelines, or controllers in the core. Those labels may be introduced only when they represent a proven responsibility that cannot remain clear in a handler.
