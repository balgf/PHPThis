# Application change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the route or entrypoint, dependencies, data touched, side effects, and failure paths.
3. Read the smallest relevant application guide and inspect the concrete source and tests.
4. Resolve missing project facts before choosing an implementation; do not guess at scale, authorization, or external contracts.
5. Reuse the application's canonical PHPThis pattern.
6. Add tests for success, expected failure, authorization, and resource bounds where applicable.
7. For database behavior, compare materially different fixture sizes and assert constant statement count.
8. Implement the smallest direct change and update the relevant application context when the public pattern changes.
9. Run focused verification, then the complete application validity gate defined in `.ai/testing.md`.
10. Report behavior proven, files changed, resource cost, and any production concern not exercised locally.

A task is not complete merely because its happy path runs. The execution path, bounds, failures, and evidence must remain apparent to the next agent.
