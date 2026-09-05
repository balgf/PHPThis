# PHPThis Agent Evaluation Controller v0.2

This maintainer-only controller implements the accepted ADR 048 lifecycle:

```text
prepare -> generate -> freeze -> score -> validate -> retain -> cleanup
```

It remains under export-excluded `tools/` and changes no framework runtime, public API, Consumer Contract, Strict Profile, application checker, skeleton requirement, or Composer dependency. Issue #68 adds the opt-in real adapter to the synthetic controller introduced by #42. The accepted [ADR 048](../../docs/decisions/048-isolated-agent-evaluation-controller.md) remains the historical decision; it is not rewritten as implementation evidence.

## Fixed composition

`tools/agent-evaluation-controller.php` explicitly requires, in order:

1. `contract.php`
2. `workspace.php`
3. `process.php`
4. `codex.php`
5. `scoring.php`
6. `controller.php`

There is no module or task discovery, runner selector, provider facade, dependency-injection container, arbitrary command setting, or arbitrary output-path setting. `process.php` is the only controller file that owns native process primitives. The unchanged v0.1 task inventory remains the sole task authority, and v0.2 accepts only `change.simple-ping` revision 26 with `comparative_claims: false`.

## Ordinary checks

```bash
composer test:agent-evaluation-controller
php tools/agent-evaluation-controller.php validate
```

The ordinary self-test exercises the deterministic test-only `fake-codex` lifecycle and pure proxy protocol controls. It performs no model request, needs no credential or OCI engine, contacts no external endpoint, and executes no AI-authored candidate. `composer check` and normal CI retain that synthetic route. These tests prove controller logic, not container containment or real-model behavior.

A legacy `run <run-id>` without its explicit live configuration still fails with `AGENT_EVALUATION_CONTROLLER_LIVE_CODEX_UNAVAILABLE`. Missing or unverifiable live controls fail closed; there is no direct-host, native macOS, `sandbox-exec`, arbitrary-shell, discovered-runner, or second-runner fallback.

## Live execution boundary

The sole accepted real runner is `codex-exec`, one fixed invocation of the pinned Codex executable in the generation image. Exact model and reasoning settings come from the reviewed profile, with a fresh bounded home and Codex state directory. The runner receives no upstream credential, ambient host environment, Git metadata, checkout, home mount, or engine socket. Optional client integrations and network tools are disabled in its fixed configuration; those settings do not replace OCI containment.

Generation uses Docker networking `none`. Its only provider connection is a loopback HTTP relay in the container, carried over the controller-owned standard-input/output channel to the host-side `responses-api-run-proxy`. There is no candidate network route to a host listener, Docker bridge, DNS server, or upstream API. The relay disables dumpability before starting candidate processes; relay failure or candidate interference fails the run.

The host proxy exposes only the selected Responses create operation. It rejects a different model or reasoning setting, stateful server conversation references, hosted tools, remote media, duplicate JSON members, unexpected paths, and unbounded bodies. Before each upstream create request, it calls the fixed Responses input-token-count endpoint, reserves the counted input plus bounded maximum output against the shared run allowance, and permits no concurrent pending reservation. The upstream destinations are fixed official count/create endpoints, with no redirects or retries. SSE is buffered within the output bound and released only after terminal model/usage validation. An ambiguous request revokes the run and retains its reservation; an exhausted quota fails the run.

