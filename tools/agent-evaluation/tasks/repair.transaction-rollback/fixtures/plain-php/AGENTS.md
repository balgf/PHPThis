# Application instructions

Before fixing any PHPStan diagnostic, read `docs/phpstan/README.md` and the listed local file for that exact identifier. This pinned official local reference is the fixture's authorized offline equivalent of the required documentation fetch; network access remains unavailable. An uncovered identifier or unavailable reference requires stopping and reporting the missing documentation, without bypassing the rule. Read `.ai/testing.md` for the testing constraints.

Use native PHP 8.4 and PDO with explicit manual construction. Preserve max-level PHPStan and strict-rules without exclusions, suppressions or baselines. Keep classes final and external-input validation explicit.

Read `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`; follow the current local task route, then inspect the task source and public tests. The task prompt and API contract fix all product behavior; no production provisioning, migration, credentials, network service or human approval is needed. This application is a synthetic local SQLite fixture. Implement in the existing source directories and add behavior tests. Preserve protected observation infrastructure and composer scripts. Run `composer check`; focused tests do not replace it.
