# ADR 048: Isolated agent evaluation controller

Status: accepted

## Context

The PHPThis Agent Evaluation Kit v0.1 freezes one explicit task inventory, schemas, prompt and scorer hashes, and independently validated run and score records. It deliberately does not prepare a candidate, call a model, mediate tools, execute candidate-owned code, freeze a patch, run a scorer, or establish that a record's execution claims are true.

Issue #42 asks for the next bounded step: one maintainer-only controller that can exercise the complete `prepare -> generate -> freeze -> score -> validate -> retain -> cleanup` lifecycle without weakening the v0.1 contract. Candidate output is untrusted. A model-facing process can write code, invoke tools, start descendants, emit unbounded output, attempt workspace escape, and target ambient credentials or external services. A controller running directly under the maintainer's ordinary host identity cannot claim isolation merely because it uses a temporary directory or a model client's own file-access policy.

The first implementation also needs useful deterministic CI evidence without a paid model call, a provider credential, an installed container runtime, or execution of AI-authored code. At the same time, a test double must not be presented as proof that a real runner, container engine, host proxy, or resource controller is safe.

On 2026-08-10 in Asia/Manila, the accountable human approved this decision for Issue #42, including the controller location and staged implementation, isolation boundary, sole future real runner and authentication boundary, finite resource policy, dependency preparation, separate scorer, retained evidence, cleanup, and comparative-claim limits.

## Decision

### Maintainer-only staged controller

The isolated controller is maintainer tooling behind the small ordered `tools/agent-evaluation-controller.php` entrypoint. It requires exactly `contract.php`, `workspace.php`, `process.php`, `codex.php`, `scoring.php`, and `controller.php` from `tools/agent-evaluation-controller/` in that order. Only `process.php` owns process-execution primitives. The controller remains outside the framework package, application checker, framework and skeleton runtime, Consumer Contract, Strict Profile, and production paths. It adds no Composer runtime or development dependency.

Version 0.2 implements the complete lifecycle first with one deterministic test-only runner identified as `fake-codex`. The fake follows the same finite controller state transitions, artifact contracts, admissibility checks, failure handling, retention rules, and cleanup state as a real run. It uses only synthetic fixtures, performs no paid model request, requires no credential, makes no external request, and executes no AI-authored candidate code. `composer check` exercises only this fake lifecycle and its negative controls. Passing it proves controller logic against those fixtures, not OCI containment or real-model behavior.

There is no runner plugin system, filesystem discovery, dependency-injection container, service locator, arbitrary command setting, generic shell adapter, or second task registry. The controller accepts only the existing explicit v0.1 inventory, initially only `change.simple-ping`.

The source locks three stable v0.2 boundary markers: `AGENT_EVALUATION_CONTROLLER_OCI_ONLY`, `AGENT_EVALUATION_CONTROLLER_FAKE_RUNNER_CI_ONLY`, and `AGENT_EVALUATION_CONTROLLER_NO_NATIVE_FALLBACK`.

### Sole future real runner and authentication boundary

The only accepted future real runner adapter is `codex-exec`, implemented as one fixed `codex exec` invocation. Real execution is separately opt-in and must fail closed unless it can run inside a reviewed Docker-compatible OCI boundary pinned by an immutable `sha256:<64 lowercase hexadecimal characters>` image digest. A mutable image tag is insufficient. The controller never falls back to direct host execution, including native macOS execution, `sandbox-exec`, or a model client's own host sandbox.

The OCI invocation is fixed controller code rather than candidate input. It uses an unprivileged numeric identity, a read-only root filesystem, all capabilities dropped, no privilege escalation, a private PID namespace with a finite PID cap, no host process namespace, no container-engine socket, a bounded candidate-only writable filesystem, bounded temporary storage, and explicit CPU, memory, temporary-disk, process-count, wall-time, output, and model-token limits. The controller records the exact engine, image digest, invocation policy, observed resource outcome, termination reason, and cleanup result. Unsupported or unverifiable controls make the run unavailable or failed; they are never silently omitted.

