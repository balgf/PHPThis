<p align="center">
  <img src="https://raw.githubusercontent.com/balgf/PHPThis/v0.1.0-alpha.7/.github/assets/phpthis-readme-banner.png" alt="PHPThis" width="100%">
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
| Latest framework tag | Alpha 8, [`v0.1.0-alpha.8`](https://github.com/balgf/PHPThis/tree/v0.1.0-alpha.8), Consumer Contract version 18, Strict Profile version 4, and diagnostics `PHT001` through `PHT008` |
| Latest proved application starter | Alpha 8 is the matching public framework/skeleton pair with complete clean Packagist-only installation evidence in [Issue #81](https://github.com/balgf/PHPThis/issues/81) |
| Coordinated release evidence | [Issue #81](https://github.com/balgf/PHPThis/issues/81) records Alpha 8 exact candidates, approvals, signed tags, packages, public proof and continuing GitHub prerelease/announcement state; closed [Issue #53](https://github.com/balgf/PHPThis/issues/53) preserves the completed Alpha 7 predecessor |
| Current post-tag `main` | Mutable availability/evidence follow-ups do not change the immutable Alpha 8 tag, artifacts or accepted scope; inspect the requested tag for historical authority |

Alpha 8 package/public-install evidence is tracked in [Issue #81](https://github.com/balgf/PHPThis/issues/81), with [accepted scope](docs/decisions/062-bounded-alpha-8-release-scope.md) and [upgrade notes](docs/releases/0.1.0-alpha.8.md). The tagged notes retain source-preparation facts; the external issue owns later candidate approvals and observed publication results.

Package availability and current release state are external facts: verify the exact [framework](https://packagist.org/packages/phpthis/framework) and [skeleton](https://packagist.org/packages/phpthis/skeleton) versions before installation. The [Alpha 8 release notes](docs/releases/0.1.0-alpha.8.md) describe the compatibility changes and carried-forward limits.

Framework and skeleton Alpha 8 tags preserve the exact approved source. Current `main` documentation is mutable and is not historical artifact authority. The [release process](RELEASING.md) owns publication gates; recorded package proof authorizes no production deployment or later release operation.

## Start a PHPThis application

Consumers install PHPThis through Composer. Do not clone or copy the PHPThis framework repository to start an application.

Create the latest proved public framework/skeleton pair explicitly:

```bash
composer create-project --stability=alpha --prefer-dist phpthis/skeleton my-app '0.1.0-alpha.8'
cd my-app
composer check
php -d error_reporting=-1 -d display_errors=0 -d display_startup_errors=0 -d log_errors=1 -d zend.exception_ignore_args=1 -S 127.0.0.1:8080 -t public
curl -i http://127.0.0.1:8080/health
```

`phpthis/skeleton` becomes the application root and Composer installs `phpthis/framework` under `vendor/phpthis/framework`. The runtime requires PHP 8.4.x, PDO, and `ext-session`.

Do not infer a matching starter release from a framework tag alone. Issue #81 records the exact Alpha 8 skeleton and clean public-install evidence; verify current package-host state before installation. Existing applications must reconcile the [Alpha 7 to Alpha 8 upgrade](docs/releases/0.1.0-alpha.8.md#upgrade-from-alpha-7). The [getting-started guide](docs/getting-started.md) covers installation, existing-application adoption and source evaluation.

## Ask the project AI

Every application owns a thin `AGENTS.md` and task-routed `.ai/` context. Ask the AI working in that application to start with `AGENTS.md`, follow its current task guide, and inspect the relevant application facts, source and tests. Read the full installed Consumer Contract for installation, deliberate adoption, upgrades or conflicts.

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
- [Reviewer walkthrough](docs/reviewer-walkthrough.md) — an optional human companion tracing one request, its evidence, and a diagnostic repair at a pinned source revision.
- [Request handling](docs/request-handling.md) and [database boundaries](docs/database.md) — the core HTTP and PDO patterns.
- [Alpha 8 release notes](docs/releases/0.1.0-alpha.8.md) — compatibility changes and the carried-forward boundary.
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
