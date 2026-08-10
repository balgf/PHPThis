# PHPThis Agent Evaluation Kit

This directory is the maintainer-only, model-neutral data contract for bounded PHPThis agent evaluations. Version 1 freezes an explicit task inventory, task and result schemas, and one public smoke case. It does not call a model, select a provider, install a skill, expose a model API, or add framework or consumer runtime behavior.

Stable v1 markers:

- `AGENT_EVALUATION_SCHEMA_VERSION(1)`
- `AGENT_EVALUATION_TASK(change.simple-ping)`
- `AGENT_EVALUATION_PUBLIC_SMOKE_ONLY`
- `AGENT_EVALUATION_EXTERNAL_HOLDOUT_AFTER_GENERATION`

## Boundary

The kit evaluates an agent's work; it is not framework authority. The installed Consumer Contract, application-owned context, concrete application source, and application tests remain the authority for a candidate change. A task manifest may bound a trial more narrowly but cannot weaken those sources.

This directory remains outside the framework package. It adds no Composer runtime dependency, framework API, Consumer Contract requirement, Strict Profile rule, application checker rule, or production execution path. Version 1 also contains no model runner or provider adapter. Model invocation and tool mediation belong to a separately reviewed execution environment that produces a run record conforming to `schema/run.schema.json`.

The repository CLI is one small explicit ordered entrypoint, `tools/agent-evaluation.php`. It requires exactly `support.php`, `tasks.php`, `run.php`, and `score.php` from this directory; those modules own shared bounded parsing, task contracts, run records, and score records respectively. This is fixed composition, not task discovery, a registry, dependency injection, or an alternate execution path.

## Explicit inventory

`tasks.json` is the complete ordered list of v1 task IDs. Consumers of the data must read that file and then resolve each ID to exactly one reviewed directory under `tasks/`; they must not discover tasks by walking the filesystem. A task revision is immutable. Changing a prompt, base fixture, workspace policy, budget, check, or rubric requires a new revision; every changed hashed artifact receives a new hash. `source-skeleton` means the `skeleton/` directory from the same exact framework checkout as this task data; that enclosing repository revision must be retained with the trial artifacts, and changing that source requires a new task revision.

Each task directory contains:

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

`schema/run.schema.json` defines provider-neutral provenance. It records the task manifest and rubric hashes, source revision and pinned fixture digest, prepared-dependency digest, condition, model identity and opaque settings, optional context bundle, available tools and permissions, budgets, provider-reported usage, timing, repairs, termination, and distinct hashed event and candidate-patch artifacts. Version 1 treats the event and candidate-patch artifacts as opaque retained bytes: the validator proves only root containment, regular single-link file identity, the size bound, and the recorded hash. A future controller owns their exact formats and the truthfulness of their contents. The schema records controller claims; it does not authorize execution or prove that those claims are true.

Run budgets exactly repeat the selected task budgets, including the per-command output cap. Actual repair turns may not exceed that fixed allowance, and start and finish use canonical UTC timestamps at whole-second precision within the task wall-time limit. Provider usage remains separate because reported token categories and tokenizer semantics vary.

The task revision pins the source-skeleton Git tree and fixture digest. `base_revision` is the exact 40- or 64-character Git object ID used to prepare that source skeleton, while every run's `base_fixture_sha256` must equal the task revision. The fixture digest is the SHA-256 of a byte-sorted, newline-terminated manifest whose lines are `<Git mode> <file SHA-256> <source-skeleton-relative path>` for every source-skeleton file. The controller retains that manifest with the run artifacts; v0.1 reconstructs the checked-in source fixture in its self-test but does not prepare a candidate checkout.

Prepared dependencies remain controller-owned provenance rather than task authority. Each run retains a byte-sorted, newline-terminated manifest using `<mode> <file SHA-256> <dependency-root-relative path>` and records that manifest's path and SHA-256. This is the one retained artifact whose internal format v0.1 validates. The validator binds the retained manifest bytes; it does not inspect the dependency tree or prove that a controller described it truthfully. An official comparison separately requires the same prepared-dependency manifest hash for every run in the condition set.

`schema/score.schema.json` binds one exact run record and candidate patch, then separates admissibility, mandatory Boolean checks, five normalized dimensions, the weighted automated result, and human review. An inadmissible run cannot pass regardless of its numeric score. A task rubric owns its exact weights and mandatory checks. Human review remains separate from the automated result.

The v0.1 CLI validates independently pinned task revisions and frozen source hashes, JSON records no larger than 1 MiB and no deeper than 64 decode levels, duplicate object-name rejection, event and patch artifacts no larger than 16 MiB each, distinct artifact identity and root confinement, exact task-budget and timing consistency, score arithmetic and check/dimension consistency, and run-to-score linkage. It does not inspect a candidate workspace, enforce the manifest's path policy against a patch, run candidate code, execute the application gate or public scorer, call a model, or originate scores. Those operations belong only to a later separately accepted isolated controller; until then, check booleans and dimension values remain controller-supplied claims.

## Public smoke case

`change.simple-ping` starts from the repository's `source-skeleton` fixture and asks for one dependency-free exact-literal endpoint. Its workspace policy permits the three application changes implied by the current four-file simple-endpoint locality metric: the installed current operational guide is read, while the existing route-area manifest, one new handler, and the nearest behavior test may change. The root route manifest, composition root, dependencies, installed framework, and existing health behavior are protected.

The visible scorer checks:

- exact `GET /ping` status, headers, and body;
- routing-owned `POST /ping` method rejection;
- unchanged `GET /health`; and
- an unchanged unknown-route response.

This is one public smoke task and `comparative_claims` is permanently `false` for its revision. Even a later isolated controller's correctly evidenced passing score cannot establish that PHPThis, a skill, a prompt strategy, or one model outperforms another.

## Official evaluation boundary

An official holdout is an independently versioned scorer artifact that is unavailable to the agent and candidate workspace. Its identifier and SHA-256 are fixed before generation. The controller freezes the model response, tool events, external-action record, and candidate patch before mounting that scorer into a separate scoring environment. Public smoke files never substitute for that boundary. The v1 score schema binds only this visible smoke scorer; an official comparison therefore requires a later schema revision that identifies its external scorer.

Future execution must use a fresh disposable standalone candidate checkout, not a Git worktree whose `.git` file grants access to a maintainer checkout. It must use an unprivileged identity, a minimal allowlisted environment, synthetic data, no secrets, no production endpoints, denied arbitrary network access, read-only prepared dependencies, bounded CPU, memory, disk, processes, time, and output, and cleanup that terminates descendants. Candidate-owned tests or Composer scripts are not an external oracle.

Official comparative claims additionally require the same frozen functional prompt, model identity, model settings, budgets, tools, and execution policy for every condition; an external post-generation holdout; and at least ten trials per condition. Report per-task pass rates and dimension results. Timing is secondary to functional correctness, boundary behavior, rollback where applicable, and resource bounds.
