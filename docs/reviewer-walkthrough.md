# Reviewing a protected request

This optional companion helps a human follow one existing operation and judge its evidence. The [Consumer Contract](consumer-contract.md) and [knowledge map](knowledge-map.md) remain the authoring interface; this guide adds no application requirement or approval gate.

## Establish the revision

The source links below are pinned to **`874b75c7c20604f30092a6e88aa9f0bb4306e907`**, an unreleased framework revision containing the checked example. They do not describe an Alpha 7 installation. The example and maintainer tests are excluded from the Composer package, so their links open that exact source snapshot on GitHub.

For a framework checkout, record:

```bash
git rev-parse HEAD
git status --short
git diff HEAD
```

Record untracked files separately: the diff does not include them. A commit alone does not identify a modified working tree. Compare any later changes against the pinned operation before reusing its conclusions.

For an application, record its own revision and changes, then run `composer show phpthis/framework --format=json`. Compare the installed version and source/dist references with the `phpthis/framework` entry in the application's `composer.lock`; a version constraint alone is insufficient. Inspect the installed contract, profile, and source under the configured Composer vendor directory. For source-only tests, use the matching framework reference. Application policy and tests still belong to the application revision, not the dependency revision.

For a historical release, use its matching tagged contract, source, and release notes, never later `main` guidance. [RELEASING.md](../RELEASING.md) owns the detailed recorded release state and publication evidence; this walkthrough establishes no package availability or new release.

## Follow `POST /accounts/{account_id:positive-int}/users`

