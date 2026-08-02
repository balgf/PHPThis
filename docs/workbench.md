# PHPThis Workbench

PHPThis Workbench is an optional development-only expression workspace supplied by the separate `phpthis/workbench` Composer package. It lets a human inspect an explicitly composed application object, try one call, and observe its return value or failure without adding a temporary HTTP route or production command.

Workbench is not framework runtime, an application container, a service locator, a test runner, a debugger, an operational console, or a sandbox. It adds no class to `phpthis/framework`, changes no accepted application syntax, and does not change the check-only role of `vendor/bin/phpthis`.

## Adoption boundary

The accountable human decides whether the local development benefit justifies arbitrary-code authority in the selected environment. Before adoption, record in `.ai/workbench.md`:

- the application-owned bootstrap path and concrete workspace type;
- every value or narrowly named operation intentionally exposed;
- the operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access, native functions and Composer-autoloaded code, and explicitly composed dependencies available to it;
- the production, migration, administrative, and unrelated credentials that must be absent;
- the distinction between direct deferred-work handler exploration, real side effects, and an operational queue-delivery path; and
- the automated tests that retain any behavior learned through exploration.

An application that does not adopt the package records `NOT_APPLICABLE(WORKBENCH)`. The framework checker does not require this optional file from existing consumers and does not inspect Workbench expressions.

Package publication is external state. Before changing dependencies, obtain accountable-human approval for the adoption, verify that `phpthis/workbench` is available from the application's approved Composer source, and prove a clean consumer installation. When deliberately adopted, install it only for development:

```bash
composer require --dev phpthis/workbench
```

Production artifacts use `composer install --no-dev` and verify that neither the package nor `vendor/bin/phpthis-workbench` is present.

The package requires PHP 8.4, `ext-readline`, an interactive terminal, and an available native `proc_open` function. Composer proves the PHP and extension constraints; Workbench fails closed with `WORKBENCH_PROC_OPEN_UNAVAILABLE` when the host disables child-process creation.

## One explicit bootstrap

Create one ordinary application-owned PHP file, such as `tools/workbench.php`. It uses `declare(strict_types=1)`, is covered by the application's normal `composer check`, and returns exactly one concrete application-owned object:

```php
<?php

declare(strict_types=1);

use App\Development\DevelopmentWorkspace;
use App\Routes;

return new DevelopmentWorkspace(
    routes: Routes::create(),
);
```

The workspace is a final named type with explicit readonly values or narrowly named operations:

```php
<?php

declare(strict_types=1);

namespace App\Development;

use PHPThis\Routing\Route;

final readonly class DevelopmentWorkspace
{
    /** @param list<Route> $routes */
    public function __construct(public array $routes)
    {
    }
}
```

Do not turn it into a string-keyed service map, `get()` method, facade, container, credential bag, raw administrative connection, generic queue dispatcher, class-name executor, discovery root, or reflection-based object graph. The bootstrap loads no configuration, database, cache, queue, session, external service, or migration authority unless its visible application code explicitly composes it.

Fix the path in the consuming application's Composer scripts:

```json
{
  "scripts": {
    "workbench": [
      "Composer\\Config::disableProcessTimeout",
      "phpthis-workbench tools/workbench.php"
    ]
  }
}
```

Start it from the application root:

```bash
composer workbench
```

The bootstrap argument is a 1-to-4,096-byte project-relative `.php` path using forward slashes. Empty, dot, parent, control-byte, absolute, root `vendor`, root `.git`, missing, unreadable, escaping, and directly symlinked paths are rejected. Workbench starts only from a Composer project root with a readable installed `vendor/autoload.php`.

## Execution model

Workbench accepts one PHP expression at a time. It exposes only the returned object as `$workspace`, automatically inspects the result with native `var_dump()`, and then discards that expression process.

```text
phpthis> $workspace->routes[0]->path
string(7) "/health"
```

