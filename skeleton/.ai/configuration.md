# Application configuration context

`NOT_APPLICABLE(CONFIGURATION)`

`NOT_APPLICABLE(LOCAL_ENVIRONMENT_LAUNCHER)`

`OUTER_HTTP_FAILURE_DISCLOSURE(CODE_OWNED_GENERIC)`

This file is the starter application's single writable authority for configuration. Other `.ai/` guides link here and retain only concern-specific facts. The health-only skeleton reads no process environment, secret, database credential, migration authority, or application setting. It therefore has no configuration reader, typed configuration value, local environment launcher, selected process profile, local configuration file, or launcher PHP file. Its sole front controller also reads no disclosure mode or runtime profile and remains code-owned `GENERIC`. Do not add artificial configuration code or a speculative launcher.

Record only adopted external input contracts after the early scope gate is resolved. Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption. Unselected worker, migration, administrative, deployment, and production configuration profiles remain explicitly not applicable; do not store task scope or task history here.

When the application introduces an external setting, replace this marker with:

- one application-owned PHP file containing every direct `\getenv('EXACT_LITERAL_KEY')` read;
- exact input names without values, their source owner, required/optional policy, grammar, and byte bounds;
- one separately named factory, final readonly output type, and process identity for each adopted process profile; every migration history is a separate profile even when histories target the same engine;
- explicit composition injection sites for adopted infrastructure, or an explicit deferred marker when configuration-only scope stops before connection composition; each migration history records its own exact input names and never inherits, combines, or falls back to another history's, runtime's, or administrative credentials;
- pre-I/O failure, rotation/restart, and redaction policy; and
- automated parsing, separation, non-disclosure, and child-process parser or adopted-entrypoint evidence.

If the application later adopts `DEVELOPMENT_DETAILS`, record the exact disclosure-mode and runtime-profile input names and their finite vocabularies here. Both inputs become required and must be parsed and validated as one complete typed selection before application-controlled I/O. Missing, empty, unknown, unavailable, malformed, or contradictory input fails configuration, retains the prebuilt generic `500`, and does not continue application execution; staging or production can never select details. Only a recorded and proved isolated local, development, or test profile is eligible. No request header, cookie, query, body, path, caller address, hostname, checkout path, or other request-controlled or inferred fact may toggle disclosure.

Read the installed `vendor/phpthis/framework/docs/configuration.md` before adding this boundary. Do not add a framework configuration service, string-keyed bag, global helper, facade, container binding, automatic dotenv loader, secret-manager abstraction, or hidden reload.

When configuration is adopted, Composer aliases may invoke a recorded entrypoint but remain value-free for every adopted input name. Do not assign or mutate application configuration in `composer.json`; the adopted `KEY=` spelling and its case variants are conservatively invalid even as an inert argument. A direct alias inherits Composer's process environment and proves no selected-only authority. Supply the complete selected profile, excluding unrelated and elevated inputs, at the outer process boundary or through the explicitly adopted application-owned local launcher.

Only after configuration and at least one process profile are adopted may the application replace the launcher marker. Read installed `vendor/phpthis/framework/docs/configuration/local-environment-launcher.md`, then record the application-owned PHP launcher path; shared canonical `ApplicationEnvironment` reader; exact ignored local file; exact profile and key sets; byte-precise declarative grammar and bounds; complete inherited-versus-file precedence without merging or fallback; exact selected-profile child environment without wholesale inheritance; fresh reload; fixed redacted failure; and evidence. `.ai/cli.md` owns any command handoff, `.ai/operations.md` owns explicit PHP CLI invocation and production non-use, and `.ai/testing.md` owns real-launcher tests including array-form shell-free `proc_open` with inherited standard-stream descriptor resources. Do not add a framework launcher, automatic PHP loading, dotenv dependency, configuration cache, or `config:clear` command.
