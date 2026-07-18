# Application AI context index

This directory is owned by `{{PROJECT_NAME}}`. It grounds the AI that explains and authors this project; it is not a framework manual. It supplies project facts that the PHPThis consumer contract cannot know. Keep it committed, current, concise, and free of secrets.

Always read:

1. `.ai/rules.md`
2. `.ai/change-workflow.md`
3. `.ai/project.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Explain framework or application behavior | installed PHPThis knowledge map, matching application guide | installed framework source, application execution path, and tests |
| Change application structure or dependencies | `.ai/architecture.md` | composition root and affected source boundary named there |
| Add or change a route | `.ai/architecture.md` | route manifest, relevant route area, and handler named there |
| Read or write application data | `.ai/data.md` | schema source, relevant handler or query object, scale tests |
| Change an external service or side effect | `.ai/integrations.md` | client boundary, contract fixture, failure tests |
| Change runtime, logging, workers, or deployment behavior | `.ai/operations.md` | composition root, deployment configuration, operational tests |
| Add or change tests | `.ai/testing.md` | nearest behavior tests and complete project check |

Accepted architectural decisions and durable rationale live in `docs/decisions/`. AI may draft and update a decision record, but acceptance requires explicit approval from an accountable human. Add a narrowly named area guide when a recurring task needs context that does not fit this map; do not turn this index into a complete project description.
