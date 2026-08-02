# ADR 041: Optional development Workbench

Status: accepted

## Context

Humans sometimes need to inspect a composed PHP value, call one concrete application operation, or exercise one job handler without adding a temporary HTTP route or retaining a production command. Native interactive PHP does not automatically inspect expression results, and session-level setup does not provide a reliable strict caller context for later entered statements. A framework-owned shell backed by a service container, discovery, dynamic dispatch, or broad application globals would conflict with PHPThis's explicit composition and one-canonical-pattern boundaries.

The core also has no room or demonstrated need for an interactive runtime primitive. `vendor/bin/phpthis` is the installed check boundary, while production one-off operations belong to the application's finite tested operational console.

## Decision

PHPThis accepts **PHPThis Workbench** as an optional separate `phpthis/workbench` development package. After approved adoption, verified availability from the application's approved Composer source, and clean consumer-install evidence, a consumer installs it only through `require-dev`, records its adoption in application context, and owns one checked project-relative bootstrap that returns exactly one concrete object. The package exposes that object as `$workspace`; it performs no discovery, automatic application booting, reflection wiring, or container lookup. The fixed Composer script disables Composer's process timeout before launching the interactive binary; this does not impose an expression execution bound.

The Workbench parent is an interactive terminal process. For each entered expression it starts a fresh `PHP_BINARY` child, sends generated PHP source through standard input, begins that source with `declare(strict_types=1)`, loads Composer autoloading, requires the selected bootstrap, evaluates the expression in the lexical `$workspace` scope, inspects the result with native `var_dump()`, and exits the child. The parent does not accept expressions from command arguments or noninteractive input and does not persist variables, object state, or command history. Each child loads its own PHP CLI configuration with `auto_prepend_file` and `auto_append_file` explicitly cleared; parent-process `ini_set()` changes and parent-launch `-d` options do not carry into it.

The generated child program is not a security boundary. The expression is arbitrary PHP whose authority combines the launching operating-system identity, inherited environment, independently loaded child PHP CLI configuration, ambient filesystem, network, process, and service access, native PHP functions and Composer-autoloaded code, and every explicitly composed dependency. The narrow workspace limits only the intended application surface; it does not contain arbitrary PHP. An ordinary compile or uncaught runtime failure is confined to that child process, but an expression can target the parent or other processes and can leave external state changed. Workbench provides no execution timeout, CPU limit, memory limit, resource limit, or operating-system termination isolation; a blocked or non-returning expression prevents the next prompt until externally interrupted, and exhaustion or termination can affect other processes. Workbench claims no sandbox, redaction, dry run, output limit, authorization, production detection, or validity evidence. Parent CLI flags and runtime INI changes are not inherited containment controls. Consumers use a dedicated least-authority development process with no production, migration, administrative, or unrelated credentials.

The application workspace is a final named concrete type with explicit readonly values or narrowly named operations. It is not a string-keyed registry and must not expose a generic `get`, dispatcher, queue, class resolver, raw administrative connection, credential bag, facade, or service container. Visible application code decides whether configuration or infrastructure is composed.

An application may expose a concrete deferred-work handler and synthetic input for direct exploration. If it must publish a real job, it invokes only the existing adopted business operation whose explicit transaction already owns the business change and job insert; it does not add a Workbench-only publisher, direct insert, second transaction path, or alternate enqueue operation. Workbench supplies no generic dispatch, job discovery, run-by-class, run-by-ID, replay, retry, claim, or worker path. Direct deferred-work handler execution does not prove publication or queued delivery. The finite application operational console retains production one-offs and the application's recorded one-delivery command; the accepted example spells that command `jobs:run-one` without reserving that spelling for every consumer.

Any retained behavior moves into ordinary application source and automated tests and passes the complete application gate. Interactive output alone is not behavior evidence.

This decision adds no framework-core PHP, runtime dependency, command, checker rule, `PHT` diagnostic, accepted-syntax change, application directory enforcement, Consumer Contract version, Strict Profile version, or core-line increase. `vendor/bin/phpthis` remains check-only. Approved adoption, availability from the selected Composer source, clean installation, and production-artifact exclusion require their own external evidence.

## Consequences

Humans gain a small original inspection tool aligned with explicit composition and strict PHP. A fresh child per expression prevents stale invocation state from silently carrying across experiments and isolates ordinary compile and uncaught runtime failures, at the cost of no persistent local variables or object mutations. It does not contain deliberate process control, undo external side effects, bound execution resources, keep a hanging expression from blocking the prompt, or guarantee that resource exhaustion and operating-system termination remain child-local.

Consumers must deliberately design a narrow development workspace and test any learned behavior. The package cannot make arbitrary code safe, prevent an overpowered bootstrap, redact output, or replace a debugger, test suite, finite operational console, queue proof, or production runbook.

The separate package keeps interactive code and its `ext-readline` development requirement outside `phpthis/framework` runtime and outside `--no-dev` production installations.

## Reconsider when

Independent consumers demonstrate that fresh-process expressions cannot support a concrete high-value inspection workflow without an additional shared primitive, or package evidence reveals a correctness or authority-boundary defect. Reconsider only the smallest proven development-only change. Familiarity with another tool, desire for a container, generic dispatch convenience, persistent mutable state, or production execution is not sufficient.
