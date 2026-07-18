# Project contract

## Purpose

This starter application exists to provide a verified PHPThis composition root and one checked health endpoint before product-specific behavior is introduced.

Primary users: the humans directing and reviewing the application and the AI authoring it.

Accountable human decision owner: the person or team adapting this skeleton; replace this generic role before product work.

## Non-goals

- Production readiness or deployment policy.
- Database access, external integrations, authentication, or business-domain behavior.

## Canonical vocabulary

| Term | Exact meaning | Do not alias as |
| --- | --- | --- |
| `health endpoint` | The public `GET /health` liveness route. | readiness endpoint |
| `complete check` | The `composer check` application validity gate. | smoke test |

## Critical invariants

- `GET /health` returns status `200`, JSON content type, and the exact body `{"status":"ok"}` followed by one newline.
- The starter route performs no database access, external side effect, or mutable application work.

Replace this generic purpose, vocabulary, and invariants with verified product facts before feature work.
