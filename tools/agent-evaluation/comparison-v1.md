# Bounded common-change comparison, revision 1

This protocol prepares issue #69's comparison of PHPThis and ordinary PHP on three fixed application changes. It is not a results report or permission to make paid requests. The machine-readable `comparison-v1.json` and the three version-2 task manifests in the existing `tasks.json` inventory define the experiment. The version-1 `change.simple-ping` task, its public scorer, and its existing records remain separate and cannot become comparative evidence.

## Question and scope

For the selected model, fixed starting applications, and fixed budgets, how often does each condition produce a functionally correct, bounded change that passes its application gate and human semantic review?

The tasks are a protected endpoint, a transaction/rollback repair, and an extension to a filtered collection. Both conditions receive the same functional prompt for a task, public API and data requirements, synthetic policy inputs, database schema, and competent starting implementation. Each condition uses PHP 8.4, the same exact PHPStan and strict-rules versions, the same model/settings and Codex tools, and the same execution limits. Each retains its protected, complete `composer check` command.

PHPThis supplies its runtime, installed guidance, checked profile, and explicit database boundary. Ordinary PHP supplies a typed, manually composed application with native PDO and maximum-level PHPStan with strict rules. It is not given an intentionally weaker static-analysis baseline. Runtime, available documentation, diagnostics, source structure, and framework dependencies still differ. The treatment is this complete bundle; the study cannot attribute an outcome to one rule or to documentation alone. It is not a comparison with Laravel, Symfony, or all ordinary PHP practices.

## Freeze before execution

Before any paid request, retain one reviewed campaign configuration containing the protocol and task hashes, the exact source revision, all materialized fixture and visible-context inventories, each condition's prepared dependency and lock hashes, model identifier and recorded revision (null with an explanation when unavailable), reasoning settings, tool versions and permissions, generation/scoring image digests, PHP/PDO/SQLite identities, private holdout identities, complete run order, evidence destination, dated pricing, and accountable spending approval. Source-context inventories and installed dependency documentation are distinct costs; neither may silently substitute for the other.

The task manifest contains only a holdout identifier, revision, and SHA-256. Private case data and expected observations remain outside the public repository and all generation inputs. Their exact bytes are fixed before the first trial. Changing a prompt, starting fixture, context, policy, budget, scorer, model, or execution condition starts a new versioned campaign. Do not repair a task or tune an oracle in response to a scored real submission and continue the same campaign.

The fixed scoring driver is `evaluation/observe.php`. It accepts bounded case inputs after generation ends; expected answers remain in the host controller. The controller compares observations and derives the verdict. A candidate's exit code or printed `PASS` is not an acceptance result. Application tests remain public regression evidence and never replace the private cases.

## Trial count and order

The fixed campaign contains 60 planned slots: three tasks, two conditions, and ten fresh attempts per task and condition. Ten is the predeclared sample size, not a power calculation or evidence of generality.

The existing task inventory supplies the three task positions. In round 0 through 9, rotate task order by `round % 3`. Within each task pair, PHPThis goes first when `(round + fixed_task_position) % 2` is zero; ordinary PHP goes first otherwise. Each condition occupies the first position five times per task. Freeze the expanded 60-slot schedule before execution. Use fresh candidate, agent state, scratch, and database state for every slot; pass no previous response, patch, scorer feedback, or private expectation into a later generation.

Every slot uses 40,000 shared input/output model tokens, 1,200 generation seconds, a 4 MiB command-output bound, and zero controller-initiated repair turns. The agent can edit and run public checks during its initial invocation within those limits. No generation resumes after private scoring. The OCI resource and cleanup policy remains mandatory for both conditions.

## Attempts and failures

Record all 60 planned slots before starting any of them. Record a started attempt before generation can make a request. Preserve admission, preparation, generation, freeze, scoring, validation, retention, interruption, exhausted-budget, and cleanup failures with the evidence actually available. An incomplete attempt cannot fabricate a patch, provider usage, successful gate, or completed scorer.

