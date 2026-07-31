# Application configuration context

`NOT_APPLICABLE(CONFIGURATION)`

This file is the starter application's single writable authority for configuration. Other `.ai/` guides link here and retain only concern-specific facts. The health-only skeleton reads no process environment, secret, database credential, migration authority, or application setting. It therefore has no configuration reader or typed configuration value. Do not add artificial configuration code.

Record only adopted external input contracts after the early scope gate is resolved. Database-engine selection does not authorize a connection attempt, server provisioning, or migration adoption. Unselected worker, migration, administrative, deployment, and production configuration profiles remain explicitly not applicable; do not store task scope or task history here.

When the application introduces an external setting, replace this marker with:

- one application-owned PHP file containing every direct `\getenv('EXACT_LITERAL_KEY')` read;
- exact input names without values, their source owner, required/optional policy, grammar, and byte bounds;
- one separately named factory and final readonly output type for each adopted process profile;
- explicit composition injection sites for adopted infrastructure, or an explicit deferred marker when configuration-only scope stops before connection composition; when an elevated profile is adopted, record no credential fallback between authority profiles;
- pre-I/O failure, rotation/restart, and redaction policy; and
- automated parsing, separation, non-disclosure, and child-process parser or adopted-entrypoint evidence.

Read the installed `vendor/phpthis/framework/docs/configuration.md` before adding this boundary. Do not add a framework configuration service, string-keyed bag, global helper, facade, container binding, automatic dotenv loader, secret-manager abstraction, or hidden reload.
