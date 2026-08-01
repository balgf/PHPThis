# Application change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the route or entrypoint, dependencies, data touched, side effects, and failure paths.
3. Read the smallest relevant application guide and inspect the concrete source and tests.
4. Resolve missing project facts before choosing an implementation; do not guess at scale, authorization, or external contracts.
5. Surface consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment before treating them as accepted.
6. For an operation that accepts external data, record its raw representation, complete bounds, exact accepted field representations, absent-versus-null and normalization policy, typed boundary value, failure contract, and duplicate-key proof limit before implementation.
7. For external configuration, update `.ai/configuration.md`, preserve one environment-reading file and process-specific final readonly types, and define failure, separation, injection, rotation, redaction, and child-process evidence before implementation.
8. Reuse the application's canonical PHPThis pattern.
9. Add or update automated tests for expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource bounds. Invalid input must prove no operation-owned downstream I/O or mutation and zero typed-seam calls when one exists; separately account for policy work ordered before parsing.
10. For database behavior, compare materially different fixture sizes and assert constant statement count.
11. Implement the smallest direct change and update the relevant application context when the public pattern changes.
12. Run focused verification, then the complete application validity gate defined in `.ai/testing.md`.
13. Report behavior proven, files changed, resource cost, consequential decisions, and any production concern not exercised locally.

## Ambiguous database setup scope

Before implementing a request that selects or sets up a database engine:

1. Inspect the prompt and current project facts before asking anything already answered.
2. Unless another environment is named, treat local development as context only. It does not authorize connection attempts or probes, package installation, service or container creation, database or role creation, schema mutation, or another external side effect.
3. Resolve database scope as configuration only, connection to an existing server, or project-local server provisioning.
4. Resolve schema scope separately as deferred migrations or an application-owned migration foundation.
5. Treat a current `NOT_APPLICABLE` marker as present-state evidence, not an accepted decision about a new adoption request.
6. Ask every unresolved scope choice in one concise message before external I/O or mutation. Do not repeat a choice resolved by the prompt or an explicit accepted project decision; after selection, ask only for concrete facts genuinely missing from that path.
7. Keep production hardening, backups, high availability, deployment credentials, recovery, and unrelated operations outside the task unless requested.
8. Implement the smallest complete selected slice. Use focused checks while editing and run the complete application gate once at the end.

After migration scope is adopted, inspect `.ai/migrations.md` for an existing source-directory and namespace decision. When none exists, PHPThis recommends `src/Database/Migrations/` with the matching `{{APPLICATION_ROOT_NAMESPACE}}\Database\Migrations` namespace. A consumer may select another coherent structure; record it in `.ai/migrations.md` and treat it as authoritative. Do not relocate an established migration structure without explicit human approval, and do not add path enforcement or filesystem discovery.

For the ambiguous prompt:

> Please setup PostgreSQL as our main DB.

ask:

> Before I change anything: should I only add PostgreSQL configuration, connect this project to an existing PostgreSQL server, or provision a project-local PostgreSQL server? Should migrations remain deferred, or should I add an application-owned migration foundation too?

An explicit request such as “Provision a project-local PostgreSQL server, configure it, and do not add migrations” proceeds without this scope question.

A task is not complete merely because its happy path runs or static analysis passes. The execution path, bounds, failures, and automated behavior evidence must remain apparent to the next agent.

For an explanation-only request, follow the same evidence path but do not edit the repository. Cite current files and clearly separate installed behavior from an application policy or proposal.
