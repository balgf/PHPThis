# Starting a PHPThis application

PHPThis is experimental pre-alpha software and does not yet publish an installable application skeleton. The repository currently provides the framework library, a proof application under `example/`, a versioned consumer contract, and a project-owned AI context template.

The template establishes how an AI learns the application before feature work begins. It is documentation scaffolding, not generated knowledge: every placeholder must be replaced with a verified project fact.

## Create the application context

For a new application:

1. Add PHPThis as a project dependency using the installation method appropriate to the version being evaluated.
2. Copy the contents of `vendor/phpthis/framework/templates/application/` into the new application root, preserving the hidden `.ai/` directory. When evaluating from a PHPThis source checkout instead, use its `templates/application/` directory.
3. Replace every `{{PLACEHOLDER}}` in `AGENTS.md` and `.ai/`.
4. Add the application's accepted architectural decisions to `docs/decisions/README.md`.
5. Commit the completed application context before asking an AI to implement the first feature.

The template contains representative rows for terms, datasets, integrations, and constraints. Delete unused optional rows or replace the relevant section with `NOT_APPLICABLE(reason)`; never invent filler merely to remove a placeholder.

When PHPThis is installed in Composer's default vendor directory, the contract referenced by the template is:

```text
vendor/phpthis/framework/docs/consumer-contract.md
```

If the project uses a different vendor directory, update that path in its `AGENTS.md`. For an existing application, merge the template deliberately; never overwrite established project instructions or decisions.

From the application root, this command should return no matches after customization:

```bash
rg -n '\{\{[A-Z0-9_]+\}\}' AGENTS.md .ai
```

## What the application must supply

PHPThis already defines framework mechanics. The application context should add only facts that alter implementation decisions:

- the product purpose, users, non-goals, and canonical domain vocabulary;
- the actual composition root, route manifest, source boundaries, and dependency direction;
- database engines, large or sensitive tables, result bounds, query budgets, index expectations, and transaction constraints;
- external services, timeouts, idempotency requirements, retry ownership, and observable side effects;
- authentication and authorization boundaries;
- runtime, deployment, worker, logging, and incident-response assumptions;
- the one complete check command and any focused verification commands.

Do not restate ordinary PHP syntax or copy the framework repository's maintainer `.ai/` directory. That directory refers to PHPThis internals such as its example, framework tests, and profile tooling. The application template is intentionally separate.

## Keep it useful

- Keep `AGENTS.md` short enough to read for every task.
- Use `.ai/README.md` as a task router and load only the relevant area guide.
- State concrete limits and paths instead of broad advice.
- Link to source-of-truth schemas, contracts, and decisions instead of duplicating them.
- Update the context in the same change when a public application pattern changes.
- Convert critical prose rules into PHPStan checks, tests, or other deterministic project checks when practical.
- Remove stale statements promptly; incorrect context is worse than absent context.

The application owns these files. Framework upgrades may update the consumer contract, but they must never replace project-specific instructions automatically.

## Current limitation

The template does not yet provide a complete application tree or a consumer-project validity runner. Until those are shipped, adopters must configure their own autoloading, PHPStan paths, profile enforcement, guardrails, and tests according to the consumer contract. A separate `phpthis/skeleton` package is planned only after this application shape has been exercised in real projects.
