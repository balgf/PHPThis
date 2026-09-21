# PHPThis Agent Evaluation Kit

This directory is the maintainer-only, model-neutral data contract for bounded PHPThis agent evaluations. Version 1 freezes an explicit task inventory, task and result schemas, and one public smoke case. It does not call a model, select a provider, install a skill, expose a model API, or add framework or consumer runtime behavior.

The [first observed context route](context-observation-v1.md) records Issue #70's single interrupted revision-26 smoke attempt, its measurement limits, and the supported testing-route clarification. It makes no comparative claim.

Schema v3 adds the non-comparative `explain.file-profile-s3` task for routing-review seed 7. It freezes the exact source and effective prompts against tracked maintainer commit `d7ab170c190c9fd7e18be3ca00b874a8d954fa2b` under the `repository-only` condition, with an exact runner/version, model/settings/context, logical and transport tool, and generation-isolation profile. Materialization excludes dirty, untracked, and Git-metadata bytes. The candidate permits no mutation or workspace-write permission. Replay validation derives the final response and external-action summary from bounded retained events, requires the frozen candidate manifest to equal the tracked source, cross-binds OCI image/toolchain evidence, requires a literal empty patch, and validates internally consistent integer provider usage.

The explanation task runs no application check or public scorer. Its automated result covers only task identity, response integrity, the unchanged workspace, resource bounds, external-action admission, and cleanup. Human review separately scores route selection, necessary concern coverage, unsupported claims, answer correctness, repairs, and clarification; `correct_completion` remains unknown until an accountable review is complete. Live records use `execution_kind: live-model`; synthetic controls use `execution_kind: synthetic-control` and cannot become semantic completion evidence. Synthetic validation and task preparation are not a real-model result, and this task authorizes no comparison claim.

Live explanation records retain the tracked source's exact Composer lock and matching installed-package metadata; replay rejects a duplicate framework package in the dependency manifest. `validate-score` validates the final evidence manifest and validation record as well as the structural evidence. A completed live review may change only `human_review` and derived `correct_completion` in `score.json`; the unchanged manifest binds the canonical pending score reconstructed from that record.

Stable v1 markers:

- `AGENT_EVALUATION_SCHEMA_VERSION(1)`
- `AGENT_EVALUATION_TASK(change.simple-ping)`
- `AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`
- `AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION`

## Boundary

The kit evaluates an agent's work; it is not framework authority. The installed Consumer Contract, application-owned context, concrete application source, and application tests remain the authority for a candidate change. A task manifest may bound a trial more narrowly but cannot weaken those sources.

This directory remains outside the framework package. It adds no Composer runtime dependency, framework API, Consumer Contract requirement, Strict Profile rule, application checker rule, or production execution path. Version 1 also contains no model runner or provider adapter. ADR 048 separately accepts the maintainer-only `tools/agent-evaluation-controller.php` v0.2 entrypoint and its fixed modules under `tools/agent-evaluation-controller/`; it must produce records conforming to these unchanged schemas and cannot weaken this data contract.

The repository CLI is one small explicit ordered entrypoint, `tools/agent-evaluation.php`. It requires exactly `support.php`, `tasks.php`, `run.php`, and `score.php` from this directory; those modules own shared bounded parsing, task contracts, run records, and score records respectively. This is fixed composition, not task discovery, a registry, dependency injection, or an alternate execution path.

## Explicit inventory

`tasks.json` is the complete ordered list of task IDs. `tasks.php` pins each ID’s schema version, revision, and manifest hash. The original v1 smoke, the three v2 comparison tasks, and the v3 explanation task remain distinct contracts. Consumers of the data must read that file and then resolve each ID to exactly one reviewed directory under `tasks/`; they must not discover tasks by walking the filesystem. A task revision is immutable. Changing a prompt, base fixture, workspace policy, budget, check, or rubric requires a new revision; every changed hashed artifact receives a new hash. `source-skeleton` means the `skeleton/` directory from the same exact framework checkout as this task data; that enclosing repository revision must be retained with the trial artifacts, and changing that source requires a new task revision.

Each v1 smoke task directory contains:

- `task.json`: the bounded machine-readable contract;
- `prompt.md`: the exact bytes supplied as the task request;
- `rubric.md`: the reviewed meaning of its checks and scores; and
- `public/`: visible smoke evidence for harness development.

