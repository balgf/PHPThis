# AI context index

This directory is the task router for AI context. Do not load every document by default. Framework questions and changes must be answered from this checkout, not model memory.

Always read:

1. `VISION.md`
2. `.ai/rules.md`
3. `.ai/change-workflow.md`
4. `.ai/strict-profile.md`

Then read only what the task needs:

| Task | Read | Inspect |
| --- | --- | --- |
| Explain PHPThis behavior or answer a usage question | `VISION.md`, `docs/knowledge-map.md`, relevant contract or decision | current framework source, tests, and application pattern |
| Add or change a route | `.ai/routing.md`, ADR 017 | `example/src/Routes.php`, `src/Routing/`, `src/Http/Request.php`, route and application tests |
| Add or change a CRUD-shaped resource profile or example | `.ai/crud.md`, `.ai/routing.md`, `.ai/database.md` as applicable | `docs/crud.md`, ADR 013, resource route list, operation directories, and behavior tests |
| Read or write database data or map a structural SQL choice | `.ai/database.md`, `.ai/strict-profile.md` | `src/Database/`, relevant handler or query object, application data authority, selector tests |
| Change PDO transport or database-driver certification | `.ai/database.md`, `.ai/testing.md` | `src/Database/Connection.php`, `tools/test-database-drivers.php`, database CI job |
| Change request or response behavior | `.ai/http.md` | `src/Http/`, `src/Application.php` |
| Change PHP runtime ingestion or the outer boundary | `.ai/request-boundary.md` | `src/Http/RequestReader.php`, `src/Http/RequestBoundary.php`, front controller |
| Add, use, or change cookie-backed session state | `.ai/session.md`, `.ai/http.md`, `.ai/request-boundary.md` | `src/Session/`, `src/Http/ResponseCookie.php`, typed service key ownership, composition root, isolated save path, and transport plus applicable policy tests |
| Propose or adopt HTTP or application data caching | `.ai/cache.md`, `.ai/http.md`, `.ai/testing.md` | `docs/caching.md`, ADR 016, application cache policy, explicit call sites, cold-cache database proof, and applicable cache tests |
| Change the consumer contract, checker, skeleton, or application CRUD-profile context | `.ai/application-context.md`, `.ai/crud.md`, `.ai/static-analysis.md`, `.ai/testing.md` | `docs/consumer-contract.md`, `verification/`, `bin/phpthis`, `skeleton/`, `templates/application/` |
| Prepare, assess, or publish a release | `RELEASING.md`, ADR 018, `.ai/application-context.md`, `.ai/testing.md` | candidate metadata, clean-tree complete gate, exact CI run, package inventory, skeleton repository and lockfile, Packagist-preferred artifacts, and public installation proof |
| Add tests | `.ai/testing.md` | `tests/run.php` |
| Change the development-pattern proof | `.ai/testing.md`, `.ai/database.md` | `tools/test-query-scaling.php`, `tests/fixtures/` |
| Map failures | `.ai/errors.md`, `.ai/request-boundary.md` | named failure, registry wiring, front controller |
| Change types or analysis rules | `.ai/static-analysis.md` | `phpstan.neon`, affected PHP files |
| Parse database, JSON, or other external values | `.ai/types.md` | boundary factory and adversarial tests |
| Add or change a strict-profile rule | `.ai/strict-profile.md`, `.ai/static-analysis.md` | rule implementation, positive/negative fixtures, and installed-consumer proof |

Durable framework knowledge and decision rationale live in `docs/`. The `.ai/` files are compact operational routing contracts. Both remain human-auditable, but neither is a traditional tutorial manual.
