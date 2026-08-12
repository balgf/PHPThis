# Optional development Workbench

`NOT_APPLICABLE(WORKBENCH)`: the health-only starter does not install or expose PHPThis Workbench.

Before adoption, read installed `vendor/phpthis/framework/docs/workbench.md`, obtain accountable-human approval, verify that the separate `phpthis/workbench` package is available from the approved Composer source, and prove a clean consumer installation. Then replace this marker with:

- the application-owned project-relative bootstrap path and concrete final workspace type;
- every explicit readonly value and narrowly named operation exposed through `$workspace`;
- the dedicated development operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access, native functions and Composer-autoloaded code, and explicitly composed dependencies;
- the production, migration, administrative, and unrelated credentials that are absent;
- the absence of a Workbench execution timeout or CPU, memory, resource, and operating-system termination isolation, including how a hanging expression is externally interrupted;
- any deliberate real side effect, the relevant `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md` facts, and its distinction from direct handler exploration and production operations;
- when jobs are involved, the selected ADR 052 adoption's existing application-owned publication operation and recorded delivery/operational entrypoint and process shape; for ADR 024 specifically, its same-connection business-write/job-insert operation and finite one-delivery command; and
- the application-owned automated tests that retain behavior learned through exploration.

For a real side effect, also read installed `vendor/phpthis/framework/docs/security.md`, `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md`, plus installed `vendor/phpthis/framework/docs/database.md` when data is involved. For job behavior, also read installed `vendor/phpthis/framework/docs/jobs/README.md` and `.ai/jobs.md`, then the deliberately selected checked profile or exact application adoption record under accepted ADR 052. Add installed `vendor/phpthis/framework/docs/cli.md` and `.ai/cli.md` only when the selected operational path uses the application console. Install Workbench only through `require-dev` and use one fixed Composer script that disables Composer's process timeout before launching Workbench. The narrow workspace is not containment against arbitrary PHP. Do not add a container, string-keyed registry, generic dispatcher, automatic booting, discovery, reflection wiring, Workbench-only publisher, HTTP or remote access, batch mode, production path, sandbox claim, or validity claim. Production artifacts install with `--no-dev` and verify that Workbench is absent.