The candidate receives no raw provider key, host credential store, maintainer home directory, production secret, production endpoint, or ambient environment. `codex-exec` may reach only the reviewed host-side `responses-api-run-proxy`. That proxy alone holds the upstream credential, exposes no credential value to the runner, allows only the selected Responses API operation and model, enforces the run's token allowance, and denies arbitrary destination forwarding. Generation network policy is `proxy-only`; candidate tools receive no arbitrary network route. A provider or proxy design that requires making a reusable credential available to candidate code is not admissible.

The initial v0.2 change does not implement or exercise that real adapter, OCI launcher, or credential proxy. Their later implementation must conform to this exact boundary and supply focused integration and adversarial evidence before a real run can be called official.

### Prepare

Preparation resolves exactly one reviewed task revision from the explicit v0.1 inventory, validates its schema and hashes, and verifies the pinned `source-skeleton` fixture manifest. It copies that fixture into a fresh disposable standalone candidate root without `.git`, a Git worktree link, a submodule, or access to the maintainer checkout.

Dependencies are prepared outside generation from exact locked inputs. Their byte-sorted manifest, modes, file hashes, aggregate digest, toolchain identity, and preparation result are retained. The generation environment receives them read-only. No candidate-authored dependency installer or Composer script is part of preparation, and a mismatch fails before generation.

The controller supplies a minimal allowlisted environment, synthetic values, the exact prompt bytes, the exact selected context, fixed model identity and opaque settings, the task's budgets, and one fixed tool policy. The scorer and its mount path do not exist in the generation environment.

### Generate

Generation starts exactly one selected runner, never an arbitrary shell command. It records ordered bounded events, provider-reported token categories, start and finish times, repair turns, command output truncation, attempted external actions, resource observations, and the exact termination reason. A missing provider token category remains unknown rather than being inferred.

The controller enforces the complete wall budget from runner start through termination and bounds each retained stream. Model-token and repair allowances come from the task revision. Limit exhaustion terminates the run and makes it inadmissible; it is not converted into a partial success. Descendants remain inside the run's process and container boundary.

### Freeze and admissibility

The controller ends generation and terminates the runner before any scorer is introduced. While the generation container is stopped but still available for controlled inspection, it freezes and hashes the response, events, attempted external actions, candidate tree, and canonical candidate patch. It validates that frozen evidence before destroying the generation container. No generation process may survive or regain access after this point.

The frozen tree is inspected without executing candidate-owned code. The controller rejects absolute or parent-traversal paths, writes outside the candidate root, protected-path changes, unlisted changed or new paths, symlinks, hard-link surprises, submodules, sockets, devices, other special files, unexpected executable bits, file or line-limit overflow, dependency changes, scorer contact, unapproved network or external actions, and any mismatch between the inspected tree and retained patch. An inadmissible candidate cannot pass regardless of scorer output.

### Separate scoring, validation, and retention

Real scoring starts only after freeze in a fresh environment with a separate unprivileged identity and resource limits. Its network policy is `none`; it has no model runner, provider credential, host proxy access, maintainer checkout, or writable access to the frozen candidate. It receives only a frozen candidate copy, the exact read-only prepared dependencies, and the fixed public scorer mounted only after freeze for this v0.2 smoke task. The v0.2 fake represents that separation with a distinct disposable scoring workspace and fixed command slots under the maintainer test process; it does not establish an operating-system identity, network, or resource isolation claim.

The real scoring contract runs the candidate application's complete unchanged `composer check`, the task's mandatory public checks, and the public scorer under finite time, memory, process, disk, and output bounds. Candidate-owned checks are evidence but never replace the controller-owned scorer. Scorer integrity and identity are verified before and after use. Scoring cannot alter the frozen patch or generation record. The v0.2 `fake-codex` fixture instead supplies fixed repository-controlled processes for the application-check and public-scorer command slots so their ordering, output bounds, failure propagation, record derivation, and cleanup can be tested without executing candidate code. A fake result is not evidence that either real scoring command passed.

