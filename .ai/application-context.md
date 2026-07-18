# Application AI context contract

This guide applies when changing `docs/consumer-contract.md`, `docs/knowledge-map.md`, `docs/getting-started.md`, `templates/application/`, `skeleton/`, ADR 009, or ADR 011.

Rules:

- Keep the framework-maintainer `AGENTS.md` and `.ai/` separate from the application template.
- Preserve AI as the primary author and knowledge interface while keeping human intent, consequential approval, and accountability explicit.
- Require framework explanations to use the installed contract, knowledge map, source, and tests rather than model memory.
- Keep the framework consumer contract portable: do not include maintainer-only paths, framework source limits, example-specific behavior, or fixture mechanics.
- Use `application AI context` for project-owned instructions. Reserve `harness` for executable test and evaluation infrastructure.
- Keep `templates/application/` documentation-only for deliberate adoption by existing projects.
- Keep `skeleton/` independently installable, runnable, and free of unresolved template tokens.
- Use visible `{{UPPER_SNAKE_CASE}}` placeholders rather than plausible sample facts.
- Require each placeholder to be replaced by a verified fact or an explicit not-applicable statement before feature work.
- Record a source and verification date for volatile scale or operational claims.
- Application rules may strengthen but never weaken the installed consumer contract or Strict Profile.
- Never place credentials, tokens, private keys, customer data, production payloads, runtime dumps, or chat transcripts in the template.
- Do not claim that Composer dependency installation inherits root scripts or development dependencies; the skeleton must declare both explicitly.
- Keep template links valid after the files are copied to an application root; do not link back with repository-relative `../../` paths.
- Keep the role, authority, and human-decision language aligned across the consumer contract, skeleton, and application template.

Run `composer check`, inspect unresolved placeholders in the documentation-only template, execute the isolated skeleton-consumer proof, and verify the exact framework archive inventory before a release.
