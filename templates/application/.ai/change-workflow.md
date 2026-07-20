# Application change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the route or entrypoint, dependencies, data touched, side effects, and failure paths.
3. Read the smallest relevant application guide and inspect the concrete source and tests.
4. Resolve missing project facts before choosing an implementation; do not guess at scale, authorization, or external contracts.
5. Surface consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment before treating them as accepted.
6. For an operation that accepts external data, record its raw representation, complete bounds, exact accepted field representations, absent-versus-null and normalization policy, typed boundary value, failure contract, and duplicate-key proof limit before implementation.
7. Reuse the application's canonical PHPThis pattern.
8. Add or update automated tests for expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource bounds. Invalid input must prove no operation-owned downstream I/O or mutation and zero typed-seam calls when one exists; separately account for policy work ordered before parsing.
9. For database behavior, compare materially different fixture sizes and assert constant statement count.
10. Implement the smallest direct change and update the relevant application context when the public pattern changes.
11. Run focused verification, then the complete application validity gate defined in `.ai/testing.md`.
12. Report behavior proven, files changed, resource cost, consequential decisions, and any production concern not exercised locally.

A task is not complete merely because its happy path runs or static analysis passes. The execution path, bounds, failures, and automated behavior evidence must remain apparent to the next agent.

For an explanation-only request, follow the same evidence path but do not edit the repository. Cite current files and clearly separate installed behavior from an application policy or proposal.
