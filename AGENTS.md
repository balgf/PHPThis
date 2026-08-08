# Instructions for AI coding agents

This file is the universal entrypoint for framework-maintainer work. Concern-specific rules live in the current guide routed by `.ai/README.md`; do not copy them into this universal entrypoint.

## Authoring model

You are the primary code author and knowledge interface for this repository. Answer from the current checkout: inspect the applicable contract, current guide, source, and tests, and name that evidence. Do not rely on remembered PHPThis behavior or present a proposal as an existing feature.

The human supplies intent and remains accountable. Surface unresolved product, architecture, security, data, release, and operational choices for human judgment. You may draft a decision record, but only an accountable human may accept it.

## Early database setup gate

When a request selects or sets up an application database engine but leaves database scope or migration scope unresolved, inspect only the prompt and current application facts needed to avoid repeating an accepted decision. Ask one combined clarification: configuration only, connection to an existing server, or project-local server provisioning; and deferred migrations or an application-owned migration foundation. Local development is context, not authorization to connect to or probe a server, install, provision, or mutate anything. Resume the ordinary read order after scope is resolved. An explicit request proceeds without a redundant question; `.ai/change-workflow.md` owns the complete gate.

## Read order

1. Read `VISION.md`.
2. Read `.ai/README.md`.
3. Read `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/strict-profile.md`.
4. Start with the one current operational guide selected by `.ai/README.md`.
5. Inspect the concrete source and nearest tests on the execution path.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

## Authority and safety

- The current Consumer Contract and Strict Profile define the accepted framework and application-authoring boundary.
- Current `.ai/` guides own mutable operational rules. `docs/` owns durable public knowledge and decision rationale.
- Preserve the contract when guidance conflicts, report the conflict, and distinguish current behavior, application policy, and proposals.
- Never invent product intent, human approval, external-system facts, or PHPThis behavior unsupported by the current checkout.
- Never write credentials, tokens, private keys, customer data, production payloads, or other secrets into context, source comments, fixtures, logs, or reports.

## Universal red lines

- Use PHP 8.4, `declare(strict_types=1);`, final named classes, interface extension points, and visible manual composition.
- Keep routes explicit and handlers on `RequestHandler::handle(Request): Response`.
- Keep I/O visible and bounded. Execute application SQL only through direct `Connection` calls with finite compile-time-constant SQL and distinct named bindings.
- Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.
- Do not add runtime discovery, reflection wiring, a service container, ORM, Active Record, lazy loading, query builder, repository layer, facade, global helper, macro system, dynamic proxy, hidden fallback, or a second execution pattern.
- Do not use magic methods other than `__construct`, weaken maximum-level analysis, or suppress a Strict Profile finding with a baseline, ignore, exclusion, or comment exemption.
- Every observable behavior change requires automated behavior evidence and the applicable public Markdown update.

## Project gate

Install development dependencies once, then run from the repository root:

```bash
composer install
composer check
```

`composer check` is the complete validity gate. Focused tests may shorten the repair loop but never replace it.
