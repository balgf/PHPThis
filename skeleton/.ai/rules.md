# Application rules

These rules supplement installed PHPThis Consumer Contract v3 and Strict Profile v2. They may not alias or weaken framework rules.

## Required

- Preserve the dependency direction and boundaries in `.ai/architecture.md`.
- Resolve missing product, scale, authorization, and external-contract facts before implementation.
- Keep every external side effect and failure path visible at a named boundary.
- Before database adoption, verify and record finite SQL-structure choices, bounded-list shapes, and isolated least-privileged runtime authority in `.ai/data.md`.
- Preserve the current explicit `Cache-Control: no-store` policy for health success, route miss, method rejection, mapped client failure, and unknown failure. Start every response path added later with explicit `no-store`, then adopt `private` or `public` only after recording finite freshness or revalidation, validators, `Vary`, intermediary topology, observability, and tests where applicable.
- Before cache adoption, verify and record narrowly named typed service ownership, authoritative rebuild paths, backend topology, versioned environment- and tenant-scoped keys, bounded payloads and finite TTLs, invalidation, stale-refill, failure and stampede behavior, observability, and cold, warm, failure, isolation, stale-refill race, and concurrency evidence.
- Run `composer check` before reporting completion.

## Forbidden

- Do not invent schema meaning, production limits, authorization, or external-service behavior.
- Do not add an undocumented side effect, retry, fallback, cache, queue, or scheduled operation.
- Do not add a generic cache service, global cache helper, hidden cache-aside behavior, automatic query caching, implicit forever TTL, or arbitrary PHP object deserialization.
- Do not use cached data as a source of truth or cache sessions, authentication state, authorization decisions, permissions, credentials, or secrets.
- Do not infer that `Set-Cookie`, a server-side cache miss, or a server-side cache hit makes an HTTP response safely private, uncacheable, or public.
- Do not add a cache helper, middleware default, or response post-processor to hide which response-producing path owns its HTTP cache policy.
- Do not invent human approval or claim unsupported framework or application behavior.
- Do not copy secrets or real customer data into code, context, fixtures, logs, or reports.
- Do not add runtime-built SQL, an SQL sanitizer, or a runtime database identity with migration or administrative authority.
- Do not read `$_SESSION`, call native `session_*` functions, manually emit a framework session cookie, or add a generic session helper.
- Do not add a second spelling or execution path for an existing operation.

## Starter constraints

- Keep `GET /health` exact until the project deliberately changes its liveness contract.
- Keep session state not applicable until its typed key ownership, cookie, isolated file-storage, and concurrency policy are recorded, together with each applicable identity, expiry, revocation, and CSRF concern or explicit non-applicability.
- When session state is adopted, keep mutation callbacks bounded and side-effect-free and complete fallible work before the final immediately committed mutation.
- Keep `HTTP_CACHE_POLICY(NO_STORE)` for every currently shipped response. A new path owns and tests its explicit policy before replacing `no-store`; server-side caching remains a separate decision.
- Keep `NOT_APPLICABLE(CACHE)` until the cache contract is recorded across `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, `.ai/operations.md`, and `.ai/testing.md`; the starter includes no cache code or dependency.
- Replace these starter constraints with verified product constraints before feature work.