The filename `public/holdout.php.fixture` is retained as a task-local scorer name, but the file is public and therefore is not an official holdout. It can prove only that a candidate and scorer can be wired together and that the named visible cases pass.

## Hashes

Paths in a manifest are relative to that task directory. Every recorded SHA-256 is the lowercase hexadecimal digest of the referenced file's exact bytes. Validation must fail on a missing file, a digest mismatch, an unlisted task, an unsupported schema version, or an unexpected manifest field.

The task manifest itself is not mounted as application context unless a separately recorded condition explicitly says so. The exact prompt bytes are the user request. Workspace policy, budgets, and scorers belong to the evaluation controller.

## Workspace policy

`allowed_existing_paths` names the only existing candidate files that may change. `allowed_new_paths` is the finite exact list of new repository-relative paths. A path in `protected_paths` is immutable; when it names a directory, every descendant is protected. No permitted path may equal or descend from a protected path. The controller must also reject absolute paths, parent traversal, symlinks, submodules, device nodes, sockets, unexpected executable bits, and writes outside the candidate root.

Line and file limits are task-admission bounds, not evidence that a patch is well designed. A smaller patch can still be wrong, and an over-limit patch is inadmissible rather than merely losing style points.

## Budgets

Task budgets are comparable-condition limits:

- `model_tokens` is the maximum provider-reported model-token allowance selected before the trial;
- `wall_seconds` covers the complete agent trajectory, excluding later external holdout scoring;
- `repair_turns` is the maximum number of controller-initiated repair responses after the initial response; and
- `command_output_bytes` bounds retained stdout and stderr for each command.

Token accounting differs between providers. A run records input, output, cached, and reasoning tokens separately and uses `null` when a provider does not report a value. `model_tokens` bounds each reported category and the input-plus-output total when both are available; cached and reasoning categories are recorded separately because providers commonly report them as subsets rather than additional tokens. Never invent a conversion between tokenizers. Compared conditions use the same model identifier, settings, budgets, and available tools.

## Run and score records

`schema/run.schema.json` defines provider-neutral provenance. It records the task manifest and rubric hashes, source revision and pinned fixture digest, prepared-dependency digest, condition, model identity and opaque settings, optional context bundle, available tools and permissions, budgets, provider-reported usage, timing, repairs, termination, and distinct hashed event and candidate-patch artifacts. Version 1 treats the event and candidate-patch artifacts as opaque retained bytes: the validator proves only root containment, regular single-link file identity, the size bound, and the recorded hash. The separate controller owns their exact formats and the truthfulness of their contents. The schema records controller claims; it does not authorize execution or prove that those claims are true.

Run budgets exactly repeat the selected task budgets, including the per-command output cap. Actual repair turns may not exceed that fixed allowance, and start and finish use canonical UTC timestamps at whole-second precision within the task wall-time limit. Provider usage remains separate because reported token categories and tokenizer semantics vary.

The task revision pins the source-skeleton Git tree and fixture digest. `base_revision` is the exact 40- or 64-character Git object ID used to prepare that source skeleton, while every run's `base_fixture_sha256` must equal the task revision. The fixture digest is the SHA-256 of a byte-sorted, newline-terminated manifest whose lines are `<Git mode> <file SHA-256> <source-skeleton-relative path>` for every source-skeleton file. The controller retains that manifest with the run artifacts; v0.1 reconstructs the checked-in source fixture in its self-test but does not prepare a candidate checkout.

Prepared dependencies remain controller-owned provenance rather than task authority. Each run retains a byte-sorted, newline-terminated manifest using `<mode> <file SHA-256> <dependency-root-relative path>` and records that manifest's path and SHA-256. This is the one retained artifact whose internal format v0.1 validates. The validator binds the retained manifest bytes; it does not inspect the dependency tree or prove that a controller described it truthfully. An official comparison separately requires the same prepared-dependency manifest hash for every repeated run of one task/condition pair; different task package metadata receives separate prepared identities.

`schema/score.schema.json` binds one exact run record and candidate patch, then separates admissibility, mandatory Boolean checks, five normalized dimensions, the weighted automated result, and human review. An inadmissible run cannot pass regardless of its numeric score. A task rubric owns its exact weights and mandatory checks. Human review remains separate from the automated result.

