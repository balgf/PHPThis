# ADR 055: Value-free Composer configuration scripts

Status: accepted

## Context

ADR 036 keeps every application process-environment read in one typed PHP configuration boundary and keeps actual deployment values outside committed JSON, source, context, and output. ADR 050 supplies one optional application-owned local launcher when developers need convenient delivery of a complete selected profile.

A Composer alias can nevertheless place assignments directly in `composer.json`, for example by prefixing a long-running command with a database DSN and username. Even when those exact literals are non-secret local placeholders, the manifest now owns deployment topology and identity, overrides the caller's selected values, can mix them with an inherited password or unrelated ambient authority, and establishes a pattern that would disclose a future secret through source, command output, or process inspection. PHT007 cannot detect this contradiction because it intentionally checks PHP process-environment access rather than Composer command text.

On 2026-08-21 in Asia/Manila, the accountable human directed PHPThis to prevent consumers from assigning application configuration in Composer scripts.

## Decision

The installed application checker correlates two already-required sources:

1. `EnvironmentAccessProfile` returns the exact non-empty uppercase literal keys from valid canonical `\getenv('EXACT_LITERAL_KEY')` calls in the complete application PHP manifest.
2. The checker inspects every non-empty string command in every root `composer.json` script, including each member of a flat list-valued script.

A command is invalid when its literal text contains an assignment spelling for one of those application configuration keys or a bounded recognized mutation spelling for that key. The checked forms include case-insensitive `KEY=value` text, Composer `@putenv`, selected literal POSIX `export`, `unset`, and `env -u`/`--unset` forms, selected literal Windows `setx` forms, selected PowerShell environment assignments and environment-provider path or named-target operations, direct `SetEnvironmentVariable`, and literal inline-PHP `putenv` or environment-superglobal mutation. The diagnostic identifies only the non-secret input name. It never repeats the script name, command, or right-hand value.

This is deliberately a fail-conservative lexical check rather than a shell interpreter. Any exact adopted key followed by optional horizontal space and `=` is invalid wherever it appears in Composer command text, even when an author intended that text as a quoted example, test filter, or inert argument. Such samples belong in an application fixture or source file rather than a root Composer command. Matching is case-insensitive so the same manifest cannot bypass the rule under Windows environment semantics.

A Composer alias may invoke a recorded application entrypoint without assigning configuration. For example, a long-running local convenience command may retain the exact `Composer\Config::disableProcessTimeout` callback followed by `@php bin/websocket-server.php`. The caller, service manager, or explicitly adopted application-owned local launcher supplies the complete selected process profile outside Composer command text. An unrelated tool setting such as `XDEBUG_MODE=off` remains outside this correlation rule when application PHP does not adopt that name as configuration.

This is an ordinary Composer/configuration consistency failure, not a new Strict Profile diagnostic. Consumer Contract version 13, Strict Profile version 3, and permanent diagnostics `PHT001` through `PHT007` remain unchanged. Contract version 13 already keeps adopted process configuration and its values at the outer application boundary; this decision makes one contradiction in root Composer command text executable without accepting or rejecting a new PHP language shape. ADR 038 is the direct precedent for an ordinary cross-artifact consistency failure without a Contract or Strict Profile version change.

The check is deterministic and performs no command execution, shell expansion, secret classification, environment read, or external I/O. It adds no framework runtime type, runtime dependency, configuration service, dotenv loader, process runner, supervisor, or Composer plugin.

## Evidence

Direct profile fixtures lock extraction of canonical keys without changing any PHT007 diagnostic. The installed-consumer proof submits string and list assignments, value references, Composer, POSIX, Windows, PowerShell, and inline-PHP mutation spellings, assignment-looking inert argument text, an adversarial script name, and a secret-looking sentinel through the public checker. Every invalid form fails with a value-free diagnostic. The same proof accepts the timeout callback, a value-free application entrypoint, an unrelated tool assignment, and an adopted key mentioned without assignment syntax. The consumer project is restored after every mutation.

## Consequences

Consumers receive an early, actionable failure for the configuration mistake while retaining application-owned process configuration and Composer aliases for value-free entrypoints. Exact application input names remain the correlation boundary, so PHPThis does not pretend to classify arbitrary uppercase tool flags or discover configuration owned only by a dependency.

This checker does not inspect a referenced shell script, PHP entrypoint, Makefile, Composer plugin, dependency callback, or non-string Composer callback shape; interpret shell quoting; exhaust every equivalent POSIX, Windows, or PowerShell spelling; detect every dynamically assembled or escaped input name; prove that a selected profile is complete; classify whether a value is secret; constrain an already-running process; or make installation of an unreviewed root package safe. Unrecognized equivalent mutation syntax remains forbidden by the configuration boundary and requires review rather than becoming an accepted escape hatch. A value-free direct Composer entrypoint still inherits the Composer process environment; the caller must exclude unrelated and elevated inputs, or use ADR 050's adopted launcher to supply an exact selected child environment. In particular, a lifecycle script may run during Composer installation before a later manual `phpthis check`; review of root scripts and an outer `--no-scripts` installation policy remain separate controls where the source is untrusted.

## Reconsider when

Independent consumer evidence shows material false positives from the exact-key correlation, a real configuration assignment bypass within ordinary root script text, or a need for a reviewed Composer-owned process-profile mechanism. Reconsider the smallest deterministic grammar and migration evidence before adding a shell parser, secret scanner, wildcard key policy, Composer plugin, or framework process runner.