The controller derives admissibility, mandatory-check results, the five rubric dimensions, weighted result, and status from observed stage results. It emits v0.1-compatible run and score records, then invokes the unchanged v0.1 validator as a separate validation stage. Validation failure fails the run; the controller does not rewrite records until they pass.

Retention copies only the bounded evidence required to audit the run into a controller-owned root outside generation and scoring: task, prompt, context, source and dependency manifests and digests; runner, model, tool, proxy, image, and limit identity where applicable; bounded events and outputs; response; external-action record; frozen patch; check and scorer results; resource and termination observations; v0.1 run and score records; and validation result. It also creates the bounded destination for the following cleanup stage's structural result. Every retained artifact has a bounded size and recorded digest. Synthetic values and redacted structural events are retained; raw credentials and ambient environment are never artifacts.

### Cleanup and failure precedence

Cleanup runs after every prepared attempt, including preparation, generation, freeze, scoring, validation, and retention failures. The synthetic host proof terminates and verifies absence of the original runner and scorer process groups, then removes disposable candidates, dependency mounts, scorer mounts, and writable temporary storage. It cannot prove termination of a child that escapes into another native process session. The future real boundary must instead terminate its whole containers, verify generalized descendant cleanup through container destruction, and remove the per-run proxy route and network. Cleanup preserves only the approved retained evidence. It is bounded and writes only its bounded redacted result and digest into the destination established by retention; it does not alter frozen generation, patch, scoring, run, or score evidence.

A cleanup failure cannot turn an earlier failure into success or erase that earlier failure. It makes an otherwise successful run failed because resource release was not proved, while retaining both the primary and cleanup outcomes in redacted structural evidence. The controller never claims successful isolation when its phase-appropriate process-group or container cleanup and disposable-resource removal are unverified.

### Claim boundary

Version 0.2 remains limited to the existing public `change.simple-ping` smoke task, whose manifest permanently fixes `comparative_claims` to `false`. Its scorer is visible before generation and is not an official holdout. One fake or future real pass can demonstrate only that the named candidate satisfied this public contract under the recorded controller boundary.

No v0.2 result establishes that PHPThis, base PHP, an AI skill, repository context, prompt strategy, provider, or model is better than another. Comparative work still requires a later schema and separately approved protocol with an independently versioned scorer unavailable during generation, identical conditions, repeated trials, and human semantic review.

## Consequences

Maintainers gain one deterministic end-to-end controller lifecycle that can be exercised in the normal repository gate without model cost, secrets, untrusted code execution, or an installed OCI engine. The same stage and artifact boundaries can later support one real Codex runner without adding provider selection or dynamic execution architecture.

The strong boundary makes real execution deliberately unavailable on a host that cannot prove the pinned OCI, proxy, resource, scorer, and cleanup controls. There is no convenient native fallback. Operating the real path will require separately implemented and maintained container-image, engine-policy, quota-proxy, resource-inspection, and cleanup evidence.

The controller remains evaluation infrastructure rather than framework or consumer behavior. It changes no framework runtime, public API, application contract, Strict Profile rule, checker diagnostic, package dependency, or production deployment claim.

## Reconsider when

A focused adversarial review shows that the pinned OCI and host-proxy design cannot keep credentials and arbitrary network authority from candidate code; a supported OCI engine cannot prove one required resource or descendant-cleanup bound; the v0.1 record schemas cannot truthfully represent observed controller outcomes; or an independently reviewed external holdout and repeated-trial protocol is ready. Reconsider the smallest affected isolation, record, or claim boundary. Do not introduce a native fallback, second runner, provider plugin system, or comparative claim merely for convenience.
