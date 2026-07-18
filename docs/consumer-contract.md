# PHPThis application contract

Contract version: 0

This is the canonical contract for an application built with the installed PHPThis version. It defines the minimum development rules supplied by that version. Application instructions may add stricter rules and project-specific facts, but they must not weaken this contract.

The root `AGENTS.md` and `.ai/` directory in the PHPThis framework repository are maintainer instructions. They are not an application template. A consuming application owns its own `AGENTS.md` and `.ai/` directory.

## Authority and read order

For application work:

1. Read this contract from the installed PHPThis package.
2. Read the application's root `AGENTS.md` and `.ai/README.md`.
3. Read only the application guide relevant to the task.
4. Inspect the concrete source and tests on the execution path.

The PHPThis Strict Profile and executable application checks are the hard floor. Tests demonstrate behavior but do not authorize a contract violation. When application instructions conflict with this contract, preserve the contract and report the conflict.

Files under `vendor/` belong to installed dependencies. Do not edit them to customize application behavior or silence a finding; change application-owned code or propose an upstream framework change.

## Program validity

A PHPThis application must:

- run on PHP 8.4 and declare strict types in every application-owned PHP file;
- run PHPStan at maximum level with every installed strict rule enabled;
- include the PHPThis PHPStan extension and enforce the Strict Profile version selected by the installed package;
- expose one documented project check command that runs static analysis, profile checks, and behavior tests;
- keep every application-owned named class final and expose an interface when an extension point is required;
- use ordinary constructors and a visible composition root instead of runtime discovery or service location;
- keep one canonical spelling and execution pattern for each framework operation;
- fix findings at their cause rather than adding baselines, broad ignores, or comment suppressions.

The framework repository's `composer check` verifies the framework and example application, not an arbitrary consuming repository. Composer does not inherit a dependency's root scripts or development dependencies, and the current repository guardrail scans the framework checkout rather than consumer code. Contract version 0 therefore does not yet ship a complete consumer-project check runner. Until an installable application skeleton supplies that runner, an application must wire equivalent checks itself and must not claim full PHPThis verification without them.

## HTTP and application flow

- One front controller reads PHP runtime globals and passes them to one bounded request boundary.
- Requests and responses are immutable values.
- Routes are explicit method, literal path, and already-constructed handler objects.
- A root route manifest combines named route-area lists; request-time route lookup remains indexed.
- Handlers implement `RequestHandler::handle` and receive dependencies through constructors.
- External `mixed` input is parsed once into a concrete final readonly command or projection before it enters typed application behavior.
- Known public failures use named exception classes and exact-class response registration. Unknown failures remain generic externally and are logged once by exception class without the exception message or sensitive request or database data.

Do not add route discovery, automatic input binding, middleware pipelines, facades, global helpers, macros, dynamic proxies, reflection-based hydration, or magic methods other than constructors.

## Database work

When an application uses a database:

- execute visible SQL through `PHPThis\Database\Connection` with named parameters;
- give every request connection an explicit `QueryBudget` and bounded `QueryTrace`;
- name selected columns and bound every collection read;
- never execute a database statement from a loop or recursive traversal;
- parse selected rows immediately into concrete projections;
- keep transactions explicit and preserve the original failure;
- test materially different fixture sizes and prove that statement count stays constant;
- treat query budgets as backstops, not proof of an efficient SQL shape.

Production-specific table sizes, indexes, locking constraints, retention rules, and query limits belong in the application's `.ai/data.md`, not in the framework contract.

## Project-owned AI context

Every application should commit:

```text
AGENTS.md
.ai/
  README.md
  rules.md
  change-workflow.md
  project.md
  architecture.md
  data.md
  integrations.md
  operations.md
  testing.md
docs/
  decisions/
    README.md
```

The application context records facts the framework cannot infer: domain vocabulary, real source paths, architectural boundaries, data scale, resource limits, external side effects, runtime assumptions, verification commands, and prohibited operations.

Keep the context compact and route tasks through `.ai/README.md`; do not load every guide for every change. Do not store credentials, tokens, private keys, customer data, production payloads, or other secrets in AI instructions. Detailed rationale and decision history belong in the application's `docs/decisions/` directory.

## Contract evolution

Clarifications may update wording without changing the contract version. A change that accepts or rejects a materially different class of application code requires a new contract or Strict Profile version and explicit upgrade notes. Updating PHPThis never grants permission to overwrite an application's project-owned context.
