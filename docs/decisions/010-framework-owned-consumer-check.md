# ADR 010: Framework-owned consumer check and skeleton

Status: accepted

## Context

The first independent consumer successfully installed PHPThis and served `GET /health`, but it had to create its own PHPStan configuration, partial guard runner, bootstrap, route structure, and tests. A disposable adversarial copy passed its complete command after the PHPThis extension was removed and a built-in PHPStan finding was suppressed. Fixed source-root lists also allowed future PHP outside conventional directories to escape analysis.

## Decision

PHPThis supplies one public `phpthis check` binary. It discovers every application-owned PHP file, applies syntax profile checks, and runs PHPStan through a temporary framework-owned maximum-level configuration. Consumer PHPStan configuration, baselines, inline ignores, and symlinked source directories are rejected. The exact discovered manifest drives both syntax and type-aware analysis.

The application owns the behavior-test library, runner, file placement, and organization. Every observable behavior change adds or updates automated tests for expected success, expected failure, boundary validation, and applicable authorization, external side effects, and resource limits. Static analysis, documentation, manual verification, and a no-op test command are not behavior evidence. The canonical `composer check` runs the installed profile stage and then the application's test command. A separately packageable project under `skeleton/` supplies that wiring, one explicit health route, a real front-controller test, and resolved project-owned AI context.

Maintainer verification installs a mirrored framework package into a fresh temporary skeleton, runs the public command, exercises adversarial files outside conventional roots, and checks the exact source-controlled release inventory for both Composer and Git export policies.

## Consequences

An AI cannot accidentally weaken the accepted profile by editing `phpstan.neon`; applications have no PHPStan configuration in contract version 1. Every new PHP location is checked automatically. Static-analysis customization is intentionally unavailable until a narrow additive contract is demonstrated. The skeleton source must be published as a separate Composer project before the documented `composer create-project` path becomes public.

No generic checker can determine whether an arbitrary application-owned suite adequately proves the requested behavior. It can verify only that the canonical gate retains a non-empty test stage. The implementing AI must report the tests changed and the behavior they prove, and the accountable human judges whether the evidence is sufficient.

No checker can protect a repository that removes the checker from CI and refuses to run it. The skeleton CI therefore invokes the installed binary directly as well as owning the application behavior-test stage.

The local archive proof cannot establish the bytes later served by a hosting provider. The alpha publication process therefore remains incomplete until the actual Packagist-preferred dist is installed, compared with the same inventory manifest, and exercised through the public skeleton command.

## Reconsider when

A real application requires a PHPStan extension, stub, or generated-code boundary that cannot be represented without project configuration, or Composer provides a stronger signed policy mechanism for root scripts.
