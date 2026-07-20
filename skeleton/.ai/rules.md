# Application rules

These rules supplement installed PHPThis Consumer Contract v4 and Strict Profile v2. They may not alias or weaken framework rules.

## Required

- Preserve the dependency direction and boundaries in `.ai/architecture.md`.
- Resolve missing product, scale, authorization, and external-contract facts before implementation.
- Keep `NOT_APPLICABLE(INPUT)` while no operation accepts application-owned fields. Before adoption, record one operation-specific named parser factory, its final readonly request or command, downstream behavior or justified typed seam, complete bounds, exact field representations, absent-versus-null and normalization policy, parser position relative to request policy, generic failure contract, and exclusion from operation-owned downstream work.
- Keep `NOT_APPLICABLE(REQUEST_POLICY)` while every route is public. Before protecting a route, use the installed action-specific request-policy composition with explicit order, concrete principal and tenant values, replaceable policies, isolated policy and protected budgets, and denial tests.
- Keep every external side effect and failure path visible at a named boundary.
- Before database adoption, verify and record finite SQL-structure choices, bounded-list shapes, and isolated least-privileged runtime authority in `.ai/data.md`.
- Preserve the current explicit `Cache-Control: no-store` policy for health success, route miss, method rejection, mapped client failure, and unknown failure. Start every response path added later with explicit `no-store`, then adopt `private` or `public` only after recording finite freshness or revalidation, validators, `Vary`, intermediary topology, observability, and tests where applicable.
- Before cache adoption, verify and record narrowly named typed service ownership, authoritative rebuild paths, backend topology, versioned environment- and tenant-scoped keys, bounded payloads and finite TTLs, invalidation, stale-refill, failure and stampede behavior, observability, and cold, warm, failure, isolation, stale-refill race, and concurrency evidence.
- Run `composer check` before reporting completion.

## Forbidden

- Do not invent schema meaning, production limits, authorization, or external-service behavior.
- Do not add a generic validator, result wrapper, string-rule language, automatic request binding, reflection hydration, mass assignment, sanitization magic, or unvalidated array beyond its named boundary.
- Do not parse the same inbound representation again downstream, silently transform or coerce an application field, or treat validation as output encoding or authorization.
- Do not add an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not add a generic cache service, global cache helper, hidden cache-aside behavior, automatic query caching, implicit forever TTL, or arbitrary PHP object deserialization.
- Do not use cached data as a source of truth or cache sessions, authentication state, authorization decisions, permissions, credentials, or secrets.
- Do not infer that `Set-Cookie`, a server-side cache miss, or a server-side cache hit makes an HTTP response safely private, uncacheable, or public.
- Do not add a cache helper, middleware default, or response post-processor to hide which response-producing path owns its HTTP cache policy.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not add middleware or policy registries, a request-context or attribute bag, hidden tenant resolution, an implicit or global authorization scope, or stored or cached authorization decisions.
- Do not copy secrets or real customer data into code, context, fixtures, logs, or reports.
- Do not add runtime-built SQL, an SQL sanitizer, or a runtime database identity with migration or administrative authority.
- Do not claim that PHT006, tenant predicates, adversarial bindings, or base PDO transport tests universally prove authorization, tenant isolation, injection safety, or application-SQL portability.
- Do not read `$_SESSION`, call native `session_*` functions, manually emit a framework session cookie, or add a generic session helper.
- Do not add a second spelling or execution path for an existing operation.

## Starter constraints

- Keep `GET /health` exact until the project deliberately changes its liveness contract.
- Keep `NOT_APPLICABLE(INPUT)` until an operation accepts application-owned external fields and its boundary policy and adversarial tests replace that marker in `.ai/architecture.md` and `.ai/testing.md`.
- Keep session state not applicable until its typed key ownership, cookie, isolated file-storage, and concurrency policy are recorded, together with each applicable identity, expiry, revocation, and CSRF concern or explicit non-applicability.
- When session state is adopted, keep mutation callbacks bounded and side-effect-free and complete fallible work before the final immediately committed mutation.
- Keep `HTTP_CACHE_POLICY(NO_STORE)` for every currently shipped response. A new path owns and tests its explicit policy before replacing `no-store`; server-side caching remains a separate decision.
- Keep `NOT_APPLICABLE(CACHE)` until the cache contract is recorded across `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, and `.ai/testing.md`; the starter includes no cache code or dependency.
- Replace these starter constraints with verified product constraints before feature work.
