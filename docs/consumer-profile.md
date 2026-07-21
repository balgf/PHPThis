# Alpha 2 consumer profile

This is the final sanitized integration proof for the Alpha 2 capability evaluation. It is an evidence map for an AI and a human reviewer, not a tutorial and not a claim that PHPThis owns a complete application stack.

## One composed request

The checked-in example registers `POST /accounts/{account_id:positive-int}/users`. The successful path is deliberately explicit:

1. the router supplies one typed positive account identifier;
2. `CreateUserHandler` wraps it in `AccountId`;
3. replaceable account policies authenticate, resolve the tenant, and authorize Create in that order;
4. `CreateUserCommand` admits one exact bounded JSON representation;
5. `TransactionalCreateUser` owns one SQLite connection, a four-statement budget, four complete raw SQL statements, and one transaction;
6. the transaction writes the user, explicit `account_users` relation, event, and bounded versioned durable-job envelope;
7. commit makes all four effects visible together to another connection; and
8. the terminal coordinator owns one `X-Request-ID` and attempts one redacted closed summary.

The job row is transactionally published with the business data. It is not an in-memory event, response callback, automatic observer, or hidden enqueue after commit.

`tests/consumer-profile.php` compares empty and 500-row fixtures and requires four statements in both. Authentication, cross-tenant, authorization, and invalid-input failures stop before protected SQL. Forced final-job and budget failures must return only the generic unknown-failure response and leave no new user, account-user relation, event, or job. Direct operation probes reject a requested/resolved account mismatch and an actor without current `account_memberships` authority. All post-policy mapped and unknown responses are `private, no-store`. Summary evidence may contain finite counts, timings, outcomes, source labels, and query fingerprints; it must not contain the submitted credential, name, email, SQL, bindings, DSN, or database failure text.

The checked-in HTTP composition remains deny-all. Test-owned policy replacements permit only the sanitized profile. A consumer supplies real credential verification, tenant resolution, and action authorization through manual constructor wiring and retains equivalent success, denial, rollback, scaling, and redaction evidence.

The example chooses to associate a successfully created user with the requested account. The separate `account_users(user_id, account_id)` table owns that domain relationship; `account_memberships(principal_id, account_id)` owns actor access, and numeric equality never links those identities. Forward migration 0007 creates `account_users` without an inferred backfill. That visible rule makes the mutation genuinely tenant-scoped; it is not a reusable account model, identity provider, permission system, service container, middleware pipeline, repository, ORM, binding helper, or application generator.

## Supporting capability proofs

| Boundary | Accepted proof |
| --- | --- |
| Typed routing | ADR 019 and routing tests in `tests/run.php` |
| Request policy | ADR 020 and `tests/request-policy.php` |
| Typed external input | ADR 021 and the Create tests in `tests/run.php` |
| Finite SQL and scaling | ADR 022, `tests/request-policy.php`, and `tools/test-query-scaling.php` |
| Correlation and redacted summaries | ADR 023 and `tests/observability.php` |
| Durable work | ADR 024 and `tests/jobs.php` |
| CLI and scheduled passes | ADR 025 and `tests/cli.php` |
| Uploads, local storage proof, and bounded file response | ADR 026 and `tests/document-files.php` |
| Explicit migrations | ADR 027 and `tests/migrations.php` |
| Redis cache and schedule coordination | ADR 028, `tests/cache.php`, and `tests/redis-coordination.php` |

ADR 029 records the selected `core` or `application pattern` exit for every row. The complete repository gate is `composer check`; a partial test does not close the profile.

## Supported runtime boundary

Framework and skeleton Composer metadata use `~8.4.0`, meaning supported PHP is 8.4.x and not 8.5. CI's explicit PHP matrix contains 8.4. A future PHP minor is unsupported until its own compatibility review and complete gate are added. Database transport certification remains a separate PHP 8.4 job against SQLite, MySQL 8.4, and PostgreSQL 17; it certifies the narrow PDO transport, not every application SQL statement.

## Limits

The integrated SQLite profile proves composition and failure behavior for one sanitized schema. It does not certify production database plans, grants, concurrency, identity, tenant policy, external job effects, Redis or filesystem topology, sink delivery, deployment, or capacity. Consumers record those facts in their own harness and tests. No result in this profile permits ORM behavior, implicit scopes, observer magic, generic facades, automatic discovery, or another hidden-I/O mechanism.
