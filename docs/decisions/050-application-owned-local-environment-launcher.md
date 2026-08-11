# ADR 050: Application-owned local environment launcher

Status: accepted

## Context

ADR 036 keeps deployment configuration application-owned, confines direct process-environment reads to one PHP file, and requires process-specific final readonly values without credential inheritance or fallback. It deliberately supplies no dotenv loader or configuration runtime. Its local-development guidance permits an application to select an outer-boundary loader, but it does not yet define one safe, copyable pattern.

That omission creates predictable friction for local operational commands. PHP receives only values already present in its process environment, while an ignored `.env` file is inert. Manually exporting each value is inconvenient; shell-sourcing the file executes configuration as code and can misinterpret ordinary opaque values such as an unquoted DSN containing semicolons. A broad automatic loader can also expose runtime, worker, migration, and administrative credentials to the same process or create a configuration bag, fallback layer, or false expectation of a stale configuration cache.

Issue 48 adds an application-owned local launcher that makes the safe path explicit without changing the accepted framework boundary.

On 2026-08-12 in Asia/Manila, the accountable human approved this optional application pattern, its checked copyable reference, and its bounded installed-consumer evidence. This approval changes no release identity and authorizes no tag, package-host, GitHub-release, or announcement operation.

## Decision

An application that has already adopted process configuration and explicit process profiles may adopt one development-only PHP launcher at a recorded application path such as `bin/application`. The checked reference is invoked explicitly through PHP CLI as `php ./bin/application jobs:run-one` or `php ./bin/application database:migrate`. It remains application-owned. It is neither a PHPThis command nor an application operation, production entrypoint, configuration service, secret store, or automatic PHP bootstrap loader.

The checked reference accepts exactly these two commands and mappings:

| Launcher command | Selected profile | Selected application keys |
| --- | --- | --- |
| `jobs:run-one` | worker | `APP_WORKER_DATABASE_DSN`, `APP_WORKER_DATABASE_USERNAME`, `APP_WORKER_DATABASE_PASSWORD` |
| `database:migrate` | migration | `APP_MIGRATION_DATABASE_DSN`, `APP_MIGRATION_DATABASE_USERNAME`, `APP_MIGRATION_DATABASE_PASSWORD` |

Those command names and keys belong to the reference application; they are not reserved framework APIs. A consumer records its own finite command, child-entrypoint, profile, and exact-key map. The launcher selects that map before reading local configuration, resolves the absolute project root from `__DIR__`, uses absolute `PHP_BINARY`, and resolves the private absolute `bin/console.php` child before local-file access. Unknown commands and invalid arguments fail before that access.

The reference local file is exactly `.env` at the resolved project root. It is ignored, must be a readable regular non-symlink file, and is parsed by application-owned PHP only as declarative bytes. The complete accepted grammar is:

- the file is at most 65,536 bytes;
- it contains at most 256 physical lines separated by LF or CRLF, with an optional final newline and no bare CR;
- every physical line is at most 4,225 bytes, excluding its LF or CRLF separator;
- an empty physical line or a visible-ASCII comment line whose first byte is `#` is allowed and counts toward the 256-line bound; leading-space comments and inline comments are unsupported;
- every other physical line is one assignment; `export`, quoting, escaping, continuation, and every other form are unsupported;
- the first `=` separates the key from the value;
- the key is 1 through 128 bytes and matches `[A-Z][A-Z0-9_]*`;
- the key must be one of the six exact reference keys above, and each present key occurs exactly once;
- the value is 1 through 4,096 visible ASCII bytes from `0x20` through `0x7E`; control bytes and DEL are rejected; and
- every byte after the first `=` is preserved opaquely, so semicolons, dollar signs, backticks, angle brackets, quotes, and other shell-significant bytes are data and are never interpreted.

The launcher never includes `.env` as PHP, evaluates, interpolates, expands, redirects from, invokes a shell for, or executes the file. No key or value selects a command, executable, PHP source, class, callback, path, environment-variable name, or profile. Duplicate keys, unknown keys, malformed lines, unsupported syntax, missing or empty selected values, a non-regular or symlink file, and every exceeded bound fail closed.

Profile precedence is atomic:

1. A complete inherited selected triplet wins and the launcher does not read `.env` only when each value satisfies the same 1-through-4,096-byte visible-ASCII transport bound with no controls or DEL.
2. If none of the selected keys is inherited, the launcher reads `.env` and requires one complete selected triplet.
3. A partially inherited selected profile, an empty inherited selected value, or a partial or empty selected file profile is invalid.
4. A non-selected file profile may be absent or complete; a represented partial non-selected triplet is invalid and is never passed to the child.
5. The launcher never fills a missing inherited value from the file and never combines profiles or sources.
6. The array-form `proc_open` call supplies exactly the selected application triplet as its explicit child environment. It deliberately passes no unrelated, opposite-profile, or elevated application input and does not request parent-environment inheritance. If the child needs another process input, the application adds another exact typed selected input rather than inheriting the parent environment wholesale. Worker, migration, runtime, bootstrap, administrative, and other profiles never fall back to or inherit from one another.

