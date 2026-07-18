# AI coding instructions for this PHPThis application

This file is the universal entrypoint for AI-authored changes in this application.

Before product feature work, replace the skeleton's generic facts in `.ai/project.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md` with verified application facts or explicit not-applicable statements.

## Read order

1. Read `vendor/phpthis/framework/docs/consumer-contract.md`.
2. Read `.ai/README.md`.
3. Read `.ai/rules.md` and `.ai/change-workflow.md`.
4. Read only the task-specific guide selected by `.ai/README.md`.
5. Inspect the concrete source and tests on the execution path.

If the installed contract is missing in a fresh checkout, read `.ai/operations.md` only far enough to install dependencies, then restart this read order. Do not substitute the framework-maintainer `AGENTS.md` or `.ai/` directory for this application context.

## Project gate

Run `composer check` from the application root. A task is not complete until it passes. Focused behavior tests may shorten the repair loop but do not replace the complete check.

## Authority

- The installed PHPThis consumer contract and Strict Profile are the minimum accepted rules.
- This application's `.ai/` guides add project-specific facts and may strengthen those rules.
- Preserve the installed contract when a project instruction conflicts with it, and report the conflict.
- Never add a baseline, suppression, hidden fallback, or second framework pattern to make a change pass.

## Context safety

Do not write credentials, tokens, private keys, customer data, production payloads, or secrets into AI context, source comments, fixtures, logs, or reports. Use documented secret references and redacted examples.
