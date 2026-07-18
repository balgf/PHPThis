# AI context index

This directory is the task router for AI context. Do not load every document by default.

Always read:

1. `VISION.md`
2. `.ai/rules.md`
3. `.ai/change-workflow.md`
4. `.ai/strict-profile.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Add or change a route | `.ai/routing.md` | `example/src/Routes.php`, `src/Routing/` |
| Read or write database data | `.ai/database.md` | `src/Database/`, relevant handler |
| Change request or response behavior | `.ai/http.md` | `src/Http/`, `src/Application.php` |
| Add tests | `.ai/testing.md` | `tests/run.php` |
| Change the development-pattern proof | `.ai/testing.md`, `.ai/database.md` | `tools/test-query-scaling.php`, `tests/fixtures/` |
| Map failures | `.ai/errors.md` | handler and application boundary |
| Change types or analysis rules | `.ai/static-analysis.md` | `phpstan.neon`, affected PHP files |
| Parse database, JSON, or other external values | `.ai/types.md` | boundary factory and adversarial tests |
| Add or change a strict-profile rule | `.ai/strict-profile.md`, `.ai/static-analysis.md` | rule implementation and positive/negative fixtures |

The detailed human-facing rationale lives in `docs/`. The `.ai/` files are compact operational contracts.
