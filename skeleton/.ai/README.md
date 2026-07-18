# Application AI context index

This directory is owned by the consuming application. Replace its generic starter facts with verified project facts before adding product behavior. Keep it committed, current, concise, and free of secrets.

Always read:

1. `.ai/rules.md`
2. `.ai/change-workflow.md`
3. `.ai/project.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Change structure or dependencies | `.ai/architecture.md` | `bootstrap.php` and the affected source boundary |
| Add or change a route | `.ai/architecture.md` | `src/Routes.php`, the route area, handler, and tests |
| Add data access | `.ai/data.md` | the schema authority, query path, and scale tests |
| Add an external side effect | `.ai/integrations.md` | the named client boundary and failure tests |
| Change runtime or logging | `.ai/operations.md` | `public/index.php`, `bootstrap.php`, and operational tests |
| Add or change tests | `.ai/testing.md` | `tests/run.php` and `composer check` |

Accepted architectural decisions live in `docs/decisions/`.
