# PHPThis application skeleton

This is the minimal checked starting point for an application built with PHPThis. It exposes one explicit route, `GET /health`, and contains project-owned AI context, behavior tests, and the complete consumer validity gate.

PHPThis uses AI-first authoring with human accountability. After installation, ask the AI working in this project how the application works or request the next feature. It must begin with `AGENTS.md`, the installed PHPThis contract and knowledge map, this application's `.ai/` context, and the concrete source and tests. PHPThis does not use a traditional framework manual as the primary learning interface.

A useful first request is:

> Read `AGENTS.md`, inspect the installed PHPThis version, explain the current request path with file references, and identify the project facts I must decide before we add the first feature.

PHPThis is still pre-alpha. Until tagged packages are published, this skeleton installs `phpthis/framework` from its public source repository at `dev-main`.

The `repositories` entry is only a pre-alpha bootstrap. The separately published skeleton must remove it, require the alpha package constraint from Packagist, and commit the resulting `composer.lock` before release.

## Install and check

```bash
composer install
composer check
```

`composer check` first runs the framework-owned Strict Profile and maximum-level PHPStan configuration, then runs the application's automated behavior tests. This starter's zero-dependency `tests/run.php` is one concrete implementation, not a required framework, filename, or directory. Every observable behavior change must add or update automated tests; the application remains free to choose its test library, runner, and file placement.
Commit the generated `composer.lock` with the application so dependency versions remain reproducible.

## Run locally

```bash
php -S 127.0.0.1:8080 -t public
curl -i http://127.0.0.1:8080/health
```

Before adding product behavior, replace this skeleton's generic project facts in `.ai/` with facts verified for the real application.

The AI may implement routine, in-scope work under human direction. It must surface consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment. The human accepts those decisions and remains accountable for the result.
