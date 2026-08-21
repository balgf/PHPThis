# Application configuration context

This file is the application's single writable authority for configuration. Other `.ai/` guides link here and retain only their concern-specific runtime, topology, behavior, or evidence facts. Record names and contracts only. Never place credentials, tokens, private keys, DSNs containing secrets, customer values, or production payloads in this file.

Record only adopted external input contracts. Database setup and migration adoption are decisions owned by the application change workflow, `.ai/data.md`, `.ai/migrations.md`, and accepted decision records; do not store task scope or task history here. Unselected worker, migration, administrative, deployment, and production configuration profiles remain explicitly not applicable.

- Configuration boundary PHP file: `{{CONFIGURATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}`
- HTTP/runtime factory and final readonly output type: `{{RUNTIME_CONFIGURATION_FACTORY_AND_TYPE_OR_NOT_APPLICABLE}}`
- CLI/worker/WebSocket factories and final readonly output types: `{{OTHER_RUNTIME_CONFIGURATION_FACTORIES_AND_TYPES_OR_NOT_APPLICABLE}}`
- Per-migration-history factories, final readonly output types, process identities, and exact input-name ownership; plus separate administrative profiles when adopted: `{{ELEVATED_CONFIGURATION_FACTORIES_TYPES_IDENTITIES_AND_HISTORY_OWNERSHIP_OR_NOT_APPLICABLE}}`
- External input names, source owner, required/optional policy, grammar, and byte bounds: `{{CONFIGURATION_INPUT_NAMES_AND_CONTRACTS_OR_NOT_APPLICABLE}}`
- Runtime, each migration history, and administrative profile, input-name, and credential separation with no inheritance, combined credentials, or fallback: `{{CONFIGURATION_PROFILE_CREDENTIAL_SEPARATION_OR_NOT_APPLICABLE}}`
- Composition injection sites, or deferred connection composition for configuration-only scope: `{{CONFIGURATION_INJECTION_SITES_OR_DEFERRED}}`
- Missing, empty, malformed, oversized, and pre-I/O failure behavior: `{{CONFIGURATION_FAILURE_POLICY_OR_NOT_APPLICABLE}}`
- Rotation, reload, and process-restart ownership: `{{CONFIGURATION_ROTATION_POLICY_OR_NOT_APPLICABLE}}`
- Redaction and non-disclosure evidence: `{{CONFIGURATION_REDACTION_EVIDENCE_OR_NOT_APPLICABLE}}`
- Automated configuration tests and child-process parser or adopted-entrypoint evidence: `{{CONFIGURATION_TESTS_OR_NOT_APPLICABLE}}`
- Local environment launcher adoption or `NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`: {{LOCAL_ENVIRONMENT_LAUNCHER_ADOPTION_OR_NOT_APPLICABLE}}
- PHP launcher path, shared canonical `ApplicationEnvironment` PHP file, exact ignored local file, byte-precise declarative grammar, and file/line/key/value bounds: {{LOCAL_ENVIRONMENT_LAUNCHER_FILE_CONTRACT_OR_NOT_APPLICABLE}}
- Exact process profiles, per-profile key allowlists, complete inherited-versus-file precedence, and no-merge or fallback rule: {{LOCAL_ENVIRONMENT_LAUNCHER_PROFILE_PRECEDENCE_OR_NOT_APPLICABLE}}
- Exact selected-profile child environment, exact additional typed process inputs if any, and unrelated, elevated, or wholesale inherited input exclusion: {{LOCAL_ENVIRONMENT_LAUNCHER_CHILD_ENVIRONMENT_OR_NOT_APPLICABLE}}
- Fresh-per-invocation file reload, fixed redacted failures, and no-cache policy: {{LOCAL_ENVIRONMENT_LAUNCHER_FAILURE_RELOAD_AND_REDACTION_OR_NOT_APPLICABLE}}

When process environment is used, every direct `\getenv('EXACT_LITERAL_KEY')` call belongs in the one recorded PHP file. Entry points call only their matching process factory once during composition. Behavior code receives concrete typed dependencies, never this reader or a string-keyed configuration bag.

Composer aliases may invoke a recorded entrypoint, but their command text remains value-free for every adopted input name. Do not prefix, export, set, clear, or otherwise directly mutate application configuration in `composer.json`; the adopted `KEY=` spelling and its case variants are conservatively invalid even as an inert argument. A direct alias inherits Composer's process environment and proves no selected-only authority. Supply one complete selected profile, excluding unrelated and elevated inputs, at the outer process boundary or through the explicitly adopted application-owned local launcher.

No process needs credentials outside its authority. Each adopted migration history has one separately named factory, final readonly value, process identity, and exact input-name set; its values never inherit, combine with, or fall back to runtime, another history, or administrative values. PHPThis provides no configuration service, helper, facade, container binding, automatic dotenv loader, secret-manager abstraction, or hidden reload.

When a local environment launcher is adopted, read installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md`. This file owns its PHP launcher and shared canonical `ApplicationEnvironment` reader, ignored local file, exact profile and key sets, byte-precise declarative syntax and bounds, atomic source precedence, selected-profile child environment, reload, failure, and redaction. `.ai/cli.md` owns the finite launcher-command/profile/private-child handoff, `.ai/operations.md` owns explicit PHP CLI invocation and production delivery, and `.ai/testing.md` owns real-launcher evidence including array-form shell-free `proc_open` with inherited standard-stream descriptor resources. The launcher remains an application-owned outer development boundary, not a PHP loader, configuration cache, `config:clear` command, or framework/skeleton helper.
