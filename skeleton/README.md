# PHPThis application skeleton

This is the minimal checked starting point for an application built with PHPThis. It exposes one explicit route, `GET /health`, and contains project-owned AI context, behavior tests, and the complete consumer validity gate.

PHPThis is still pre-alpha. Until tagged packages are published, this skeleton installs `phpthis/framework` from its public source repository at `dev-main`.

The `repositories` entry is only a pre-alpha bootstrap. The separately published skeleton must remove it, require the alpha package constraint from Packagist, and commit the resulting `composer.lock` before release.

## Install and check

```bash
composer install
composer check
```

`composer check` first runs the framework-owned Strict Profile and maximum-level PHPStan configuration, then runs the application's behavior tests.
Commit the generated `composer.lock` with the application so dependency versions remain reproducible.

## Run locally

```bash
php -S 127.0.0.1:8080 -t public
curl -i http://127.0.0.1:8080/health
```

Before adding product behavior, replace this skeleton's generic project facts in `.ai/` with facts verified for the real application.
