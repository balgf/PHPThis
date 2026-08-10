<p align="center">
  <img src="https://raw.githubusercontent.com/balgf/PHPThis/v0.1.0-alpha.6/.github/assets/phpthis-readme-banner.png" alt="PHPThis" width="100%">
</p>

# PHPThis

PHPThis is an experimental PHP 8.4 framework foundation for **AI-first authoring with human accountability**. It stays close to ordinary PHP so an AI can follow the real execution path and a human can review it without first reconstructing hidden framework behavior.

PHPThis favors code that is local, literal, typed, bounded, and mechanically checked. It does not provide AI or LLM APIs; “AI-first” describes the code-authoring and knowledge workflow.

> PHPThis is prerelease evaluation software. APIs may change between prereleases. Do not use it in production.

## Why PHPThis

- Ordinary typed PHP with visible manual composition and zero third-party framework runtime dependencies.
- Explicit finite routes, immutable HTTP values, and one traceable request path.
- Direct engine-specific SQL through a thin PDO transport boundary, with bound data, query budgets, and scale-sensitive tests.
- No ORM, Active Record, lazy loading, query builder, repository layer, service container, facade, autowiring, or runtime discovery.
- A versioned checked PHP subset with permanent, repair-oriented `PHT` diagnostics.
- Installed contracts, task-routed context, source, and tests that an AI can cite and a human can audit.
- Application-owned product policy, configuration, authentication, authorization, caching, jobs, migrations, deployment, and operational evidence.

The framework supplies a small execution foundation and strict verification boundary. It does not turn application decisions into hidden framework services.

## Current release state

| Boundary | Recorded state |
| --- | --- |
| Latest framework tag | Alpha 6, [`v0.1.0-alpha.6`](https://github.com/balgf/PHPThis/tree/v0.1.0-alpha.6), Consumer Contract version 11, Strict Profile version 3, and diagnostics `PHT001` through `PHT007` |
| Current unreleased source | ADR 049, Consumer Contract version 12, Strict Profile version 3, diagnostics `PHT001` through `PHT007`, and 2,618 core lines under the accepted 2,620-line ceiling |
| Last coordinated application starter | Alpha 5 remains the latest framework/skeleton pair with complete clean public-install evidence |
| Alpha 6 completion | The matching skeleton, clean public `create-project` proof, GitHub prereleases, and final announcement remain tracked in [release issue #37](https://github.com/balgf/PHPThis/issues/37) |

Package availability and current release state are external facts: verify the exact [framework](https://packagist.org/packages/phpthis/framework) and [skeleton](https://packagist.org/packages/phpthis/skeleton) versions before installation. The [Alpha 6 release notes](docs/releases/0.1.0-alpha.6.md) describe the framework changes and compatibility boundary.

The Alpha 6 framework tag is immutable. Accepted unreleased `main` after Alpha 6 now includes ADR 049's response-cookie boundary, Consumer Contract version 12, and the 2,618-line core under the accepted 2,620-line ceiling. Those changes are not part of Alpha 6, and their acceptance selects no later release identity or candidate.

## Start a PHPThis application

Consumers install PHPThis through Composer. Do not clone or copy the PHPThis framework repository to start an application.

Until the coordinated Alpha 6 skeleton and public-installation proof are complete, create the last completely proved framework/skeleton pair explicitly:

```bash
composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.5'
cd my-app
composer check
php -S 127.0.0.1:8080 -t public
curl -i http://127.0.0.1:8080/health
```

`phpthis/skeleton` becomes the application root and Composer installs `phpthis/framework` under `vendor/phpthis/framework`. The runtime requires PHP 8.4.x, PDO, and `ext-session`.

Do not infer a matching starter release from the framework tag. Use the Alpha 6 `create-project` path only after the exact skeleton version and clean public-install evidence are recorded; existing applications may assess the framework package independently against the [Alpha 6 upgrade notes](docs/releases/0.1.0-alpha.6.md#upgrade-from-alpha-5). The [getting-started guide](docs/getting-started.md) covers the Composer path, existing-application adoption, and source evaluation.

## Ask the project AI

Every application owns a thin `AGENTS.md` and task-routed `.ai/` context. Ask the AI working in that application to inspect those files, the installed PHPThis contract, and the concrete source and tests before explaining or changing behavior.

Useful requests include:

- `Explain this request path and cite the installed PHPThis contract, application wiring, and nearest tests.`
- `Add a bounded database read using this application's canonical pattern and prove its query count stays constant.`
- `Explain this PHT diagnostic, find the cause in this project, and repair it without weakening the profile.`
- `Does PHPThis support this mechanism? Distinguish installed behavior, application policy, and a proposal.`

The AI may author code and draft decisions. A human still supplies intent, approves consequential choices, and remains accountable for the result.

## Key documentation

- [Vision](VISION.md) — AI-first authoring, human accountability, and framework non-goals.
- [Getting started](docs/getting-started.md) — installation and deliberate adoption.
- [Consumer Contract](docs/consumer-contract.md) — the portable application validity floor.
- [Knowledge map](docs/knowledge-map.md) — the smallest relevant guide, source, and evidence route for each task.
- [Request handling](docs/request-handling.md) and [database boundaries](docs/database.md) — the core HTTP and PDO patterns.
- [Alpha 6 release notes](docs/releases/0.1.0-alpha.6.md) — compatibility changes and the carried-forward boundary.
- [Architecture decisions](docs/decisions/README.md) — accepted rationale and reconsideration triggers.
- [Security policy](SECURITY.md) and [release process](RELEASING.md) — experimental support limits and publication gates.

Installed consumers use the packaged contract and knowledge map. The source repository’s `.ai/` context is maintainer-only and is intentionally excluded from the Composer package.

## Develop or evaluate PHPThis itself

Cloning this repository is for contributing to PHPThis or evaluating its framework source and checked example. It is not the consumer application installation path.

```bash
git clone https://github.com/balgf/PHPThis.git
cd PHPThis
composer install
composer check
```

`composer check` is the complete maintainer gate. PHPStan, PHPUnit, and the Strict Profile are development and verification dependencies; they do not affect the framework runtime or require consumers to select the same test runner. See [CONTRIBUTING.md](CONTRIBUTING.md) and [the guardrail catalogue](docs/guardrails.md) before changing the framework.

## License

PHPThis is open-source software licensed under the [MIT License](LICENSE).
