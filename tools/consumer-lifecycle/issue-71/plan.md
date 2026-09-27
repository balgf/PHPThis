# Retained account-directory evaluation — issue 71

Frozen before application implementation. Owner: PHPThis maintainer (balgf), with Codex as implementation assistant. This is a synthetic, maintainer-led evaluation, not a production application, independent adoption study, paid API trial, or longitudinal result.

## Retention and identities

Working consumer: sibling `consumer-lifecycle/account-directory/`; its Git bundle, checkpoint manifest, diffs, and logs will be retained here. Dependencies and generated SQLite fixtures are excluded from Git. Baseline public skeleton `v0.1.0-alpha.7` at `c0e8f5c2a7eb45e24e97dc672bd22dcc13c8684d`, framework at `de07cbd449ed3e905331aeea105e528fbaa83ac3` (Contract 13, Profile 3). Upgrade target: `909977b762ff08f823be1e2c16a4dbb704e4f93a` (Contract 18, Profile 4), public Git source evaluation only. Preserve all application history and the changed application during upgrade; do not regenerate the skeleton. Exact runtime versions and lock hashes belong in the manifest.

## Bounded initial product

An account administrator can create a directory entry and read an account-scoped page. Use the existing protected Create/List pattern, explicit replaceable I/O-free policies, separate actor membership and created-user associations, typed JSON/query/projection boundaries, and SQLite direct constant statements. Checked-in HTTP authentication denies all; tests inject synthetic writer/reader policies without implementing real credential security. Only synthetic records, one local SQLite file, one PHP process per request, and loopback HTTP tests. No production traffic, external provider, framework feature, jobs, sessions, cache, migration history, frontend, Update/Delete, or deployment claim.

Create accepts exact JSON `id`, `name`, `email`; client-selected positive integer IDs are unique, names are 1–80 UTF-8 bytes without ASCII padding/control bytes, and emails are unmodified PHP FILTER_VALIDATE_EMAIL values up to 254 bytes. A conflict returns generic 409. Three explicit transaction writes create user, account relation, and audit event; injected last-write failure must roll back everything. List emits at most 20 ascending IDs, one-row lookahead, optional canonical `after_user_id`, one statement regardless of fixture size. Private no-store applies after policy. Production authorization, credential lifecycle and database privilege separation are absent, not inferred from test policies.

## Frozen behavior-change prompt

On the SAME Alpha 7 dependency and existing application, add optional `search` to the account-scoped user list. Omitted search preserves existing results. A present search is a non-empty UTF-8 string of at most 80 bytes; reject arrays, invalid UTF-8, ASCII controls and padding before protected SQL. Match an exact case-sensitive substring in name OR email (SQLite `instr`), with literal percent/underscore/SQL-looking text preserved as bound data. Keep account isolation, ascending keyset order, 20-row bound, and one-statement query budget. Use distinct named placeholders per occurrence as Alpha 7 already requires. No schema change or package update. Add tests before implementation and retain the first failing test result.

## Frozen acceptance

At baseline, behavior change, and repaired upgrade: complete `composer check`; preserve health and starter summary/emission checks; real front-controller health/denial plus synthetic-policy Create/List tests; permitted/unauthenticated/forbidden/cross-account and unexpected policy-failure outcomes; exact policy order and independently replaceable interfaces; invalid shape 400, unacceptable body value 422, media 415, overflow 413, conflicts 409; denial and invalid-input zero protected statements; rollback; response/summary/trace redaction; identical one-query lists for small and 500-row fixtures; bounded pagination. Search additionally covers name/email, no match, omitted search, literal wildcard/SQL-looking values, filter validation and continuation.

Upgrade in place with an exact locked Git reference, derive applicability from the INSTALLED target guide, retain the first failed compatibility checkpoint, and repair Contract 18's missing outer HTTP failure boundary. Generic-only startup failure must emit a fixed private no-store 500 and one class-only outer event, with native error display disabled and no input-selected details. Preserve ordinary coordinator summaries and independent emission handling; no diagnostic suppression or wholesale context replacement. Record Contract 14–18 applicability, including already-distinct placeholders (15), normal source paths (16), no pending output (17), and absent optional profiles.

## Review and measurement

The user authorized one fresh qualitative review sub-agent with only the retained Alpha 7 consumer and published installed guidance. Give it no maintainer source, implementation history, or solution hints. Record its prompt, evidence, findings, file inventory and any additional help; repair demonstrated gaps. No paid API campaign. Retain actual hashes/diffs/lock changes, source/context size, touched and known-read inventory, observed command durations, repair attempts and interventions. Do not estimate model tokens or claim complete tool telemetry when unavailable.

## Next maintenance trigger

When the next public PHPThis framework AND matching skeleton release are available, the maintainer should reopen this same consumer, preserve its state, and repeat the upgrade table and complete gate against that exact public release. This is a version-triggered plan, not an installed background monitor or a claim that time has passed. Future product changes use a new frozen prompt on this same history.
