# PHPThis knowledge map

This is the installed framework knowledge router for an AI working in a PHPThis application. It is not a tutorial manual. Start with `docs/consumer-contract.md`, combine it with the application's own `AGENTS.md` and `.ai/` context, and load only the smallest relevant row below.

Framework contracts define what is supported. Human-approved application decisions and context define desired local policy and may strengthen the framework contract, but cannot weaken it. Concrete source and tests establish what the installed version and application actually do.

## Question and task routing

| Question or task | Read from the installed framework | Inspect in the installed framework and application |
| --- | --- | --- |
| Explain PHPThis's purpose or whether a mechanism belongs | `VISION.md`, relevant record under `docs/decisions/` | current application pattern and complete check |
| Start or validate an application | `docs/getting-started.md`, `docs/consumer-contract.md`, `docs/guardrails.md` | application `composer.json`, `AGENTS.md`, `.ai/`, tests, and `bin/phpthis` |
| Add or explain a route or handler | `docs/architecture.md`, `docs/request-handling.md` | `src/Routing/`, `src/Http/RequestHandler.php`, application route manifest, handler, and tests |
| Add or explain a CRUD-shaped feature | `docs/crud.md`, `docs/decisions/013-optional-crud-reference-profile.md`, `docs/database.md` | application `.ai/architecture.md`, `.ai/data.md`, selected feature structure, route list, operation handlers, boundary values, and scale-sensitive tests |
| Read runtime request data or return a response | `docs/request-handling.md`, `docs/errors.md`, `docs/logging.md` | `src/Http/`, application front controller, composition root, and boundary tests |
| Add, explain, or secure cookie-backed session state | `docs/sessions.md`, `docs/security.md`, `docs/decisions/015-explicit-native-session-lifecycle.md` | `src/Session/`, `src/Http/ResponseCookie.php`, application `.ai/architecture.md`, `.ai/operations.md`, typed key ownership, composition root, isolated save path, mandatory transport evidence, and each applicable security-policy test |
| Connect to, read, write, or assess SQL safety | `docs/database.md`, `docs/security.md`, `docs/decisions/012-pdo-transport-application-owned-dialects.md`, `docs/decisions/014-sql-data-and-finite-structure.md`, `docs/performance.md`, `docs/logging.md` | `src/Database/`, PHT006 implementation and fixtures, application `.ai/data.md`, composition root, finite engine-specific SQL paths, runtime authority, projections, adversarial integration tests, and scale tests |
| Parse JSON, database rows, or other external values | `docs/type-safety.md`, `docs/static-analysis.md` | named command or projection factory and adversarial tests |
| Explain or repair a `PHT` diagnostic | `docs/strict-profile.md`, `docs/static-analysis.md` | named rule implementation, failing file, and nearest passing framework pattern |
| Change project-specific architecture, data, integrations, or operations | `docs/consumer-contract.md`, `docs/decisions/009-project-owned-ai-context.md` | the matching application `.ai/` guide, accepted decisions, concrete source, and tests |
| Ask for authentication, middleware, migrations, caching, queues, or another capability without a supported pattern | `ROADMAP.md`, `VISION.md`, relevant decision records | verify that no canonical implementation exists; session storage does not imply authentication or authorization; state the unsupported boundary clearly before proposing an application decision |

## Answer protocol

When answering how PHPThis works or how code should be written:

1. Identify the installed PHPThis version or exact dependency revision when available.
2. Inspect the consumer contract, the relevant application guide, and the concrete source and tests. Do not treat model memory as framework authority.
3. Distinguish current framework behavior, application-owned policy, and a new proposal. Never present a proposal as an existing feature.
4. Name the files, symbols, diagnostics, or checks that support the answer so a human can audit it.
5. State when PHPThis deliberately has no canonical mechanism instead of borrowing a pattern from another framework.
6. If implementation was requested, run the complete application gate and report the evidence. If only an explanation was requested, do not change the repository.

Do not invent missing product requirements, schema meaning, authorization policy, production limits, or external-service behavior. Surface those decisions to the accountable human.