The reference launcher uses this fixed redacted outer contract:

| Condition | Exit | stdout | stderr |
| --- | ---: | --- | --- |
| unknown command | `2` | empty | `{"error":"unknown_command"}\n` |
| invalid arguments | `2` | empty | `{"error":"invalid_arguments"}\n` |
| local configuration, profile, file, or launch preparation failure | `1` | empty | `{"error":"local_environment_failed"}\n` |

After successful preparation, the launcher calls `proc_open` with an array command containing absolute `PHP_BINARY`, the absolute private child path, and the code-owned selected command; it uses explicit inherited standard-stream descriptor resources (`0 => STDIN`, `1 => STDOUT`, `2 => STDERR`), the absolute project root as the working directory, the exact selected environment, and `bypass_shell`. The child console retains its own exact exit and stream contract without launcher-side stream interception or buffering. The launcher does not print, log, retain, or place configuration values in arguments. Real values remain outside source, `.env.example`, AI context, fixtures, snapshots, logs, traces, exception messages, and retained command output.

Every file-backed invocation reparses the ignored file. There is no configuration cache and no `config:clear` analogue. Production receives configuration from its explicitly selected supervisor, container, service manager, or other deployment-owned delivery path and does not invoke this launcher or read its local file.

PHP composition remains unchanged. The reference launcher and private child share `src/Configuration/ApplicationEnvironment.php` and its `ApplicationEnvironment` class; that file alone owns every direct `\getenv('EXACT_LITERAL_KEY')` read required by ADR 036. The copyable reference provides only the selected launcher transport values and proves only their atomic source precedence, byte bounds, and exact child delivery. It deliberately does not define or claim generic DSN, username, password, or credential semantic validation. Before adopting the pattern for a real command, the application MUST add and use that command's specifically named child factory in the same canonical reader file. That factory reads only the process's exact selected input names, applies the application's real semantic grammar and bounds, produces the real process-specific final readonly configuration before application-controlled I/O, and supplies it through visible manual composition. Without that application-specific factory and evidence, the checked block remains transport proof rather than adopted application configuration. The decision adds no framework source, runtime dependency, automatic HTTP or CLI loading, dotenv package, secret-manager adapter, configuration bag, facade, provider, container binding, fallback, cache, helper, Contract requirement, Strict Profile rule, `PHT` diagnostic, or checker rule.

## Evidence

The real PHP launcher runs in fresh subprocesses and proves its exact command/profile/child map; the exact `php ./bin/application <command>` forms plus invocation by absolute launcher path from outside the project working directory; absolute root, `PHP_BINARY`, and child resolution before file access; shared canonical `ApplicationEnvironment` ownership; byte-precise parsing; complete inherited-profile precedence and file bypass; absent inherited profile with complete file delivery; partial and empty inherited rejection; partial and empty file rejection; duplicate, unknown, malformed, unsupported, control-bearing, line-ending, and every maximum-plus-one rejection; opaque semicolon and shell-metacharacter preservation; exact selected child environment; unrelated and elevated profile exclusion; array-form shell-free `proc_open` with inherited `STDIN`, `STDOUT`, and `STDERR` descriptor resources; fixed exit and stream bytes; complete redaction including command arguments; and cleanup of every synthetic ignored file and side-effect sentinel.

Negative controls include shell commands, `exit`, command substitution, backticks, variable expansion, redirection, and a `PATH` assignment. They prove that local bytes neither execute nor alter executable selection, files, processes, or output. The installed proof certifies only the packaged guidance, application-context routes, and checked reference. It does not certify an arbitrary consumer launcher, local file permissions beyond the tested shape, production secret delivery, a deployment supervisor, container, service manager, rotation process, or legal and organizational access policy.

## Consequences

Consumers gain one convenient and reviewable local command without making PHP configuration implicit. The finite launcher map and atomic profile precedence prevent a local worker from inheriting migration or administrative configuration. The strict declarative grammar permits only empty lines and first-byte-`#` comments while providing no leading-space or inline-comment, quoting, escaping, interpolation, or other dotenv semantics; those bytes remain ordinary opaque value data, which keeps the execution boundary inspectable.

The launcher must be adapted deliberately for each application's real commands, key names, process profiles, validation, and deployment boundary. Copying the reference without replacing its application-specific map is not adoption evidence. The health-only skeleton retains `NOT_APPLICABLE(CONFIGURATION)`, `NOT_APPLICABLE(CLI)`, and `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)` and ships no launcher file.

## Reconsider when

Independent application evidence shows that the exact declarative grammar cannot carry a required process value, that atomic selected-profile precedence cannot support a necessary local workflow, or that a supported PHP runtime cannot preserve the recorded root, `PHP_BINARY`, array-form `proc_open`, exact environment, and stream behavior. Reconsider the smallest application-owned bound or handoff with adversarial evidence. Familiar dotenv syntax, framework parity, a desire to execute configuration as code, or a request for a generic configuration cache is not sufficient.
