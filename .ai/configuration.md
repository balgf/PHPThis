# Application configuration contract

Start application-configuration work with `docs/configuration.md` and this current guide. For the optional local launcher, also use `docs/configuration/local-environment-launcher.md`; add `.ai/cli.md`, `.ai/operations.md`, and `.ai/testing.md` only when their boundaries are entered. Read ADR 050 or ADR 055 only when reviewing or changing the decision it records.

## Configuration record

- Require `.ai/configuration.md` in the current skeleton and template. A configuration-free application records the exact standalone marker `NOT_APPLICABLE(CONFIGURATION)`.
- An adopting application owns one environment-reading PHP file containing every exact canonical `\getenv('LITERAL_KEY')` read. Record exact input names without values, adopted process-specific factories and final readonly types, validation bounds, process profile, input-name and credential separation, failure before application-controlled I/O, rotation/restart behavior, redaction, and child-process parser or adopted-entrypoint evidence.
- Give runtime, migration, administrative, worker, and other adopted identities distinct input names and final types without inheritance, combined credentials, or fallback. Record visible injection sites once their process or infrastructure composition is selected; configuration-only scope records connection composition as explicitly deferred.
- Keep deployment-input access and parsing application-owned. Do not add framework configuration runtime, string-keyed configuration bags, global helpers, facades, service containers, discovery, automatic dotenv loading, secret-manager abstractions, hidden reload, or credentials in source or context.
- Route checker or `PHT007` implementation changes through `.ai/static-analysis.md`. This guide owns the authoring policy, not a second structural checker contract.

## Value-free Composer aliases

- Keep every application Composer alias value-free for adopted configuration. Never hardcode an adopted configuration value, credential, token, DSN, endpoint secret, or other deployment input in Composer command text.
- ADR 055's ordinary checker correlates exact canonical `\getenv` keys case-insensitively with fail-conservative assignment and bounded recognized mutation spellings, emits only the input name, and adds no `PHT` diagnostic. The adopted `KEY=` spelling and its case variants are rejected even when intended as inert argument or sample text.
- Value-free entrypoint aliases and unrelated tooling inputs remain valid. Inherited ambient authority, referenced scripts, plugins, dynamic or escaped names, profile completeness, and pre-check lifecycle execution remain explicit review limits.

## Optional local environment launcher

- Keep the recommended launcher application-owned and optional. A configuration-free starter records `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)` and gains no launcher file.
- After configuration and process profiles are selected, `.ai/configuration.md` owns the PHP launcher, shared canonical `ApplicationEnvironment` reader, exact ignored file, finite profile and key sets, byte-precise declarative grammar, atomic inherited-versus-file precedence, exact selected child environment, fresh reload, failure, and redaction.
- `.ai/cli.md` owns the finite launcher-command to selected-profile, exact private child, and unchanged application-command map. `.ai/operations.md` owns absolute project-root and `PHP_BINARY` resolution, explicit PHP CLI invocation, and production non-use. `.ai/testing.md` owns real-launcher subprocess and array-form shell-free `proc_open` evidence with inherited standard-stream descriptors.
- Preserve ADR 050's optional application-owned boundary. Do not add a framework or skeleton launcher, automatic PHP loading, dotenv dependency, configuration cache, `config:clear`, launcher-specific Contract/Profile/`PHT` change, inherited-wholesale child environment, secret argument, launcher-side stream interception, or second console path.

## Verification

Prove exact accepted and rejected input names and representations, bounds, profile separation, fail-before-I/O behavior, credential non-disclosure, and fresh process composition. For Composer aliases, retain the checker's positive value-free controls and negative mutation spellings without exposing values. A launcher adoption additionally proves its complete configuration, CLI, operations, and real-subprocess boundaries before it is described as usable.
