# Unfamiliar reviewer receipt and disposition

Reviewed consumer: `search-alpha7` (exact hash in manifest), installed framework v0.1.0-alpha.7 at de07cbd449ed3e905331aeea105e528fbaa83ac3. Fresh agent with no inherited turns; input/intervention record in `review-prompt.md`. This receipt preserves its reported findings and command/read inventory; the parent did not independently capture the reviewer's full tool stream.

## Findings returned

1. **P2, application evidence:** SQL membership enforcement lacked a regression test. Existing cross-account tests stopped before SQL; all successful synthetic principals retained membership. Create's guarded insert (`src/Users/CreateUser/CreateUserHandler.php:25`) and List's predicate (`src/Users/ListUsers/ListUsersHandler.php:19`) needed a policy-success/storage-membership-absent case. Existing Create behavior was documented as 409; List returned empty 200 without explicitly recording that outcome.
2. **P2, application evidence:** Declared cursor overflow/null/array/empty/unknown-key rejection, both property-order variants of mixed structural/value errors, and accepted 80-byte/search UTF-8 boundaries lacked retained assertions. Source appeared consistent; no implementation failure was confirmed.
3. **P2, installed guidance:** The immutable Alpha 7 installed README lines 25–45 called Alpha 6 current, while installed getting-started.md around line 145 still described Alpha 7 candidates as PENDING with no Alpha 7 command. This conflicts with the installed lock, can misdirect readers, and is distinct from live publication state, which the reviewer did not inspect.

The reviewer found feature-first placement, explicit composition, bounded literal search, scoped SQL, rollback and keyset lookahead discoverable. It could not inspect upstream `example/` or `tests/` paths mentioned by the installed CRUD guide: these are absent from Composer distributions and outside its review boundary.

## Disposition

- Findings 1–2: added `tests/directory-boundaries.php` with absent-membership Create/List outcomes, unchanged tables, zero rejected-input work, cursor representations/overflow, structural property-order equivalence, 80-byte ASCII/UTF-8 acceptance and 81-byte UTF-8 rejection. Recorded empty-200 List semantics in data context. Existing runtime feature code did not change. These additional cases are proved at the final source checkpoint; do not retroactively claim the original Alpha 7 checkpoint contained them.
- Finding 3: verified current maintainer-source README/setup guidance already identifies Alpha 7 correctly. Historical tags/vendor are unchanged. Added a focused setup explanation that installed release labels are frozen snapshots and the consumer lock/source reference determines identity; the consumer README records its exact snapshot caveat. The historical archive is not relabeled or republished.
- Discoverability limit: clarified in current `docs/crud.md` that the tree describes maintainer-source examples excluded from Composer, how to select the exact locked source revision when needed, and that installed guides/current app source remain the first authority. No extra source was supplied during the review.
- Final consumer and framework gate evidence is captured separately. No new framework feature, contract version, profile exception, baseline, or suppression was introduced.

## Reviewer command receipt

- `composer check`: first run passed PHP guardrails, PHPStan and PHP behavior, then failed because sandbox policy denied loopback binding. Normal escalated rerun exited 0, including real HTTP; 42 PHP files and one non-failing duplication advisory.
- `php -v`: 8.4.19. `php --ri pdo_sqlite`: SQLite 3.51.2.
- `composer show phpthis/framework --no-ansi` plus targeted lock inspection: v0.1.0-alpha.7 and exact reference above.
- Read-only inspection: cat, nl -ba, sed -n, rg --files, rg -n, wc -l. Two multi-file nl attempts returned usage output and were retried individually.
- `ls -ld` confirmed absent installed example/ and tests/.

## Reviewer read receipt

Paths relative to the reviewer consumer; braces enumerate separate files. Fully read:

```text
{AGENTS.md,README.md,composer.json,bootstrap.php,public/index.php}
.ai/{README.md,rules.md,change-workflow.md,project.md,architecture.md,data.md,request-policy.md,testing.md,operations.md,configuration.md,observability.md}
src/{DirectoryComposition.php,Routes.php,HealthRoutes.php,HealthHandler.php}
src/Accounts/{AccountId.php,Principal.php,Tenant.php,SameAccountTenant.php,DenyAllAuthentication.php,AuthenticateAccountRequest.php,ResolveAccountTenant.php}
src/Users/{UserId.php,UserRoutes.php}
src/Users/CreateUser/{CreateUserCommand.php,CreateUserHandler.php,AuthorizeCreateUser.php,AccountCreateAuthorization.php,CanCreateUser.php}
src/Users/ListUsers/{ListUsersPage.php,ListUsersHandler.php,AuthorizeListUsers.php,UserSummary.php,AccountListAuthorization.php,CanListUsers.php}
src/Observability/TerminalRequestCoordinator.php
tests/{DirectoryFixture.php,PolicyFixtures.php,fixture.php,directory.php,run.php,front_controller.py}
vendor/phpthis/framework/docs/{consumer-contract.md,knowledge-map.md,crud.md,database.md,type-safety.md}
```

Partial reads:

```text
composer.lock — framework identity only
vendor/phpthis/framework/README.md — lines 23–54 and targeted matches
vendor/phpthis/framework/docs/getting-started.md — lines 11–25,139–149 and matches
vendor/phpthis/framework/docs/request-handling.md — lines 1–28 and query/normalization matches
vendor/phpthis/framework/src/Http/RequestReader.php — query/normalization matches
vendor/phpthis/framework/src/Database/Connection.php — transaction-method matches
```

Line counts only, not content review: src/Observability/{CorrelationId.php,ErrorLogRequestSummarySink.php,QuerySummarySource.php,RequestSummary.php,RequestSummarySink.php}.

## Timing and limits

Reviewer-reported clock window: 2026-09-26 06:51:53–13:43:32 UTC (6h 51m 39s), INCLUDING the interruption/resume interval. Active review time was not separately measured. There was one parent continuation message and no technical help. No source/context edits, histories, memories, sibling consumers, maintainer checkout, subagents, paid campaigns, or external writes were reported. Model-token usage is unavailable. This is a qualitative fresh-agent review, not a human-adopter study or controlled independence/productivity measurement.
