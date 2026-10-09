# Development pattern evaluation

Use the evidence appropriate to the claim. The full repository gate is `composer check`; focused commands support iteration. Passing code and distribution checks establish their tested properties. General AI authoring benefits still require comparative evidence.

## Current evidence

| Claim | Command or retained evidence | Limit |
| --- | --- | --- |
| Runtime request, routing, response, session and database behavior | `composer test`; concern files explicitly loaded by `tests/run.php` | The exercised paths and runtime only; deployment needs its own proof. |
| Strict Profile diagnostics | `composer test:profile`, `composer analyse`, installed `phpthis check` | PHT001–PHT008 enforce their documented structural/type rules; they do not certify business policy or security. |
| Context, package and application integration | `composer guard`, `composer test:consumer` | Concrete routes, exact inventories, clean installation and behavior controls; prose meaning still needs review. |
| Constant database statement count | `composer test:query-scaling` and transaction tests | Statement growth, not rows scanned, total database cost or production latency. |
| PDO transport | `composer test:database-drivers` and the [certification matrix](database.md#pdo-transport-certification-matrix) | Exact tested transport versions; application SQL and engine semantics need separate integration evidence. |
| Retained consumer change and upgrade | [Consumer lifecycle report](https://github.com/balgf/PHPThis/tree/main/tools/consumer-lifecycle) | Maintainer-led synthetic application evidence and one qualitative review, not independent adoption or longitudinal productivity. |
| Written instruction routes | [Dated context review](ai-context-routing-review.md) | Manual route and unsupported-claim review, not an agent compliance rate or token measurement. |

## Database and application boundaries

The accepted `GET /users` path executes one aggregate statement for both 2-user and 50-user fixtures. A negative control returns the same data using 3 and 51 statements; PHT003 rejects its loop, and a budget of three stops the fourth statement before PDO. Static keyset traversal covers 125 users as 50, 50 and 25 rows. It is not a snapshot or a generic paginator.

Account-scoped Create uses four fixed writes and proves rollback of all preceding changes on each failing write. Policy and invalid-input cases stop before protected mutation. The protected document-list recipe proves finite sort/filter variants, explicit tenant and membership bindings, one statement per non-empty page, and zero protected SQL for its recorded empty selection. `/users/{user_id:positive-int}` item Get proves one statement and missing-item behavior; it has no authorization/tenant policy. Update and Delete remain absent. Current requirements live in [database guidance](database.md), [CRUD structure](crud.md), [request policy](request-policy.md) and [typed boundaries](type-safety.md).

PHT006 proves finite native SQL string alternatives at direct calls. PHT008 checks distinct recognized placeholder occurrences; it does not compare binding arrays. Bound-data controls and query budgets do not prove grants, tenant isolation, nondestructive SQL, stored procedures, plans, locking, cross-database atomicity or production throughput. Verify authority with the real selected engine and deployed identity. The full limits and current evidence requirements belong to [database](database.md), [static analysis](static-analysis.md) and [performance](performance.md).

Session, file-transfer, cache, job, CLI, migration, coordination, logging, WebSocket and RAG evidence belongs to each current guide selected by `docs/knowledge-map.md`. Keep the exact backend and topology attached to every claim. Synthetic reference structure is not adoption; a sink attempt is not durable delivery; a local process/socket or SAPI proof is not production deployment or client receipt.

## Agent evaluation

The maintainer-only [kit](https://github.com/balgf/PHPThis/tree/main/tools/agent-evaluation) owns frozen tasks, schemas, rubrics, prompt/source identities and retained revision notes. The [controller](https://github.com/balgf/PHPThis/tree/main/tools/agent-evaluation-controller) owns preparation, generation, freeze, scoring, retention and cleanup. These tools are excluded from installed packages. The current smoke fixture pins the shorter starter guidance as revision 32; earlier trials remain tied to their original task and source revisions.

Ordinary `composer check` uses synthetic fixtures: no provider request, paid model call, credential, OCI engine or AI-authored candidate execution. Separately invoked deterministic OCI tests prove the recorded transport/isolation boundary without paid calls. A real trial requires exact approved model/settings, task/context revisions, resource limits, spending ceiling and operations. Use only the accepted digest-pinned OCI adapters and host-only quota proxy; generation is proxy-only and scoring has no network or provider credential. Failed or unverifiable isolation, freeze, accounting or cleanup controls fail closed. The controller guide owns the exact commands and diagnostic grammar.

`AGENT_EVALUATION_CONTROLLER_OCI_ONLY`, `AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY` and `AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK` identify that boundary. Native fake controls prove controller behavior, not real containment. A valid record proves schema consistency, not the truth of an unverified artifact description. An inadmissible candidate cannot pass regardless of score; semantic human review remains separate from automated checks.

The public `change.simple-ping` task has `comparative_claims: false`. `AGENT_EVALUATION_PUBLIC_SMOKE_ONLY` and `AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION` remain its evidence boundaries. A passing public smoke case is one observed candidate, not a comparison result.

### Recorded observations

- #69's [comparison preparation](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation/comparison-preparation-v1.md) retains the campaigns, failures, fixes and unrun slots. Real generation occurred, but no campaign establishes a comparison result. [Fixture revision notes](https://github.com/balgf/PHPThis/tree/main/tools/agent-evaluation) retain changed inputs and fresh-preparation requirements.
- #70's [code-change completion](https://github.com/balgf/PHPThis/tree/main/tools/agent-evaluation-preparation/issue-70-completion-v2) passed the application gate and public scorer. Review accepted behavior while recording an inaccurate claim about read order; its frozen score's human-review field remains pending.
- #70's [explanation revision 16](https://github.com/balgf/PHPThis/tree/main/tools/agent-evaluation-preparation/issue-70-explanation-v16) completed with an empty patch, verified cleanup and an accepted conditional S3 answer. Earlier failed and incomplete revisions remain retained. One run per revision cannot establish that a route fix caused completion or lowered cost.
- #67's [restriction review](https://github.com/balgf/PHPThis/tree/main/tools/restriction-review) retains fixed-source reproductions. It is historical evidence; current size-proxy policy is [ADR 063](decisions/063-maintainability-review-over-size-proxies.md).

The [evaluation narrative before consolidation](https://github.com/balgf/PHPThis/blob/884e7154984d774f9da8f595c820c2b978c8118a/docs/evaluation.md) preserves dated calibration details and original numerical observations. Frozen prompts, fixtures, hashes, source snapshots, run records, scores and tagged release history remain unchanged. Use their original revisions when reproducing them; never apply today's instructions retrospectively.

## Future comparisons and explanation review

Follow the [fixed three-task, 60-slot comparison protocol](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation/comparison-v1.md). Keep prompts, model/settings, budgets and tools matched; use fresh isolated candidates and external holdouts introduced after generation. At least ten trials per condition are required before reporting a rate. Calibration and public smoke results cannot supply that rate. Record all attempts, interruptions, admissibility, repair turns, provider-reported usage and unknown usage; tokenizers are not interchangeable. Correctness, boundary behavior and bounded query count precede timing.

For knowledge-interface work, freeze questions across at least two installed revisions, including unsupported capabilities and deliberate version differences. Record the exact framework/application revisions, cited evidence, loaded files, usage, repairs, missed conflicts, invented capabilities and decisions surfaced for human judgment. Automated link/symbol checks support accountable semantic review; they do not replace it.

For database-setup questions, apply [the scope contract](configuration.md#scope-database-setup-before-implementation). The frozen ambiguous prompt in [ADR 037](decisions/037-database-setup-scope-gate.md) requires one combined question for unresolved database and schema scope before external action. Explicit controls proceed without reopening accepted choices. Retain the first response, inspected files, mutations, model/settings, time and repairs. Instruction distribution alone does not prove an agent follows it.
