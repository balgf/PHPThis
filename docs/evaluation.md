# Development pattern evaluation

PHPThis needs executable evidence, not a claim that visible SQL or AI-oriented documentation automatically prevents mistakes.

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

## Future AI comparison

The current proof compares programming patterns; it does not yet prove that one AI model, prompt, or context strategy outperforms another.

A model comparison must use:

1. A frozen functional prompt that does not mention N+1 or reveal the holdout checks.
2. Fresh isolated worktrees for a base PHP condition and a PHPThis-context condition.
3. The same model identifier, settings, token budget, time budget, and available tools.
4. An external holdout scorer added only after generation.
5. At least 10 trials per condition before reporting a rate.

Record the prompt hash, model identifier, repository revision, generated diff, validity-gate output, small and large statement counts, repair turns, and token use. The primary metric is the percentage of functionally correct submissions that also keep query count constant and pass every boundary and rollback check. Timing is secondary because this SQLite fixture is a correctness experiment, not a production benchmark.

## Limits

- SQLite proves the execution shape used by the repository tests, not plans or locking behavior on another database.
- Statement budgets do not bound rows scanned or event-history fan-out; this proof detects query-count growth, not total database cost.
- The aggregate read can observe concurrent changes according to the target database's isolation rules; production evaluation must choose that policy explicitly.
- The read returns only the first 50 users; pagination and continuation are not implemented yet.
- The request type does not yet carry headers, so the sample cannot enforce JSON `Content-Type`.
- Known input and conflict failures are not mapped to public HTTP responses until the Phase 1 error registry exists.