1. **Find the wiring.** [ApplicationComposition::http()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/ApplicationComposition.php#L123) constructs the Create connection with `QueryBudget(4)` and `QueryTrace(4)`. [Routes::create()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Routes.php#L27) passes three explicit policies and `TransactionalCreateUser` to the handler. [UserRoutes::create()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Users/UserRoutes.php#L15) declares the POST route. Framework routing supplies the typed positive integer; it supplies no account authorization.

2. **Read policy order in the handler.** [CreateUserHandler::handle()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Users/CreateUser/CreateUserHandler.php#L25) wraps `account_id` in `AccountId`, then calls `authenticate()`, `resolve()`, and `authorizeCreate()` before parsing the command or invoking the operation. The checked-in composition injects deny-all policies. Successful tests replace them with synthetic policies; copying this example does not provide credential verification or production tenant policy.

3. **Inspect typed input.** After the media-type check, [CreateUserCommand::fromJson()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Users/CreateUser/CreateUserCommand.php#L26) accepts at most 2,048 bytes, uses JSON depth 16, and requires an object with exactly `name` and `email` as strings before checking their values. Native JSON duplicate keys use the last value. [ApplicationComposition::errorResponses()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/ApplicationComposition.php#L71) maps malformed structure to 400, unacceptable values to 422, oversized input to 413, and unsupported media to 415. Request-body ingestion happens before handler policy; this ordering claim concerns the operation's typed command.

4. **Read all four statements.** [TransactionalCreateUser::execute()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Users/CreateUser/TransactionalCreateUser.php#L24) uses one connection and one transaction. Its four direct `executeStatement()` calls insert a user, its `account_users` relation, a `user_events` row, and an `application_jobs` row. Complete SQL and bindings are visible at each call; each must affect exactly one row. All four compare requested/resolved accounts. The first two also check current actor membership; the event and job writes check the created account relation. `account_memberships` records principal access; `account_users` associates the created user. Equal numeric IDs do not join those identities. The operation commits after all writes and rolls back in `finally` if a transaction remains active.

   **Bound:** four protected SQL statements in both empty and 500-user fixtures. [Connection::run()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/src/Database/Connection.php#L99) charges the budget before PDO execution. Connection setup, transaction-control calls, rows scanned, lock waits, and latency are outside that count. Composition eagerly opens five SQLite connections, so a denial's zero protected statements is not zero database I/O.

5. **Follow the response and effects.** The handler encodes success JSON before calling the typed operation and returns 201 only after it succeeds: `data` contains `account_id`, `name`, and `email`, with `private, no-store`. [TerminalRequestCoordinator::handle()](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/example/src/Observability/TerminalRequestCoordinator.php#L66) selects a generic response for unknown failures, attaches `X-Request-ID`, and attempts one redacted summary sink invocation. The transaction publishes a welcome job; this POST does not send email. Summary redaction does not mean that the intended success payload hides name/email, or that the sink delivers durably.

## Locate and run the evidence

These are exact behavior names, searchable in the linked source and selectable with PHPUnit's filter. The integrated profile uses test-owned policies and error mappings; compare those with the real composition above when reviewing a change.

| Question | Named behavior and source | What it establishes |
| --- | --- | --- |
| Is the order and query bound retained as data grows? | [`consumer profile composes policy typed input transaction job and correlation`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/consumer-profile.php#L172) | Exact policy order, four statements at 0/500 existing users, response, summary, and four effects visible through a separate connection. |
| Does a denial stop protected work? | [`consumer profile denials and invalid input stop before protected SQL`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/consumer-profile.php#L228) | Stop order, mapped failures, zero protected statements and writes. |
| What happens if the final write fails or the budget is too small? | [`consumer profile job and budget failures roll back every scoped write`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/consumer-profile.php#L321) | Generic 500, no remaining transaction, and no user, relation, event, or job after either failure. |
| Can SQL reject stale or inconsistent authority? | [`consumer profile SQL rejects mismatched tenant and missing actor membership`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/consumer-profile.php#L381) | Direct-operation rejection after one statement with no writes. This does not certify concurrent policy changes. |

For input semantics, start at [`HTTP command parses one exact JSON object`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/input-projection.php#L343); adjacent behaviors cover duplicate keys, failure classification, and the typed operation boundary. For sink failure, use [`terminal coordinator keeps success and unknown responses unchanged when the sink throws`](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tests/observability.php#L578).

From the matching framework source checkout with its development dependencies installed:

```bash
composer test -- --filter 'consumer profile'
composer test -- --filter 'HTTP command'
composer check
```

The filters help inspect failures; the complete gate is still required for repository changes. A local run's actual engine/version and output define its evidence. The [evaluation guide](evaluation.md#database-transport-certification) distinguishes local SQLite checks from the exact CI transport matrix. Neither certifies this application SQL on another engine.

## Read one diagnostic as a repair

[PHT008](strict-profile.md#phpthis-owned-rule-catalogue) requires distinct named placeholder occurrences. The repeated-value case in [the static fixtures](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tools/test-strict-profile.php#L1903) has this rejected SQL shape:

```php
$connection->selectOneRow(
    'SELECT :same AS first_value, :same AS second_value',
    ['same' => 7],
);
```

Its correction keeps both result names and the intended integer value:

```php
$connection->selectOneRow(
    'SELECT :first_value AS first_value, :second_value AS second_value',
    ['first_value' => 7, 'second_value' => 7],
);
```

The correction changes parameter ownership, not the intended two values. [The passing fixture](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tools/test-strict-profile.php#L2027) checks the repaired shape; [the PDO transport probe](https://github.com/balgf/PHPThis/blob/874b75c7c20604f30092a6e88aa9f0bb4306e907/tools/test-database-drivers.php#L176) tests binding one logical value separately. PHT008 does not validate SQL meaning or compare bindings with SQL. It is a static diagnostic, not a runtime safeguard or authorization proof; recheck changed statements on the application's recorded engine.

## Judge the claim

| Evidence or authority | How to use it |
| --- | --- |
| Framework behavior | Trace framework `src/` and its tests; application policy is not a framework service. |
| Application policy | Review the explicit `Example` wiring and SQL above. A consumer owns its replacement policies, schema, and operational decisions. |
| Accepted decisions and proposals | The [decision index](decisions/README.md) records accepted rationale; a roadmap item or issue is proposed work until accepted. Acceptance alone proves neither execution nor publication. |
| Static checks | Establish the checked profile's specific properties. They do not prove correct authorization, efficient plans, or appropriate product policy. |
| Behavior and real-service evidence | The SQLite fixture exercises real transactions with synthetic data and policies. The [consumer profile's limits](consumer-profile.md#limits) exclude production identity, concurrency, grants, capacity, external job effects, and sink delivery. |

Useful review questions:

- Do the application's real policies authenticate the intended principal and resolve/authorize the requested account in this order? Does SQL preserve that authority at the write?
- Are statement counts, input sizes, and affected rows bounded? What evidence covers plans, scans, lock waits, and policy I/O beyond this fixture?
- Do denial, malformed input, database failure, and budget exhaustion select the intended response without partial writes or sensitive failure output? Are conflict and retry semantics decided for this application?
- Can a retry duplicate an external effect? Where are job-consumer idempotency, delivery failures, and sink availability tested?

No general human or AI productivity conclusion follows from this walkthrough or these behavior tests.

## Navigation check

On 2026-09-05, an independent reviewer agent unfamiliar with this operation started with this guide and followed its links into the pinned revision. It located the policy order, four-statement bound and excluded costs, named denial/rollback tests, and the synthetic-policy limitation without additional hints. This was an agent navigation exercise, not a human usability study.

The review found an imprecise rejected-fixture anchor and missing direct links to the passing fixture and repeated-value transport probe; an intermediate transport anchor pointed at the next test. Those links now target the exact calls. Link verification also repaired the diagnostic catalogue heading. The SQL description now distinguishes the first two writes' membership checks from the event/job relation checks. No core navigation dead end was found. These observations establish only this bounded review result.
