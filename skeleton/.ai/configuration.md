# Application configuration context

`NOT_APPLICABLE(CONFIGURATION)`

This file is the starter application's single writable authority for configuration. Other `.ai/` guides link here and retain only concern-specific facts. The health-only skeleton reads no process environment, secret, database credential, migration authority, or application setting. It therefore has no configuration reader or typed configuration value. Do not add artificial configuration code.

When the application introduces an external setting, replace this marker with:

- one application-owned PHP file containing every direct `\getenv('EXACT_LITERAL_KEY')` read;
- exact input names without values, their source owner, required/optional policy, grammar, and byte bounds;
- separately named runtime, worker, migration, and administrative factories and final readonly output types;
- explicit composition injection sites and no credential fallback between authority profiles;
- pre-I/O failure, rotation/restart, and redaction policy; and
- automated parsing, separation, non-disclosure, and real child-process entrypoint evidence.

Read the installed `vendor/phpthis/framework/docs/configuration.md` before adding this boundary. Do not add a framework configuration service, string-keyed bag, global helper, facade, container binding, automatic dotenv loader, secret-manager abstraction, or hidden reload.