The v0.1 CLI validates independently pinned task revisions and frozen source hashes, JSON records no larger than 1 MiB and no deeper than 64 decode levels, duplicate object-name rejection, event and patch artifacts no larger than 16 MiB each, distinct artifact identity and root confinement, exact task-budget and timing consistency, score arithmetic and check/dimension consistency, and run-to-score linkage. It does not inspect a candidate workspace, enforce the manifest's path policy against a patch, run candidate code, execute the application gate or public scorer, call a model, or originate scores. The separately located v0.2 controller owns those lifecycle claims, and the unchanged v0.1 validator remains their independent structural check.

## Public smoke case

`change.simple-ping` starts from the repository's `source-skeleton` fixture and asks for one dependency-free exact-literal endpoint. Its workspace policy permits the three application changes implied by the current four-file simple-endpoint locality metric: the installed current operational guide is read, while the existing route-area manifest, one new handler, and the nearest behavior test may change. The root route manifest, composition root, dependencies, installed framework, and existing health behavior are protected.

The visible scorer checks:

- exact `GET /ping` status, headers, and body;
- routing-owned `POST /ping` method rejection;
- unchanged `GET /health`; and
- an unchanged unknown-route response.

This is one public smoke task and `comparative_claims` is permanently `false` for its revision. Even the accepted isolated controller's correctly evidenced passing score cannot establish that PHPThis, a skill, a prompt strategy, or one model outperforms another.

## Isolated controller v0.2

The ADR 048 controller follows one explicit finite lifecycle: `prepare -> generate -> freeze -> score -> validate -> retain -> cleanup`. Its first implementation uses the deterministic test-only `fake-codex` runner through every stage and every normal repository check. That path uses synthetic fixtures, makes no model or external request, requires no credential or container engine, and executes no AI-authored candidate code. It proves only controller logic and failure handling against those controls.

Stable controller boundary markers are `AGENT_EVALUATION_CONTROLLER_OCI_ONLY`, `AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY`, and `AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK`.

Preparation verifies this kit's exact task revision, source fixture, and read-only prepared-dependency manifest, then creates a disposable candidate without Git metadata or maintainer-checkout access. Generation receives the exact prompt, context, settings, tools, environment, and budgets. Freeze begins only after the fixed fake runner has ended and its original process group is absent, then inspects the workspace and derives its canonical patch. The native fake cannot prove termination of a child that escapes into another process session; generalized descendant cleanup belongs to future OCI container destruction. The real boundary gives the unchanged candidate application gate and controller-owned public scorer a separate identity and environment without a runner, credential, proxy, network, or writable frozen candidate. The v0.2 fake represents this only with a distinct disposable scoring workspace and fixed repository-controlled processes for those two command slots, so it proves their order, bounds, failure propagation, record derivation, original-process-group cleanup, and disposable-resource cleanup, not operating-system isolation or either real scoring result. The controller emits v0.1-compatible run and score records, invokes this directory's unchanged validator, retains bounded hashed redacted evidence, and runs bounded cleanup even after an earlier failure.

The only accepted real adapter is `codex-exec`, one fixed opt-in `codex exec` invocation behind a reviewed Docker-compatible OCI boundary pinned by immutable image digest and the host-side `responses-api-run-proxy`. That proxy alone holds the provider credential and enforces the selected model and token quota; generation network is proxy-only and scoring network is none. The candidate receives no raw provider key or reusable credential. Unsupported or unverifiable resource, network, proxy, scorer, descendant, or cleanup controls fail closed. There is no direct-host, native macOS, `sandbox-exec`, arbitrary-shell, discovered-runner, or second-runner fallback. Issue #68 implements that opt-in adapter; deterministic OCI transport evidence remains separate from a paid model trial.

## Official evaluation boundary

An official holdout is an independently versioned scorer artifact that is unavailable to the agent and candidate workspace. Its identifier and SHA-256 are fixed before generation. The controller freezes the model response, tool events, external-action record, and candidate patch before private scoring. Version 2 keeps expected data in the host and supplies only individual case inputs to a fixed observation driver in the separate scoring environment. Public smoke files never substitute for that boundary. The v1 score schema binds only this visible smoke scorer; the separate v2 run/score schemas identify each private holdout without reinterpreting v1 results.

Real execution must use a fresh disposable standalone candidate checkout without Git metadata or maintainer-checkout access. It must use an unprivileged identity, a minimal allowlisted environment, synthetic data, no secrets, no production endpoints, denied arbitrary network access, read-only prepared dependencies, bounded CPU, memory, disk, processes, time, tokens, and output, and cleanup that terminates descendants. Candidate-owned tests or Composer scripts are not an external oracle.

