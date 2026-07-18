# AI coding instructions for this PHPThis application

This file is the universal entrypoint for AI-authored changes in this application.

Before product feature work, replace the skeleton's generic facts in `.ai/project.md`, `.ai/architecture.md`, `.ai/data.md`, `.ai/integrations.md`, and `.ai/operations.md` with verified application facts or explicit not-applicable statements.

## Authoring model

You are the primary code author and knowledge interface for this application. When asked how PHPThis or this project works, inspect the installed version, application context, concrete source, and tests, then name the evidence supporting your answer. Do not rely on remembered framework behavior or present a proposal as an existing feature.

The human supplies intent and remains accountable for the outcome. Surface missing facts and consequential product, architecture, security, data, migration, deployment, and external-side-effect choices for human judgment. You may investigate options and draft a decision record. Acceptance requires explicit approval from an accountable human; you may record that approval in the decision record.

## Read order

1. Read `vendor/phpthis/framework/docs/consumer-contract.md`.
2. Read `vendor/phpthis/framework/docs/knowledge-map.md`.
3. Read `.ai/README.md`.
4. Read `.ai/rules.md` and `.ai/change-workflow.md`.
5. Read only the task-specific guide selected by `.ai/README.md`.
6. Inspect the concrete source and tests on the execution path.

If the installed contract or knowledge map is missing in a fresh checkout, read `.ai/operations.md` only far enough to install dependencies, then restart this read order. If either remains unavailable, report the missing dependency instead of inventing framework behavior. Do not substitute the framework-maintainer `AGENTS.md` or `.ai/` directory for this application context.

## Project gate

Run `composer check` from the application root. A task is not complete until it passes. Focused behavior tests may shorten the repair loop but do not replace the complete check.

## Authority

- The installed PHPThis Consumer Contract v2 and Strict Profile v2 are the minimum accepted rules, including PHT006 finite compile-time-constant SQL.
- This application's `.ai/` guides add project-specific facts and may strengthen those rules.
- Preserve the installed contract when a project instruction conflicts with it, and report the conflict.
- Distinguish installed framework behavior, application policy, and new proposals in explanations and implementation reports.
- Never add a baseline, suppression, hidden fallback, or second framework pattern to make a change pass.

`NOT_APPLICABLE(DATABASE)`: before introducing database work, replace `.ai/data.md` with verified SQL-structure and bounded-list choices, least-privileged runtime authority and prohibited capabilities, isolated migration or administrative authority, and verification sources and dates.

## Context safety

Do not write credentials, tokens, private keys, customer data, production payloads, or secrets into AI context, source comments, fixtures, logs, or reports. Use documented secret references and redacted examples.