Do not replace a failed attempt until ten pass, silently omit unavailable slots, retry an ambiguous paid request, or choose the best response from several starts. An infrastructure failure remains visible and counts against operational completion. A separately labelled sensitivity analysis may distinguish infrastructure from candidate failures, but cannot replace the primary accounting. Abort on an unresolved cleanup or ambiguous provider request; retain the remaining slots as not run and report an incomplete campaign.

Do not report per-task rates until the ten predeclared attempts for each condition have actual outcomes. An incomplete campaign reports counts, reasons, and missing slots, with rates unavailable. Any later continuation needs an explicit decision about the unchanged campaign identities and cannot erase the original record.

## Measures and review

The primary measure is correct completion out of the ten planned attempts in each task/condition group. Correct completion requires admissible execution and cleanup, the complete application gate, all private functional/boundary/rollback checks applicable to that task, the declared resource checks, and a human semantic pass. Report functional correctness and the other components separately so a static-analysis failure does not conceal correct observable behavior or a passing gate conceal a regression.

Report counts and rates per task, the PHPThis-minus-ordinary-PHP difference, and binomial uncertainty intervals. Do not pool the six groups into a claim about general framework quality. Small samples, fixed authored fixtures, one selected model, SQLite-only application SQL, and correlated repeated attempts constrain interpretation. Equal rates or a negative difference must be reported as observed; do not select only favorable tasks.

Secondary records include regressions, edits and public-check repairs where observable, controller repair turns, human intervention requests and their justified/unnecessary classification, reviewer effort, elapsed generation and scoring time, and provider-reported input, output, cached, and reasoning token categories. Use `null` with a reason for unavailable metrics. Tool-call counts do not establish human interventions, repeated file writes do not establish repairs, and wall time does not establish reviewer effort. Preserve the distinction between measured values, agent-reported claims, and reviewer judgments.

Human semantic review stays separate from automated scores. A reviewer checks that the product requirements are actually met, that tests and observation adapters were not bypassed, that database authority and transaction behavior are coherent, and that the proposed change is maintainable. Record the reviewer's identity, decision, observed effort when available, and concrete reasons. Do not invent these records or treat an agent's approval as accountable human review.

## Measurement trust and limits

The host owns private expectations and verdict computation. Query traces are observations from the protected PHPThis or native-PDO instrumentation inside the evaluated PHP process, not an independent database server audit. Their semantics must be matched and demonstrated by known fixed-query and growing-query controls. They do not prove the absence of every possible in-process tampering or alternate-connection path. Human review must inspect database call sites, instrumentation bypass, forged output, process creation, reflection, and any attempt to alter or replace the observation path; unresolved evidence makes the resource result unavailable and prevents correct-completion claims.

Durable rollback evidence comes from a separate observer connection after the operation and includes pre-existing data and transaction state. Statement counts detect query-count growth, not total rows scanned, query-plan quality, lock behavior, or production latency. SQLite observations do not certify another engine's SQL or transaction semantics. The common task contract permits a bounded join or batch solution; an oracle must not require one preferred SQL spelling.

The existing OCI limits still apply. Final free-space snapshots can miss transient disk exhaustion after files are removed, scoring exits do not provide complete child-level resource-event telemetry, and network isolation does not count every denied socket attempt. Retain these limits with the campaign rather than presenting unknowns as zero.

## Cost and publication

Record standard API pricing from its dated official source, model and service tier, reported token categories, estimated API charge, and any unavailable or ambiguous amount. Cached and reasoning tokens may be subsets; do not double-count them. Do not convert ChatGPT subscription usage to API charges or invent unreported token categories. The host quota proxy enforces token reservations; a dated price calculation is not an independently enforced billing limit.

Prepare a concrete run-count/spending proposal and obtain accountable approval before execution. This protocol document, a configuration file, a passing synthetic fixture, or normal CI cannot grant that approval.

Keep synthetic/scorer and OCI integration verification in a preparation section, separate from actual model attempts. A findings report must retain every attempt, its exact identities, component results, uncertainty, missing data, human reviews, adverse cases, and the scope of any conclusion. Until real trials and human review exist, report preparation status only. Issue #69 remains open until its actual trial and findings requirements are fulfilled.
