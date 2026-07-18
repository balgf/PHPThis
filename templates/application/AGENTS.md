# AI coding instructions for {{PROJECT_NAME}}

This file is the universal entrypoint for AI-authored changes in this application. Replace every double-braced template token before feature work begins.

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

If the installed contract or knowledge map is missing in a fresh checkout, read `.ai/operations.md` only far enough to run its dependency-install command, then restart this read order. If either remains unavailable, report the missing dependency instead of inventing or copying framework rules. If Composer uses a non-default vendor directory, update both installed-framework paths above. Do not substitute PHPThis's framework-maintainer `AGENTS.md` or `.ai/` directory for this application context.

## Project gate

Run the complete application validity gate documented in `.ai/testing.md` from the repository root. A task is not complete until that command passes. A focused test may shorten the repair loop but does not replace the complete check.

## Authority

- The installed PHPThis Consumer Contract v3 and Strict Profile v2 are the minimum accepted rules, including explicit cookie/session boundaries and PHT006 finite compile-time-constant SQL.
- This application's `.ai/` guides add project-specific facts and may strengthen those rules.
- If a project instruction conflicts with the consumer contract, preserve the contract and report the conflict.
- Distinguish installed framework behavior, application policy, and new proposals in explanations and implementation reports.
- Never add a baseline, broad ignore, hidden fallback, or second framework pattern to make a change pass.

Before database work, `.ai/data.md` must identify the reviewed SQL-structure mappings and bounded-list choices, runtime database authority and prohibited capabilities, isolated migration or administrative authority, and the source and date that those facts were verified. Do not infer them from successful connectivity.

Before session-backed work, `.ai/architecture.md`, `.ai/operations.md`, and `.ai/testing.md` must record typed service and non-overlapping key ownership, cookie and isolated save-path policy, deployment and transport tests, plus each applicable authentication, authorization, regeneration, expiry, logout, revocation, and CSRF policy. Mark absent concerns explicitly not applicable; session-free applications mark only session transport and session-backed concerns not applicable.

## Context safety

Do not write credentials, tokens, private keys, customer data, production payloads, or secrets into `AGENTS.md`, `.ai/`, source comments, fixtures, logs, or reports. Use documented secret references and redacted examples.
