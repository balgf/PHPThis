# AI coding instructions for this PHPThis application

This file is the universal entrypoint for AI-authored changes in the checked health-only starter. Concern-specific rules live in the current guide routed by `.ai/README.md`; do not copy them into this universal entrypoint.

Before product feature work, replace generic starter facts in the applicable `.ai/` guides with verified application facts or explicit not-applicable statements.

## Authoring model

You are the primary code author and knowledge interface for this application. Answer from the installed framework version, current application context, concrete source, and tests, and name that evidence. Do not rely on remembered framework behavior or present a proposal as an existing feature.

The human supplies intent and remains accountable. Surface unresolved product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment. You may draft a decision record, but only an accountable human may accept it.

## Early database setup gate

When a request selects or sets up a database engine but leaves database scope or migration scope unresolved, inspect only the prompt and current application facts needed to avoid repeating an accepted decision. Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation. Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything. Resume the ordinary read order after scope is resolved. An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.

## Read order

1. Read installed `vendor/phpthis/framework/docs/consumer-contract.md`.
2. Read installed `vendor/phpthis/framework/docs/knowledge-map.md`.
3. Read `.ai/README.md`.
4. Read `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`.
5. Start with the one current operational guide selected by `.ai/README.md`.
6. Inspect the concrete source and nearest tests on the execution path.

If installed framework context is missing, read `.ai/operations.md` only far enough to install dependencies, then restart this order. If Composer uses a non-default vendor directory, replace the leading `vendor/` segment in every installed path. Never substitute a framework-maintainer checkout for installed application authority.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Authority and safety

- Consumer Contract v15 and Strict Profile v4 are the minimum accepted rules.
- Application `.ai/` guides add verified project facts and may strengthen but never weaken the installed contract.
- Preserve the installed contract when project guidance conflicts, report the conflict, and distinguish installed behavior, application policy, and proposals.
- Never invent product intent, human approval, external-system facts, or unsupported PHPThis behavior.
- Never copy credentials, tokens, private keys, customer data, production payloads, or other secrets into context, source comments, fixtures, logs, or reports.

## Universal red lines

- Keep PHP direct, strictly typed, final, and manually composed; use interfaces for extension points.
- Keep routes, dependencies, I/O, failures, and resource bounds explicit.
- Do not add runtime discovery, reflection wiring, a service container, ORM, query builder, repository layer, facade, global helper, hidden fallback, or a second application pattern.
- Do not suppress the installed profile or replace application-owned behavior tests with static analysis or documentation.

## Project gate

Run `composer check` from the application root. A task is not complete until it passes. Focused tests may shorten the repair loop but never replace the complete check.

Every observable behavior change must add or update application-owned automated tests. The application owns their library and organization; a no-op test command is not evidence.
