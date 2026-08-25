# Application-context distribution contract

Use this guide only for cross-artifact application-context ownership and distribution: the current Consumer Contract, its conditional upgrade/history route, `docs/knowledge-map.md`, the context entrypoint in `docs/getting-started.md`, task routing, application template and skeleton context inventories, package inventory, and the guard or installed-consumer evidence that keeps those surfaces coherent. A concern-specific skeleton or template edit starts with that concern's routed guide; add this guide only when the edit also changes an inventory, router, shared authority statement, or distribution boundary.

ADR 009, ADR 011, ADR 013, ADR 044, and ADR 058 own the context, template, optional CRUD-profile, and task-routing decisions. Read one only when reviewing or changing the decision it records. Concern-specific decisions remain conditional context for their routed guides.

## Ownership and authority

- Keep one current owner for every mutable authoring rule. Universal entrypoints retain only universal authority, safety, scope, validity, and verification. Concern-specific detail belongs in its routed current guide; contract evolution and historical ADRs are loaded only for upgrade or decision work.
- Preserve AI as the primary author and knowledge interface while keeping human intent, consequential approval, and accountability explicit.
- Require framework explanations to use the installed current contract, knowledge map, source, and tests rather than model memory.
- Keep role, authority, and human-decision language aligned across the Consumer Contract, skeleton, and application template.
- Application rules may strengthen but never weaken the installed Consumer Contract or Strict Profile.
- Use `application AI context` for project-owned instructions. Reserve `harness` for executable test and evaluation infrastructure.

## Distribution surfaces

- Keep the framework-maintainer `AGENTS.md` and root `.ai/` separate from the application template.
- Keep the framework Consumer Contract portable: do not include maintainer-only paths, framework source limits, example-specific behavior, or fixture mechanics.
- Keep `templates/application/` documentation-only for deliberate adoption by existing projects.
- Keep `skeleton/` independently installable, runnable, configuration-free, and free of unresolved template tokens. Its health-only starter records every absent optional concern explicitly, including CRUD.
- Use visible `{{UPPER_SNAKE_CASE}}` placeholders instead of plausible sample facts. Require every placeholder to be replaced by a verified fact or an explicit not-applicable statement before feature work.
- Record a source and verification date for volatile scale or operational claims.
- Never place credentials, tokens, private keys, customer data, production payloads, runtime dumps, or chat transcripts in the template or skeleton context.
- Do not claim that Composer dependency installation inherits root scripts or development dependencies; the skeleton declares both explicitly.
- Keep copied template links valid from an application root; do not link back through repository-relative `../../` paths. Treat every hardcoded `vendor/phpthis/framework/` path in copied `AGENTS.md` and `.ai/` files as one installation assumption that must be updated together when Composer uses a non-default vendor directory.

## Concern routing

- Route a concern-specific public guide, application record, template, skeleton marker, checked reference, or test through that concern's row in `.ai/README.md`. Do not duplicate its normative policy here.
- Add this guide to concern work only when adding, removing, or renaming a packaged context file; changing the knowledge or task router; changing shared template/skeleton authority; or changing package, guard, or installed-consumer coherence.
- When a concern gains or loses a context file, update the current contract and knowledge routes, template and skeleton inventories, package allowlist, focused distribution guardrails, and installed-consumer proof together. Do not add runtime policy discovery or generated policy.

## Verification

For a context-distribution change, inspect unresolved placeholders in the documentation-only template, execute the isolated skeleton-consumer proof, verify the exact framework archive inventory, and run `composer check`. Verify routing by representative tasks and confirm that each task reaches one current concern owner without requiring an unsupported claim from omitted context. Context-size measurements are advisory evidence only: report the fixed universal set separately from task-specific files and never make words, bytes, or tokens a validity threshold, checker rule, `PHT` diagnostic, or substitute for route-clarity and unsupported-claim review.
