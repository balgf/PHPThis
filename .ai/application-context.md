# Application AI context contract

This guide applies when changing `docs/consumer-contract.md`, `docs/knowledge-map.md`, `docs/getting-started.md`, `docs/crud.md`, `docs/caching.md`, `templates/application/`, `skeleton/`, ADR 009, ADR 011, ADR 013, ADR 016, or ADR 017.

Rules:

- Keep the framework-maintainer `AGENTS.md` and `.ai/` separate from the application template.
- Preserve AI as the primary author and knowledge interface while keeping human intent, consequential approval, and accountability explicit.
- Require framework explanations to use the installed contract, knowledge map, source, and tests rather than model memory.
- Keep the framework consumer contract portable: do not include maintainer-only paths, framework source limits, example-specific behavior, or fixture mechanics.
- Use `application AI context` for project-owned instructions. Reserve `harness` for executable test and evaluation infrastructure.
- Keep `templates/application/` documentation-only for deliberate adoption by existing projects.
- Keep `skeleton/` independently installable, runnable, and free of unresolved template tokens; its health-only starter resolves CRUD policy as not applicable.
- Use visible `{{UPPER_SNAKE_CASE}}` placeholders rather than plausible sample facts.
- Require each placeholder to be replaced by a verified fact or an explicit not-applicable statement before feature work.
- Record a source and verification date for volatile scale or operational claims.
- Application rules may strengthen but never weaken the installed consumer contract or Strict Profile.
- Keep the CRUD reference profile optional application structure. Consumers may record one coherent alternate placement and naming rule, but cannot weaken the installed consumer contract or Strict Profile.
- Route consumer CRUD work through installed `vendor/phpthis/framework/docs/crud.md`, never the maintainer-only `.ai/crud.md`.
- Require application context to record identifier and route policy, including immediate conversion of a validated path integer to a concrete identifier, plus pagination, create identity and conflicts, `PUT`/`PATCH` and concurrency, missing behavior, delete and retention, authorization, and audit ownership.
- When sessions are adopted, require application context to record typed service boundaries and key ownership, cookie policy, native file-storage topology, cleanup, verified PHP settings, and each applicable authentication, regeneration, expiry, logout, revocation, and CSRF policy; mark absent concerns explicitly not applicable.
- Require application context to record an explicit HTTP response policy and separately adopt or reject server-side data caching. An adopted data cache records its narrowly named typed services, backend and topology, bounded versioned tenant-aware keys and payloads, finite lifetimes, invalidation and stale-refill behavior, failure and stampede behavior, redacted aggregate observability, and cold-cache plus cache-specific concurrency evidence.
- Keep authentication, authorization, session state, permissions, secrets, and other security decisions outside an initial application data-cache slice unless a later accepted decision defines stronger invariants and evidence.
- Do not add a generic CRUD runtime, discovery mechanism, filesystem enforcement, or checker rule for directories and names.
- Require `.ai/data.md` to name every connection's engine/version, PDO extension, non-secret configuration source, schema/dialect authority, integration command, and lack of cross-connection atomicity.
- Never place credentials, tokens, private keys, customer data, production payloads, runtime dumps, or chat transcripts in the template.
- Do not claim that Composer dependency installation inherits root scripts or development dependencies; the skeleton must declare both explicitly.
- Keep template links valid after the files are copied to an application root; do not link back with repository-relative `../../` paths. Treat every hardcoded `vendor/phpthis/framework/` path in copied `AGENTS.md` and `.ai/` files as one installation assumption that must be updated together when Composer uses a non-default vendor directory.
- Keep the role, authority, and human-decision language aligned across the consumer contract, skeleton, and application template.

Run `composer check`, inspect unresolved placeholders in the documentation-only template, execute the isolated skeleton-consumer proof, and verify the exact framework archive inventory before a release.
