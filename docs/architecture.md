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
          -> application request-policy adapter (for a protected route)
            -> authenticate
            -> resolve tenant
            -> authorize named action
            -> protected operation with explicit principal and tenant context
          -> operation-specific parser -> final readonly command or request
          -> typed application operation (when business or transaction ownership requires a seam)
            -> operation-specific transaction owner -> Connection
          -> typed application session service (when needed)
          -> typed application cache service (when deliberately adopted)
          -> application-owned job insert in the same transaction (when adopted)
          -> Connection (when the handler directly owns its data work)
        <- Response
    -> ErrorResponseRegistry (only after a named failure)
    -> SessionLifecycle finish or abort (when configured)
  -> ResponseEmitter
```

The composition root constructs the complete graph, including the body limit and exact error-response registrations. Its root route manifest explicitly combines named route-area lists, keeping the complete application map visible without placing every endpoint in one file. These lists are composition fragments, not discoverable providers.

Only `public/index.php` reads PHP superglobals and passes them to the application-owned terminal coordinator. `RequestReader` receives those arrays through the one `RequestBoundary`, reads at most its configured body limit plus one byte, and creates one immutable `Request`. `RequestBoundary` then begins the optional session lifecycle, calls the application, maps only exact registered failure classes, and performs one finalization outside that error-mapping catch. Unknown failures abort active and never-issued session work before being rethrown to `TerminalRequestCoordinator`, which calls `UnknownFailureBoundary::respond()` to select one generic 500 response with `Cache-Control: no-store`. A completed update to a browser-owned identifier is already committed and is not rolled back. Beginning a session lifecycle does not open native storage; only an explicit application session operation does.

ADR 023 places one application-owned terminal coordinator around that path without changing `RequestBoundary`, `UnknownFailureBoundary`, or `Connection`. It generates the correlation ID, adds `X-Request-ID` to the final immutable response, derives one closed bounded event from a finite list of distinct connection budgets and traces, and makes exactly one sink invocation attempt before `ResponseEmitter`. Sink failure is isolated and cannot replace the response. This is explicit front-controller composition, not middleware, a global logger, a facade, a service locator, an event pipeline, or hidden instrumentation.

The router stores objects, not class names, so dispatch does not need reflection or a container. It validates the complete list once. Fully literal routes use immutable method/path and path/method indexes. A parameterized route has at most two full-segment placeholders, uses only canonical `positive-int` or bounded `token` values, and is compiled into a deterministic state index. Exact literal routes take precedence, overlapping parameterized declarations fail at construction, and dispatch plus 405 lookup may traverse only the bounded request path and compiled state transitions rather than scanning the route list or an index collection. A successful lookup returns immutable `RouteMatch` metadata. `Application` copies the normalized request with the match's immutable `PathParameters` and passes it to the existing `RequestHandler::handle(Request)` interface; static routes receive empty parameters. Handlers depend on the smallest concrete boundary needed for the current design and immediately convert each validated path value into a route-specific concrete identifier.

## Source responsibilities

- `Application`: selects HTTP outcomes, copies matched routing metadata onto the immutable request, and delegates to one handler.
- `Routing`: direct exact-literal matching plus one deterministic state index for at most two bounded full-segment typed parameters; no route or index scan and no domain binding.
- `Http`: bounded runtime ingestion, immutable request/response values, exact error mapping, and final emission.
- `Session`: bounded immutable snapshots and one lazy native-file session lifecycle; authentication, authorization, expiry, and CSRF remain application policy.
- `Database`: explicit PDO execution and query accounting.
- `example`: proves the complete manual wiring path, including its application-owned terminal request coordinator and sink; the optional feature-first CRUD profile with bounded application-owned user and document continuations; a typed Create operation with visible transactional data and job publication; one SQLite-specific one-shot durable-job worker; bounded typed-item Get use cases; and nested protected document routes with explicitly ordered, replaceable application request-policy adapters.

ADR 021 adds no core input or operation namespace. `CreateUserHandler` owns HTTP media checks, complete `CreateUserCommand` parsing, and response preparation. It then calls `CreateUserOperation`, the explicit boundary to the independently meaningful Create transaction. `TransactionalCreateUser` is the one example-owned concrete transaction operation and retains the direct `Connection` calls, exactly three fixed statements, transaction, budget, and trace: user, event, and commit-visible welcome-job insertion. This responsibility split makes rejected-input non-entry directly testable; the test does not itself justify a generic service layer, repository, query object, data mapper, command bus, SQL helper, automatic handler split, or second execution path.

ADR 024 adds no core job namespace. The example parses one bounded stored envelope, dispatches one finite type, and runs one fresh SQLite worker invocation that claims at most one due row. Its complete claim, idempotent effect, completion, retry, and dead-letter SQL remains application-owned and fenced by an unexpired opaque lease token. Repetition belongs to an external supervisor; delivery is at least once and no external exactly-once behavior is claimed.

ADR 020 adds no core policy namespace or request state. One application route adapter owns a fixed `authenticate -> resolve tenant -> authorize -> protected handler` sequence and passes concrete immutable principal and tenant values explicitly. The reference policies are I/O-free; any policy that reads storage uses a named connection, budget, and trace distinct from protected handler work. Authorization remains current per request, and protected SQL remains explicitly tenant- and resource-scoped after the decision.

ADR 022 adds no core data, pagination, or SQL namespace. `ListDocumentsHandler` owns eight complete raw SQLite statements: two order directions crossed with omitted or one-to-three-category shapes. A direct empty list or parsed `['']` shape returns without protected SQL; native PHP inputs such as `?categories[]=` produce that parsed shape. The exact SQL and named parameter arrays remain together at direct `Connection` calls; requested account, resolved tenant account, principal membership, cursor presence and values, categories, and the 51-row lookahead stay visible. There is no ORM, query builder, repository, generic paginator, SQL/binding/placeholder helper, generated or dynamic SQL, transaction callback, or dialect abstraction. Its cursor traversal is not a snapshot, its SQLite version is not pinned, and its application SQL is not evidence for another engine.

There is no cache namespace or cache mechanism in the core. HTTP response policy remains an explicit property of the response-producing path. Framework-owned 404, 405, and unknown-failure 500 responses explicitly prohibit storage; the skeleton and example do the same for their current handlers. PHPThis does not rewrite arbitrary handler responses, so every additional application path still owns and tests its policy. If an application later adopts server-side caching, it manually wires a narrowly named typed application service at the handler boundary; that service owns one cache-aside execution path and its backend-specific policy. It is not a generic key-value facility, middleware, or replacement for the authoritative data path.

There are no providers, repositories, models, middleware pipelines, request-context bags, policy registries, generic paginators, SQL/binding/placeholder helpers, or controllers in the core. `RequestBoundary` is one named transport boundary, not a composable middleware chain. Routing metadata enters only through immutable `PathParameters`; session, principal, tenant, and authorization state do not enter `Request`. An application places narrowly named typed services with explicit non-overlapping key ownership in front of one `SessionLifecycle` instead of adding helpers or a generic key-value repository. An operation interface or operation-specific SQL owner is introduced only for a concrete tested responsibility such as ADR 021's typed use-case entry and Create transaction ownership; collection SQL remains in its handler when the complete direct calls are already clear.
