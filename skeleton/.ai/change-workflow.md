# Application change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the route or entrypoint, dependencies, data touched, side effects, and failure paths.
3. Read the smallest relevant application guide and inspect the concrete source and tests.
4. Resolve missing project facts before choosing an implementation.
5. Surface consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment before treating them as accepted.
6. Reuse the application's canonical PHPThis pattern.
7. Add or update automated tests for expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource bounds.
8. For database work, compare materially different fixture sizes and assert constant statement count.
9. Implement the smallest direct change and update application context when the public pattern changes.
10. Run focused verification, then `composer check`.
11. Report behavior proven, files changed, resource cost, consequential decisions, and any production concern not exercised locally.

A task is not complete merely because its happy path runs or static analysis passes. The execution path, bounds, failures, and automated behavior evidence must remain apparent to the next agent.

For an explanation-only request, follow the same evidence path but do not edit the repository. Cite current files and clearly separate installed behavior from an application policy or proposal.