Every expression is sent over standard input to a fresh `PHP_BINARY` child whose generated source begins with `declare(strict_types=1)`. The child freshly loads Composer autoloading and the selected bootstrap. Variables, connections, budgets, traces, leases, mutable objects, and other invocation state do not persist to the next expression. An ordinary parse, compile, or uncaught runtime failure exits only that child, so a later expression starts from a fresh composition. Arbitrary PHP can still signal or terminate other processes and can leave filesystem, database, network, queue, or other external state changed.

Workbench supplies no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation. A non-returning or blocked expression prevents the next prompt until the child is externally interrupted or terminated. Resource exhaustion or operating-system termination can affect the Workbench parent or other processes and must not be described as child-local failure isolation. Use operating-system and service controls outside Workbench where those bounds matter.

Each child loads the PHP CLI configuration available to its own fresh `PHP_BINARY` invocation, with `auto_prepend_file` and `auto_append_file` explicitly cleared. Runtime `ini_set()` changes made in the interactive parent and `-d` options used to launch that parent are not inherited by the child. None of those parent settings is an authority or containment boundary. Verify the actual child CLI configuration and enforce process, operating-system, filesystem, network, database, and service authority independently.

An expression contains 1 through 16,384 bytes and no ASCII control byte or DEL. A trailing semicolon is accepted. Workbench accepts one line rather than a statement or multiline program and records no history.

The tool deliberately has no expression argument, batch mode, HTTP endpoint, remote shell, persistent command history, automatic class loading beyond Composer, result serialization, replay, or production mode. `:help` displays the session contract and `:exit` closes it.

## Jobs and side effects

An application may deliberately expose a concrete deferred-work handler with synthetic development input to inspect that handler's direct behavior. This bypasses durable publication and delivery. If an experiment must publish a real job, expose and invoke only the application's existing adopted business operation whose explicit transaction already owns both the business change and job insert. Do not create a Workbench-only publisher, direct job-table insertion, second transaction path, or alternate enqueue operation.

Workbench supplies no `dispatch()`, queue facade, job base class, class-name selection, run-by-ID, replay, retry, claim, or worker mechanism. Direct deferred-work handler execution does not prove commit-visible publication, envelope parsing, lease behavior, retry transitions, idempotency, or queued delivery. Claiming at most one real queued delivery remains the responsibility of the application's recorded finite tested one-delivery operational command described in [Application CLI and scheduler](cli.md) and [Durable jobs](jobs.md). The accepted example spells that command `jobs:run-one`; applications are not required to copy that spelling.

Production one-off work belongs in that finite application console, where input grammar, identity, output, redaction, idempotency, failure, resource limits, and audit behavior are reviewable and testable.

## Authority and evidence

An entered expression is unchecked arbitrary PHP. Its authority is the combination of the launching operating-system identity, inherited environment, independently loaded child PHP CLI configuration, ambient filesystem, network, process, and service access, native PHP functions and Composer-autoloaded code, and every explicitly composed dependency. Together those can permit reading files and environment values, connecting to services, mutating data, starting processes, and disclosing values. A narrow `$workspace` bounds the intended application surface; it is not containment against arbitrary PHP. Workbench performs no sandboxing, dry run, output bound, redaction, authorization, environment-name safety check, or production detection. A label such as `development` is not an authority boundary.

Run it only under a dedicated development identity whose environment contains no production, migration, administrative, or unrelated secrets. Give explicitly composed infrastructure only the least authority needed for the intended experiment. An ordinary bootstrap exception, invalid bootstrap return, compile error, or uncaught runtime failure writes child diagnostics and exits nonzero; Workbench does not convert it to a default result or retry it.

Workbench output is exploratory evidence, not application validity evidence. Any behavior the application relies on must be written as ordinary checked source, covered by application-owned automated tests, and pass the complete `composer check` gate. Do not paste runtime dumps, credentials, private data, or production payloads into source, tests, `.ai/`, issue trackers, or chat transcripts.

See [ADR 041](decisions/041-optional-development-workbench.md) for the package and ownership decision.
