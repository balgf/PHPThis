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

- #69 was [closed with the repeated study, study reviews and final comparative findings deferred](https://github.com/balgf/PHPThis/issues/69#issuecomment-5589005226), as [#72 records](https://github.com/balgf/PHPThis/issues/72). Its [preparation report](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation/comparison-preparation-v1.md) separates zero-provider controls from five approved launches. The first stopped before generation; the second through fourth reached real responses but left usage unknown. The fifth retained four token-budget failures, one unsettled-response failure and 55 unrun slots. Later calibrations and fixture revisions retain their own identities. Paid attempts occurred; no valid completed PHPThis-versus-ordinary-PHP study exists.
- #70's [recorded resolution](https://github.com/balgf/PHPThis/issues/70) includes completed live code-change revision 29 and explanation revision 16. The code change passed its application gate and public scorer. Review accepted behavior while correcting an inaccurate read-order claim; its frozen score's human-review field remains pending. The explanation completed with an empty patch, verified cleanup and an accepted conditional S3 answer; its reviewed score records semantic completion. Its 16 settled requests produced a USD 0.428604 standard-price proxy estimate, not a billing receipt.
- Detailed #70 artifacts remain in the maintainer-held sibling directories `agent-evaluation-preparation/issue-70-completion-v2/` and `agent-evaluation-preparation/issue-70-explanation-v16/`, outside this repository. The public issue resolution records their disposition. Earlier failed, stopped and incomplete attempts remain evidence; neither one completed run per revision nor synthetic controls establish a causal routing improvement or productivity rate.
- #67's [restriction review](https://github.com/balgf/PHPThis/tree/main/tools/restriction-review) retains fixed-source reproductions. It is historical evidence; current size-proxy policy is [ADR 063](decisions/063-maintainability-review-over-size-proxies.md).

The [evaluation narrative before consolidation](https://github.com/balgf/PHPThis/blob/884e7154984d774f9da8f595c820c2b978c8118a/docs/evaluation.md) preserves dated calibration details and original numerical observations. Frozen prompts, fixtures, hashes, source snapshots, run records, scores and tagged release history remain unchanged. Use their original revisions when reproducing them; never apply today's instructions retrospectively.

## Future comparisons and explanation review

Follow the [fixed three-task, 60-slot comparison protocol](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation/comparison-v1.md). Keep prompts, model/settings, budgets and tools matched; use fresh isolated candidates and external holdouts introduced after generation. At least ten trials per condition are required before reporting a rate. Calibration and public smoke results cannot supply that rate. Record all attempts, interruptions, admissibility, repair turns, provider-reported usage and unknown usage; tokenizers are not interchangeable. Correctness, boundary behavior and bounded query count precede timing.

### Proposed bounded follow-up (#89)

#89 completed the proposal for a two-start feasibility pilot. On 2026-10-12, the maintainer accepted [#96's](https://github.com/balgf/PHPThis/issues/96) offline preparation scope: determine whether one representative maintenance task can produce a reviewable solution in each condition under a small allowance, before investing in a repeated study. Paid execution remains pending its own frozen packet and approval.

The task is `change.filtered-collection`: add a literal optional category filter and per-document event count while preserving tenant/current-membership isolation, keyset pagination, response shape and bounded query growth. [Revision 8](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation/fixture-revision-8.md) adopts the shorter task route in both conditions, preserving the functional prompt, starting PHP, API and private holdout. The PHPThis dependency pins merged framework commit `1245e4b3ae6b8888b9f93d7c320039e3f8bda0e2`.

The [pilot-v1 policy](https://github.com/balgf/PHPThis/blob/main/tools/agent-evaluation-controller/README.md#two-start-feasibility-pilot-v1) admits two fresh starts, PHPThis then ordinary PHP, in the existing controller. It uses `gpt-5.4-2026-03-05`, high reasoning and standard service. Each start permits 1,000,000 cumulative tokens, at most 200,000 counted input tokens per request, 1,200 generation seconds, 4 MiB command output and zero controller repair turns. The proposed API ceiling is USD 1 per start / USD 2 total. Allow up to 20 minutes of accountable semantic review per candidate; exhausted review remains pending.

[Official standard pricing](https://developers.openai.com/api/docs/pricing), checked 2026-10-12, remains USD 2.50 input, 0.25 cached input and 15.00 output per million tokens below the higher context tier. The [model reference](https://developers.openai.com/api/docs/models/gpt-5.4) lists the selected snapshot and high reasoning. This verifies published terms, not this account's model access or remaining credit. Pre-dispatch reservations enforce the token and monetary allowances; estimates are not billing receipts.

Before execution approval, freeze source/task/context/dependency/image/scorer identities, effective prompts and order; verify both application gates and unchanged independent private holdouts using known-correct/adverse controls. Reconcile relevant historical accounting separately. Preparation, synthetic tests and issue approval supply no spending authority.

Retain both planned starts and every actual outcome, including failure, exhaustion, unknown usage and unrun work. Stop on unresolved accounting or cleanup. No replacement, automatic retry or repair after private scoring is permitted. Record automated components separately from accountable review, including observed repairs/interventions, reviewer effort, elapsed time and provider usage/cost; unavailable metrics retain their reasons.

Two starts establish only observed feasibility for these candidates under the bundled framework, guidance, diagnostics and structure. They establish no comparative success rate, causal documentation improvement or general superiority. If both are reviewable, present the case for further study; otherwise retain the blocker and leave that study deferred. The six-start calibrations, 60-slot comparison and historical outcomes retain their existing identities and requirements.

For knowledge-interface work, freeze questions across at least two installed revisions, including unsupported capabilities and deliberate version differences. Record the exact framework/application revisions, cited evidence, loaded files, usage, repairs, missed conflicts, invented capabilities and decisions surfaced for human judgment. Automated link/symbol checks support accountable semantic review; they do not replace it.

For database-setup questions, apply [the scope contract](configuration.md#scope-database-setup-before-implementation). The frozen ambiguous prompt in [ADR 037](decisions/037-database-setup-scope-gate.md) requires one combined question for unresolved database and schema scope before external action. Explicit controls proceed without reopening accepted choices. Retain the first response, inspected files, mutations, model/settings, time and repairs. Instruction distribution alone does not prove an agent follows it.