The candidate-facing operation is exactly `POST /v1/responses`. The host additionally calls `POST /v1/responses/input_tokens` as the fixed quota prerequisite. This interprets ADR 048's single-operation boundary as the candidate's exposed authority; it does not claim that only one upstream endpoint is called. The implementation follows the official [token-counting contract](https://developers.openai.com/api/docs/guides/token-counting) and [reservation/uncertain-request guidance](https://developers.openai.com/cookbook/articles/per_run_spending_controller_responses_api). Exact CLI/configuration fields were checked against the pinned client and official [non-interactive Codex](https://learn.chatgpt.com/docs/non-interactive-mode) and [configuration reference](https://learn.chatgpt.com/docs/config-file/config-reference) documentation. The proxy implements token accounting; the illustrative pricing policy in that guidance is not an automatic monetary guarantee here.

Proxy totals are the authority for token usage. Runner-reported usage is separately retained; missing categories remain unknown. Candidate events and command text are untrusted evidence. The report does not infer zero attempted sockets from a network-disabled container or claim complete telemetry for every denied action.

The launcher verifies digest-pinned generation and scoring image identities and exact PHP, Composer, Python, Codex, and relay identities as applicable. Generation and scoring must use identical PHP and Composer versions. Every candidate/scorer container uses a non-root numeric identity, read-only root, dropped capabilities, no-new-privileges, private PID namespace, and finite CPU, memory, disk, process, wall, and output limits. Unsupported engine controls fail preflight. Scoring uses a distinct image and identity with no Codex or relay.

The candidate filesystem lives in a bounded tmpfs-backed Docker volume. A fixed, read-only, no-network holder keeps that volume mounted after generation stops, so the controller can inspect output without restarting the candidate. Generation is stopped and its PID cleared before export; all exported paths, types, modes, counts, and sizes are validated before import. The existing workspace policy then rejects protected/unlisted changes, links, special files, unexpected executables, and edit-limit violations. The holder is infrastructure, not another runner.

Prepared package bytes are immutable and must contain no files in `vendor/.phpthis`. The controller creates that empty mount point only in its copied dependency volume. The checker-owned `vendor/.phpthis` cache and the application's `tmp/` test scratch space use separate bounded temporary mounts in generation and scoring. Their contents are excluded from the frozen source. Export accepts only the exact empty underlying infrastructure mount points and rejects unexpected nested files there. Candidate, temporary, cache, and shared-memory allocations are included in the fixed disk allowance. No dependency installation or candidate-owned Composer script runs during preparation.

The tmpfs sizes enforce storage capacity. End-of-run free-space readings are snapshots, not durable records of every failed write: a candidate could encounter `ENOSPC`, delete a file, and leave free space again. The controller rejects an observed full mount and retains this telemetry limit; it does not infer zero earlier disk-exhaustion attempts. Generation memory and PID exhaustion use persistent cgroup event counters. Scoring retains actual container exits and Docker's OOM state; that state alone cannot establish zero earlier child-level exhaustion attempts. Wall, output, and model-token allowance exhaustion terminates the run.

## Prepare and preflight

Build the separate targets described by [the OCI assets](oci/README.md), resolve them to immutable local registry digests, and prepare a standalone vendor tree from exact locked inputs outside generation. Keep its Composer lock file separately. Record its sorted file/mode/hash manifest digest, lock digest, image references, and toolchain versions in one bounded JSON configuration. Never copy host authentication or an engine socket into an image or prepared tree.

`agentEvaluationControllerReadLiveConfiguration()` in `contract.php` owns the exact configuration keys. The configuration contains the existing validated `profile`, the fixed `engine` fields, `prepared_dependencies`, `prepared_lock`, both SHA-256 identities, and an `approval` record naming its reference, exact model, one run, and decimal spending ceiling. It supplies no arbitrary command, upstream endpoint, runner, or output location. Additional context bundles are unavailable for this initial repository-context smoke task.

The bounded smoke protocol uses these fixed limits and explicitly reviewed inputs:

| Setting | Value |
| --- | --- |
| Task | `change.simple-ping`, revision 26 |
| Provider and reasoning | OpenAI; exact model ID, recorded revision, and supported reasoning effort supplied for review |
| Run count | One fresh invocation per approval; another invocation requires another approval |
| Shared model allowance | 40,000 input/output tokens, reserved before each create request |
| Generation wall allowance | 1,200 seconds, including proxy requests and termination |
| Retained command output | 4 MiB bound |
| Per-container CPU, memory, PIDs | One CPU, 1 GiB without swap, 64 PIDs |
| Writable allocation | 768 MiB candidate, 112 MiB `/tmp`, 64 MiB application test scratch, 64 MiB checker cache, 16 MiB shared memory |
| Repair allowance | Task permits one; this initial adapter uses zero post-score repair turns |
| Identities | Generation UID 65534; separate scoring UID 65533; exact immutable images and toolchains required |
| Evidence | Fixed sibling `agent-evaluation-runs/<run-id>/evidence/` |

```bash
php tools/agent-evaluation-controller.php preflight /absolute/path/smoke-configuration.json
```

Preflight uses no upstream key or model call. It checks the selected local engine/images/toolchains and removes its temporary control directory. A passing preflight is prerequisite evidence, not a paid-trial approval or a completed adversarial integration proof.

Before a paid run, obtain accountable approval for the concrete model/settings, one run, token allowance, spending ceiling, engine/images/dependency identity, and evidence destination. The approval record documents that decision; merely generating a JSON file is not approval. Token limits are enforced by the proxy. Price assumptions and the monetary ceiling must be reviewed for the selected model before execution.

After that approval, the host process alone receives `OPENAI_API_KEY` and runs:

```bash
php tools/agent-evaluation-controller.php run 00000000000000000000000000000068 /absolute/path/smoke-configuration.json
```

Use a fresh 32-character lowercase hexadecimal run ID. The fixed evidence parent is `agent-evaluation-runs/` beside the framework checkout; an existing run cannot be overwritten. Do not place credentials in the configuration, command arguments, generated files, or evidence.

## Freeze, scoring, retention, and cleanup

Generation ends before output is exported and frozen. The controller validates the canonical candidate tree and patch, creates the read-only scoring copy, and destroys the generation container before introducing any scorer. Fresh scoring containers have no network, runner, relay, key, checkout, or writable frozen source; their cache and test scratch mounts start empty. They execute the unchanged application `composer check` and exact public scorer in separate fixed command slots; actual exits and bounded streams determine the result. A container startup failure is inadmissible. Candidate-owned checks never replace the controller-owned public scorer.

The controller validates unchanged v0.1 run and score records and retains bounded hashed task/prompt/profile, source/dependency identities, proxy and process observations, candidate patch/manifest, scoring results, validation, and cleanup evidence. Failed attempts retain phase and available structural evidence; a preparation failure cannot fabricate a completed generation record. The source and dependency identities remain linked to the selected task, not a later task registry.

Cleanup runs after success, failure, or catchable interruption (`SIGINT`/`SIGTERM`). It stops and destroys only run-owned containers and volumes, checks their absence, removes temporary controller/proxy state and disposable workspaces, and retains the approved evidence. Container destruction establishes that candidate descendants are gone. Cleanup failure preserves the primary failure and makes an otherwise passing run fail.

Before each OCI resource creation, the controller persists its exact owner, run ID, and pending resource name in a bounded private `owned-resources.json` ledger. It checks ownership before removal and updates the ledger after verified destruction. The final ledger and cleanup result are retained even when cleanup fails. A failed standalone preflight preserves its control directory when resources remain unresolved.

A timed-out or interrupted Docker create request may still finish in the daemon after its client exits. Such names remain under `pending-creation-*` roles, along with the run's dependent volumes, and cleanup is unverified. A momentarily absent name does not settle that operation. Manual recovery must reconcile the pending creation before releasing those volumes.

`SIGKILL`, host failure, or an unavailable Docker daemon can prevent cleanup. Such an attempt has no successful cleanup claim. Recovery must inspect the private temporary `phpthis-oci-control-*` directory's ledger, or the retained evidence ledger, and select resources with **both** recorded `org.phpthis.evaluation.owner` and `org.phpthis.evaluation.run_id` labels. Verify each exact name and both labels before removing it; do not use a global Docker prune. Confirm absence afterward and record that manual recovery separately from the original run's immutable evidence.

The ordinary synthetic proof still establishes only original-process-group cleanup and cannot contain a native descendant that escapes its process session. The opt-in OCI proof must demonstrate the real container boundary separately. A deterministic Responses fixture using the real Codex executable is OCI/client integration evidence, not a real model trial. A paid public smoke pass still has `comparative_claims: false`; its public scorer is not a confidential holdout.

Stable boundary markers:

- `AGENT_EVALUATION_CONTROLLER_VERSION(2)`
- `AGENT_EVALUATION_CONTROLLER_OCI_ONLY`
- `AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY`
- `AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK`
