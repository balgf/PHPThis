# Optional development Workbench

- Adoption or `NOT_APPLICABLE(WORKBENCH)`: {{WORKBENCH_ADOPTION_OR_NOT_APPLICABLE}}
- Checked project-relative bootstrap path: {{WORKBENCH_BOOTSTRAP_OR_NOT_APPLICABLE}}
- Concrete final workspace type: {{WORKBENCH_WORKSPACE_TYPE_OR_NOT_APPLICABLE}}
- Explicit readonly values and narrowly named operations: {{WORKBENCH_EXPOSED_SURFACE_OR_NOT_APPLICABLE}}
- Development operating-system identity, inherited environment, independently loaded child CLI configuration, ambient filesystem, network, process, and service access, native functions and Composer-autoloaded code, and explicitly composed dependencies: {{WORKBENCH_AUTHORITY_OR_NOT_APPLICABLE}}
- Production, migration, administrative, and unrelated credentials proved absent: {{WORKBENCH_EXCLUDED_AUTHORITY_OR_NOT_APPLICABLE}}
- Execution timeout, CPU, memory, resource, and operating-system termination isolation limitations plus external interruption of a hanging expression: {{WORKBENCH_RESOURCE_LIMITS_OR_NOT_APPLICABLE}}
- Deliberate real side effects, relevant data, integration, and operations context, and distinction from direct deferred-work handlers and production operations: {{WORKBENCH_SIDE_EFFECT_POLICY_OR_NOT_APPLICABLE}}
- Existing adopted business producer transaction and application-recorded finite one-delivery console command when jobs are involved: {{WORKBENCH_JOB_PATH_OR_NOT_APPLICABLE}}
- Retained application behavior tests and complete check evidence: {{WORKBENCH_EVIDENCE_OR_NOT_APPLICABLE}}

Before adoption, read installed `vendor/phpthis/framework/docs/workbench.md`, obtain accountable-human approval, verify that the separate package is available through the approved Composer source, and prove a clean consumer installation. For a real side effect, also read installed `vendor/phpthis/framework/docs/security.md`, `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md`, plus installed `vendor/phpthis/framework/docs/database.md` when data is involved. For job behavior, also read installed `vendor/phpthis/framework/docs/jobs.md`, installed `vendor/phpthis/framework/docs/cli.md`, `.ai/jobs.md`, and `.ai/cli.md`. Install Workbench only through `require-dev`, expose only one concrete application-owned object as `$workspace`, and fix the bootstrap path in one Composer script that disables Composer's process timeout before launching Workbench.

Workbench is arbitrary development code, not a sandbox, redactor, test, debugger, service container, generic dispatcher, operational console, or production path. Its narrow workspace is not containment, and it supplies no execution timeout or resource isolation. It must not receive production, migration, administrative, or unrelated credentials or introduce a Workbench-only publisher. Any retained behavior belongs in ordinary checked application source and automated tests.
