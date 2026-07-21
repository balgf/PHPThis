# ADR 029: Alpha 2 consumer-profile rollup

Status: accepted

## Context

ADR 019 through ADR 028 evaluated ten separate capabilities. Closing the Alpha 2 consumer profile requires one durable record of each exit decision and one sanitized request that composes the accepted boundaries. Without that rollup, individually passing examples could be mistaken for a framework-owned application stack, and the repository could claim a supported PHP range wider than its tested runtime.

The composed proof must not invent or expose a private application, organization, data set, or naming scheme. It must also keep every application recipe replaceable instead of turning it into a framework service.

## Decision

The ten capability exits are:

| Child | Capability and accepted record | Exit | Source owner and executable evidence | AI guidance |
| --- | --- | --- | --- | --- |
| #2 | bounded multiple typed routes, ADR 019 | `core` | `src/Routing/`; routing and application tests in `tests/run.php` | `.ai/routing.md` |
| #3 | request policy, ADR 020 | `application pattern` | `example/src/Accounts/`, protected actions under `example/src/Documents/` and `example/src/Users/`; `tests/request-policy.php` and `tests/consumer-profile.php` | `.ai/request-policy.md` |
| #4 | typed input boundaries, ADR 021 | `application pattern` | `example/src/Users/CreateUser/`; `tests/run.php` and `tests/consumer-profile.php` | `.ai/types.md`, `.ai/http.md` |
| #5 | finite data paths, ADR 022 | `application pattern` | direct application SQL; `tests/request-policy.php`, `tests/run.php`, and `tools/test-query-scaling.php` | `.ai/database.md` |
| #6 | terminal request summaries, ADR 023 | `application pattern` | `example/src/Observability/`; `tests/observability.php` and `tests/consumer-profile.php` | `.ai/observability.md` |
| #7 | SQLite durable jobs, ADR 024 | `application pattern` | `example/src/Jobs/`; `tests/jobs.php` and `tests/consumer-profile.php` | `.ai/jobs.md` |
| #8 | explicit CLI and scheduler, ADR 025 | `application pattern` | `example/src/Cli/`, `example/bin/console.php`; `tests/cli.php` | `.ai/cli.md` |
| #9 | bounded file transfers, ADR 026 | `core` | bounded HTTP values and emission in `src/Http/`, application storage under `example/src/DocumentFiles/`; `tests/document-files.php` | `.ai/file-transfers.md` |
| #10 | explicit SQLite migrations, ADR 027 | `application pattern` | `example/src/Migrations/`; `tests/migrations.php` | `.ai/migrations.md` |
| #11 | Redis cache and schedule lease, ADR 028 | `application pattern` | application Redis source under `example/`; `tests/cache.php` and `tests/redis-coordination.php` | `.ai/cache.md`, `.ai/cli.md` |

No capability has an overall `defer` exit. ADR 026's overall exit is `core` because it accepted only bounded HTTP upload and local-file response primitives into core. Storage remains application-owned and byte ranges remain a subordinate deferral; neither changes that capability exit.

The existing Create path becomes the umbrella request: `POST /accounts/{account_id:positive-int}/users`. `CreateUserHandler` immediately converts the route value, then performs explicit account authentication, tenant resolution, action authorization, media-type checking, and strict `CreateUserCommand` parsing. `TransactionalCreateUser` executes four complete named-parameter statements on one SQLite connection: create the user only while the actor has current `account_memberships(principal_id, account_id)` authority, attach the new user through `account_users(user_id, account_id)`, record the account-scoped event, and insert one bounded versioned welcome-job envelope. Principal and user identifiers are separate namespaces even when their integer values coincide. All four statements share one explicit transaction. The job becomes visible with the business data at commit; it is not an after-commit callback.

This accepts one example-domain rule: successful account-scoped user creation also associates the new user with that account. Forward migration `0007_create_account_users` creates the relation table without copying any `account_memberships` row or otherwise inferring user ownership from a principal ID. The current migration manifest therefore has seven permanent steps and a 23-statement fresh-run ceiling; a valid six-step database applies the schema-only forward step in four statements. The rule is application source and may be replaced by a consumer with equivalent explicit policy and evidence. It is not a PHPThis identity, tenant, permission, or user model.

`tests/consumer-profile.php` compares empty and 500-row fixtures and requires exactly four statements in both. It requires denial and rejected-input paths to perform no protected SQL, directly rejects mismatched requested/resolved accounts and missing actor membership, forces final job publication and query-budget failures, verifies direct table rollback counts, parses the exact stored welcome-job envelope, observes committed state from a second connection, and requires one generated response correlation identifier plus one summary containing bounded evidence but no credentials, input values, SQL, bindings, or exception messages. Repeated Create evidence crosses the actor's numeric principal ID and proves that created user IDs cannot collide with authorization data.

The application registry conservatively maps all known client failures with `private, no-store`, so a failure after protected policy cannot fall back to a public cache policy. The framework-owned generic unknown-failure response also changes from `no-store` to `private, no-store`; this is a stricter literal header policy, not a cache helper or route-sensitive mechanism.

The supported PHP runtime is exactly the PHP 8.4.x Composer range `~8.4.0`. CI expresses the supported runtime as a matrix containing PHP 8.4. PHP 8.5 and later remain unsupported until a separate reviewed compatibility decision and complete CI evidence exist. The former `^8.4` constraint unintentionally advertised untested later 8.x minors, so narrowing the accepted runtime is a material compatibility correction. Consumer Contract version 7 records the upgrade: PHP 8.4 applications change no source and rerun the complete gate; PHP 8.5-or-later applications cannot adopt this contract and must not bypass Composer until PHPThis reviews that runtime. An earlier release's broader constraint is not support evidence and requires independent consumer validation. Strict Profile version 2 is unchanged.

This decision records the Alpha 2 claim boundary, not mutable tag, package, GitHub release, or installation availability. Verify publication state from the external release evidence, tagged repositories, and Packagist.

Framework core and release inventory must continue rejecting runtime namespaces or files that present an ORM, model layer, repository, facade, discovery mechanism, observer, scope mechanism, service container, query builder, binding helper, placeholder helper, or autowiring mechanism. The path check uses exact mechanism segments and reviewed CamelCase suffixes so ordinary names such as `Transform` and `Telescope` remain allowed. The existing permanent exclusions also remain: no implicit or global scopes, observer magic, automatic discovery, generic cache, lock, lease, queue, scheduler, migration, validation, binding, or dialect abstraction. The guard is a filename heuristic and boundary regression check, not a substitute for human review of indirectly implemented or differently named behavior.

## Consequences

Alpha 2 has one auditable map from every evaluated capability to `core` or `application pattern`, one checked-in integrated consumer proof, and an honest tested PHP range. The framework API does not grow; the only core behavior change here strengthens the fixed generic 500 cache header. Consumers may copy and change the application composition only after recording their own identity, tenancy, authorization, schema, job, failure, and operational decisions.

Passing the sanitized profile does not certify an arbitrary application's database plans, grants, concurrency, identity provider, tenant isolation, external job effect, Redis failover, filesystem, deployment, or observability destination. Those remain consumer evidence.

## Reconsider when

A supported PHP minor is added, a capability's ownership moves between core and application, or independent consumer evidence justifies a narrower shared contract. Reconsider the affected row and proof explicitly; do not infer adjacent framework behavior.
