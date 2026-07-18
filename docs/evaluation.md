# Development pattern evaluation

PHPThis needs executable evidence, not a claim that visible SQL or AI-oriented documentation automatically prevents mistakes.

The current harness proves specific code and execution properties. It does not yet prove that an AI will answer every framework question correctly, use the installed version instead of model memory, or surface every decision that belongs to a human.

## Current proof

Run:

```bash
composer check
```

The focused scaling harness is also available as `composer test:query-scaling`; the transaction evidence runs with the behavioral tests in `composer test`.

The harness creates fresh SQLite databases with the same `users` and indexed `user_events` schema used by the example application. Each user has two events.

The accepted `GET /users` handler uses one bounded aggregate statement. It returns equivalent data for a 2-user fixture and a 50-user fixture while executing exactly one statement in both cases. Its query trace contains one fingerprint executed once and is not truncated.

The negative control returns the same data by selecting users once and then counting events once per user. It executes 3 statements for 2 users and 51 statements for 50 users. The child-query fingerprint reaches 50 executions. The harness also proves that:

- `PHT003` rejects the exact negative source at its stable line;
- the invalid source is not an accepted `.php` file or autoload target;
- a query budget of 3 records three executed statements and rejects the fourth before PDO or the query trace sees it.

The `POST /users` tests provide the transaction evidence. Empty and 500-user fixtures both require two writes. With a budget of 1, the event write is rejected and the preceding user insert is rolled back.

## Database transport certification

`composer test:database-drivers` runs the same narrow PDO transport probe for every driver selected by `PHPTHIS_DATABASE_TEST_DRIVERS`. Local and complete repository checks default to SQLite. The dedicated CI job supplies real SQLite, MySQL 8.4, and PostgreSQL 17 drivers and services; an unavailable requested driver or missing DSN fails rather than skips.

The probe proves native connection, unique named string/integer/boolean/null bindings, associative one-row and collection fetches, one-row insert and delete counts, commit, rollback, visibility from a second connection, independent traces, budget rejection before PDO, and rethrown database failures recorded by the trace. Its SQL deliberately uses only the tiny common subset needed to exercise transport. It is not a dialect translation layer.

## Future AI comparison

The current proof compares programming patterns; it does not yet prove that one AI model, prompt, or context strategy outperforms another.

A model comparison must use:

1. A frozen functional prompt that does not mention N+1 or reveal the holdout checks.
2. Fresh isolated worktrees for a base PHP condition and a PHPThis-context condition.
3. The same model identifier, settings, token budget, time budget, and available tools.
4. An external holdout scorer added only after generation.
5. At least 10 trials per condition before reporting a rate.

Record the prompt hash, model identifier, repository revision, generated diff, validity-gate output, small and large statement counts, repair turns, and token use. The primary metric is the percentage of functionally correct submissions that also keep query count constant and pass every boundary and rollback check. Timing is secondary because this SQLite fixture is a correctness experiment, not a production benchmark.

## Future knowledge-interface evaluation

Evaluate the no-traditional-manual claim separately from code generation. Freeze a set of framework explanation and implementation-planning questions across at least two installed PHPThis revisions, including questions about unsupported capabilities and deliberate version differences. Give the AI only the repository and normal project instructions.

For each answer, record:

- the exact framework revision and application-context revision;
- every cited contract, decision, source file, symbol, test, and diagnostic;
- whether the answer separates installed behavior, application policy, and proposals;
- unsupported claims, invented APIs, missed conflicts, and unjustified certainty;
- consequential choices correctly surfaced for human judgment;
- files loaded, tokens used, and repair turns after holdout review.

A passing answer must be correct for the installed revision, supported by accessible repository evidence, explicit about missing authority, and free of invented framework behavior. Human reviewers score semantic correctness; automated checks verify cited paths and symbols where practical.

## Limits

- SQLite proves the execution shape used by the repository tests, not plans or locking behavior on another database.
- The cross-driver transport probe does not prove application SQL, DDL, generated identifiers, scalar representations, update row counts, error translation, isolation, locking, plans, charset, timezone, TLS, or timeout behavior on any engine.
- Multiple certified connections remain independent; the probe does not provide distributed transactions or cross-database atomicity.
- Statement budgets do not bound rows scanned or event-history fan-out; this proof detects query-count growth, not total database cost.
- The aggregate read can observe concurrent changes according to the target database's isolation rules; production evaluation must choose that policy explicitly.
- The read returns only the first 50 users; pagination and continuation are not implemented yet.
- The sample enforces JSON `Content-Type` and maps malformed input, unsupported media, and oversized bodies; database conflicts remain generic 500 failures until a reliable named translation is designed.
- The unknown-failure log is deliberately minimal and has no request ID or query-trace summary yet.
- The project-owned AI context and installed knowledge map define a grounding strategy, not proof that every model will follow it; grounded-answer trials remain future work.
