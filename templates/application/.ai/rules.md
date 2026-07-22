# Application rules

These rules supplement installed PHPThis Consumer Contract v9 and Strict Profile v2. They may strengthen those rules but may not weaken them.

## Required

- Use the canonical domain terms defined in `.ai/project.md`.
- Preserve the dependency direction and boundaries defined in `.ai/architecture.md`.
- Apply the data and resource limits defined in `.ai/data.md`.
- Declare every resource path identifier with the narrowest fixed type: `positive-int`, `uuid`, or `ulid` for that canonical representation, and `token` only when it is genuinely opaque. Use the matching `PathParameters` accessor, immediately wrap the unchanged value in an application-owned route-specific identifier, and enforce narrower domain rules before database work; never normalize, bind, look up, or fall back between route types.
- Prove invalid resource-identifier syntax returns `404` with zero handler and database work, and prove a canonical valid path with the wrong method returns `405`.
- Keep every adopted application-owned request-handler decorator final, route-local, and explicit: exactly one downstream `RequestHandler`, complete unrolled nesting beside each route, zero-or-one delegation with the exact same immutable `Request` instance, unchanged exception propagation, explicit immutable `Response` replacement with complete field preservation, and named bounded side effects with behavior tests.
- Parse each complete inbound operation representation exactly once through the operation-specific named factory recorded in `.ai/architecture.md`, into a concrete final readonly request or command with a private constructor, before downstream operation behavior. Add a typed operation seam only when HTTP adaptation and an independently meaningful business or transaction responsibility need separate ownership.
- Enforce every recorded byte, depth, field, list, item, and scalar bound; distinguish absence with `array_key_exists`, reject unknown fields, check runtime types before conversion, and accept only the operation's recorded canonical representations.
- Keep field normalization opt-in and exactly recorded with transformation order, pre- and post-transform bounds, collision behavior, and retained canonical value. Keep validation separate from output encoding, SQL binding, and current authorization.
- Keep parser position relative to authentication, tenant resolution, and authorization explicit. Rejection prevents operation-owned downstream I/O and mutation; it does not erase separately bounded policy work deliberately ordered before parsing.
- Keep every SQL structural selector, bounded-list shape, runtime capability, prohibited capability, migration-authority boundary, and verification source current in `.ai/data.md`.
- Make every external side effect and failure path named in `.ai/integrations.md` visible in the execution path.
- Preserve the identity, tenant, and authorization boundaries defined in `.ai/architecture.md`.
- Keep each protected route behind its recorded action-specific request-policy adapter with explicit `authenticate -> resolve tenant -> authorize -> handler` order, concrete immutable principal and tenant values, and manually replaceable policies.
- Keep any policy reads on their recorded connections, budgets, and traces, separate from protected handler work; a denial executes no protected query, write, session mutation, cache mutation, or external business side effect.
- Keep one application-owned terminal request-summary coordinator and sink in the visible front-controller path, generated correlation and `X-Request-ID`, at most eight finite database sources, complete redaction, and exactly one failure-isolated sink invocation attempt.
- Keep adopted session state behind typed application services and the deployment policy recorded in `.ai/architecture.md` and `.ai/operations.md`; mutate only owned keys and preserve every unowned key from the supplied snapshot.
- Keep session mutation callbacks bounded and side-effect-free; finish fallible work before the final immediately committed mutation.
- Keep adopted server-side caching behind narrowly named typed services with explicit hit, miss, authoritative-read, write, and invalidation paths; apply the key, payload, TTL, tenant, invalidation, stale-refill, failure, stampede, and observability policies recorded in `.ai/data.md` and `.ai/operations.md`.
- Treat cached payloads as untrusted derived data, parse them into bounded typed projections, and preserve correctness when an entry is absent or evicted.
- Keep HTTP response caching separate from server-side data caching and give each response-producing path an explicit `no-store`, `private`, or `public` policy with finite freshness or revalidation, validators, and complete `Vary` behavior where applicable.
- Start every new or unreviewed response path with an explicit `Cache-Control: no-store` header, including success, redirect, mapped client-error, unknown server-error, and cookie-emitting responses; change that path only after its recorded policy and tests support reuse.
- Keep every adopted operational command behind the sole application console with the finite command and typed-argument map, exit and stream contract, fresh composition, clock, cadence, one-pass work, local overlap topology, supervisor, redaction, and tests recorded in `.ai/cli.md`, `.ai/operations.md`, and `.ai/testing.md`.
- Run the complete application validity gate defined in `.ai/testing.md` before reporting completion.

## Forbidden

- Do not invent missing product behavior, schema meaning, production limits, or external-service semantics.
- Do not add a generic validator, result wrapper, string-rule language, automatic request binding, reflection hydration, mass assignment, sanitization magic, or unvalidated array beyond its named boundary.
- Do not parse the same inbound representation again downstream, silently transform or coerce an application field, or treat validation as output encoding or authorization.
- Do not introduce an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not add application commands to framework `phpthis`, command discovery, class-name dispatch, a service-container command resolver, generic console or scheduler facade, daemon, hidden loop, unrecorded persistent slot or catch-up behavior, or distributed coordination without an accepted application decision and evidence.
- Do not add a generic cache service, global cache helper, hidden cache-aside behavior, automatic query caching, implicit forever TTL, or arbitrary PHP object deserialization.
- Do not use cached data as a source of truth or cache sessions, authentication state, authorization decisions, permissions, credentials, secrets, or another class prohibited by `.ai/architecture.md`.
- Do not infer that `Set-Cookie`, a server-side cache miss, or a server-side cache hit makes an HTTP response safely private, uncacheable, or public.
- Do not add a cache helper, application-owned request-handler decorator, generic or framework middleware default, or response post-processor to hide which response-producing path owns its HTTP cache policy.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not bypass authentication, authorization, validation, audit, or data-retention requirements to simplify a change.
- Do not add a generic or framework middleware interface, pipeline, iterable registry, priority ordering, discovery, `$next` abstraction, request-context or attribute bag, hidden binding or I/O, service-located policy, hidden tenant resolution, an implicit or global authorization scope, or stored or cached authorization decisions. Do not wrap `Application`, `RequestBoundary`, the terminal coordinator, or `ResponseEmitter` in a decorator.
- Do not add framework logging event, sink, or coordinator types; logger facades, global logging helpers, generic or framework logging middleware, terminal observability inside an application-owned request-handler decorator, event pipelines, automatic sink discovery, per-query log I/O, hidden database instrumentation, or durable-delivery claims.
- Do not read `$_SESSION`, call native `session_*` functions, manually emit the framework session cookie, add a generic session helper, or treat stored identity as authorization.
- Do not accept SQL text from external input, invent an SQL sanitizer, interpolate data, or grant the runtime database identity migration or administrative authority to simplify a change.
- Do not claim that PHT006, tenant predicates, adversarial bindings, or base PDO transport tests universally prove authorization, tenant isolation, injection safety, or application-SQL portability.
- Do not copy secrets or real customer data into code, instructions, fixtures, logs, or reports.
- Do not add a second spelling or execution path for an existing application operation.

## Additional project constraints

- {{PROJECT_RULE_1}}
- {{PROJECT_RULE_2}}
- {{PROJECT_RULE_3}}
