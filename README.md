# PHPThis

PHPThis is an experimental, checked PHP profile and minimal web framework designed for PHP code authored by AI under human direction. It stays close to ordinary PHP and favors code that is local, literal, typed, bounded, and easy to verify over APIs optimized for typing speed.

PHPThis does not provide AI or LLM APIs. "AI" refers to the code-authoring workflow.

The working rule is simple: if a behavior cannot be found by following ordinary PHP definitions, it does not belong in the framework.

## What makes it different

- No ORM, Active Record, lazy loading, query builder, facades, global helpers, autowiring, route discovery, or runtime macros.
- SQL stays visible and uses named parameters through a thin PDO boundary.
- Every database connection has an explicit query budget that fails before an excessive statement executes.
- Every database connection has a bounded query trace that reports repeated SQL fingerprints, execution timing, and failures without retaining SQL or parameters.
- External database and JSON values are parsed once into concrete final readonly projections and commands before entering typed code.
- A versioned Strict Profile rejects legal-but-unsafe PHP with stable, repair-oriented `PHT` diagnostics.
- Handlers implement one visible `handle` method and receive dependencies through normal constructors.
- Routes are explicit method, path, and already-constructed handler objects, composed from named route-area lists into one visible manifest.
- Markdown is part of the framework interface. The guardrail command requires more Markdown files than PHP files.
- The core is intentionally capped at 550 physical lines under the accepted bounded-query-tracing decision.

Removing an ORM does **not** prove that N+1 queries are impossible. PHPThis combines visible SQL with query budgets and scale-sensitive tests so that query count cannot silently grow with result size.

## Current state

**Status: experimental pre-alpha.** Framework APIs may change without backward compatibility while the development pattern is being proven. Do not use PHPThis in production.

This is a zero third-party runtime-dependency foundation. The first proof slice supports exact-path routing, request/response values, explicit handlers, and instrumented PDO access. Its sample application includes a bounded `GET /users` aggregate read and a transactional `POST /users` write.

The executable query-scaling proof holds the accepted read at one statement as its fixture grows from 2 to 50 users. An isolated N+1 negative control produces the same JSON response body while growing from 3 to 51 statements; `PHT003` rejects that implementation, and a query budget stops it before statement 4.

```text
Request -> Router -> Handler -> Connection -> Response
```

## Try it

PHP 8.4 with PDO, PDO SQLite, and Composer are required for the complete development checks. PHPStan and the PHPThis Strict Profile are mandatory development components and do not affect the framework runtime.

```bash
git clone https://github.com/balgf/PHPThis.git
cd PHPThis
composer install
composer check
composer example:setup
php -S 127.0.0.1:8080 -t example/public
curl -i http://127.0.0.1:8080/health
curl -i http://127.0.0.1:8080/users
curl -i -X POST http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' \
  --data '{"name":"Katherine Johnson","email":"katherine@example.com"}'
```

## Read next

- [Vision](VISION.md) explains the hypothesis and success measures.
- [Architecture](docs/architecture.md) traces the request path.
- [Strict Profile](docs/strict-profile.md) defines the accepted PHP subset and permanent rule catalogue.
- [Evaluation](docs/evaluation.md) describes the executable scaling proof and future AI comparison protocol.
- [Roadmap](ROADMAP.md) describes the maturity plan.
- [Contributing](CONTRIBUTING.md) defines the contribution gate.
- [AI context index](.ai/README.md) routes an AI to task-specific instructions.

## License

PHPThis is open-source software licensed under the [MIT License](LICENSE).