Official comparative claims additionally require the same frozen functional prompt, model identity, model settings, budgets, tools, and execution policy for every condition; an external post-generation holdout; and at least ten trials per condition. Report per-task pass rates and dimension results. Timing is secondary to functional correctness, boundary behavior, rollback where applicable, and resource bounds.


## Bounded comparison protocol v1

[comparison-v1.md](comparison-v1.md) defines issue #69's exact 60-slot study: three common application changes, PHPThis and competent ordinary PHP, ten attempts per task/condition. The task inventory admits `change.protected-endpoint`, `repair.transaction-rollback`, and `change.filtered-collection` with `schema/task-v2.schema.json`; `schema/run-v2.schema.json` retains planned and failed attempts, and `schema/score-v2.schema.json` separates mandatory observations from accountable human review.

Both conditions receive the same task prompt and observable contract, matched PHP/PHPStan/strict-rules versions, model/settings/tools, 40,000 shared input/output tokens, 1,200 generation seconds, and zero post-score repairs. Each candidate may change only its supplied handler and add `tests/behavior.php`. Source fixtures are materialized by stripping the final `.fixture` suffix; source context and installed dependency documentation have separate frozen inventories. Private expectations and reference solutions remain external and are never generation context.

The maintainer source stores the common offline bundle once at `references/phpstan/`. The fixed fixture composition includes it at `docs/phpstan/` before hashing, context validation, and bound checks; materialization writes separate regular files for each application. The original storage consolidation preserved revision-5 task, context, and effective fixture hashes; subsequent instruction changes have their own fixture revision. No candidate receives a link to shared writable storage.

Revision 2 of the three comparison tasks corrects only the PHPThis `AGENTS.md` knowledge-map route to `vendor/phpthis/framework/docs/knowledge-map.md`, with refreshed fixture/context hashes. Revision 1 incorrectly named a nonexistent application-local path; retained generation evidence shows that agents followed it. Prompts, budgets, plain-PHP fixtures, and private holdout identities are unchanged. Existing prepared projects and campaigns retain revision 1; corrected tasks require fresh preparation and a new frozen campaign. This path correction does not establish that the cumulative token budget is sufficient.

[Fixture revision 3](fixture-revision-3.md) supplies identical pinned official PHPStan references in both conditions and a shared `ObservationResult` PHPDoc contract for public test helpers. Each application routes diagnostic work to its protected offline reference. The six source contexts and fixture hashes are refreshed; earlier campaigns keep their original inputs. Prompt, API, handler, profile, budget and private holdout contracts remain unchanged. Fresh preparation checks establish fixture readiness, not improved model outcomes.

[Fixture revision 4](fixture-revision-4.md) adds the missing `nullCoalesce.offset` reference to all six bundles and explains malformed-input boundaries in the JSON observation transport. Source contexts and fixture hashes advance together. All fixture PHP source, task behavior, evaluation policy and retained calibration results are preserved; fresh offline checks do not constitute another model run.

[Fixture revision 5](fixture-revision-5.md) adds task-directed installed documentation routes, shared guidance for completing work after a green gate, and all 1,109 authored diagnostic pages at the same pinned official commit. Exact-identifier reads avoid loading the full source inventory. The source-context entry cap now matches the existing 4,096-file fixture cap, preserving complete accounting without changing byte or model budgets. Fresh checks establish readiness; any model-efficiency improvement remains unmeasured.

[Fixture revision 6](fixture-revision-6.md) makes final line citations optional, keeps diagnostic references out of general inventories, and routes typed-helper authoring directly to the supplemental PHPDoc sections. Only the two fixture guides and their revision/hash bindings change; shared reference bytes, executable behavior, budgets, and calibration suffixes remain unchanged.

[Fixture revision 7](fixture-revision-7.md) corrects protected malformed-route handling and ordinary-PHP query instrumentation. Both conditions return the contracted `404` for unmatched paths; failed SQL preparation is counted, and the statement limit is checked before execution. Earlier campaign inputs remain unchanged.

The controller derives results from bounded raw HTTP/policy/database observations and independently held expected data. Query instrumentation runs inside the candidate PHP process and still needs human review for bypass or fabricated output; a passing counter alone is insufficient. Missing attempts and pending human reviews prevent correctness-rate claims. Preparation controls are not model results, and issue #69 remains open until real trials, human review, and findings are recorded.
