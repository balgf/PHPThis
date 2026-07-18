# Application AI context contract

This guide applies when changing `docs/consumer-contract.md`, `docs/getting-started.md`, `templates/application/`, or ADR 009.

Rules:

- Keep the framework-maintainer `AGENTS.md` and `.ai/` separate from the application template.
- Keep the framework consumer contract portable: do not include maintainer-only paths, framework source limits, example-specific behavior, or fixture mechanics.
- Use `application AI context` for project-owned instructions. Reserve `harness` for executable test and evaluation infrastructure.
- Keep the template documentation-only until a separately verified application skeleton exists.
- Use visible `{{UPPER_SNAKE_CASE}}` placeholders rather than plausible sample facts.
- Require each placeholder to be replaced by a verified fact or an explicit not-applicable statement before feature work.
- Record a source and verification date for volatile scale or operational claims.
- Application rules may strengthen but never weaken the installed consumer contract or Strict Profile.
- Never place credentials, tokens, private keys, customer data, production payloads, runtime dumps, or chat transcripts in the template.
- Do not claim that Composer dependency installation inherits root scripts, development dependencies, or consumer guardrails.
- Keep template links valid after the files are copied to an application root; do not link back with repository-relative `../../` paths.

Run `composer check`, inspect unresolved placeholders in the framework's own application template, and confirm that a Composer archive contains the hidden `.ai/` files before a release.
