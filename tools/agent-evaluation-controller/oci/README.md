# Explicit OCI image and deterministic integration fixtures

These source-only assets support the controller's one accepted `codex-exec` adapter. They are excluded from the framework package and do not add a Composer dependency or stage. The optional integration test runs the real pinned Codex binary against a deterministic local HTTP fixture with no provider credential, paid request, or model-quality claim.

## Reviewed image inputs

The Dockerfile has separate `generation` and `scoring` targets. The scoring target contains PHP, Composer, and Python; it contains neither Codex nor the generation relay. Generation uses numeric UID 65534 and scoring uses UID 65533. The controller additionally checks and imposes the runtime identity, filesystem, capability, PID, CPU, memory, network, storage, and cleanup policy; Dockerfile defaults alone do not establish that policy.

The build pins these public inputs:

| Input | Exact identity |
| --- | --- |
| Dockerfile frontend | `docker/dockerfile:1@sha256:ecfaec9ed6d810b56388c508f4121597bfbba70d41a6dfeee4d8cad5f295fc32` |
| PHP | `php:8.4.19-cli-bookworm@sha256:3ddb5a91b44a1c922538576d73a6e808fb2438d9d2d65b1fc3ffd55619fac2e3` |
| Composer | `composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c` |
| Codex CLI | Official `@openai/codex` version `0.153.1`, Linux arm64 or amd64 architecture package |

[`install-codex.py`](install-codex.py) pins the official npm tarball URL and published SHA-512 integrity for each supported architecture. It copies only the exact regular Codex and ripgrep members after verifying the complete bounded archive; it never runs a package installation script. The pinned PHP base's Debian repositories supply Python and small system dependencies during the trusted image build. Their resolution can change between builds, so these source pins alone are not a reproducible final image guarantee. A reviewer must record the actual final image's immutable repository digest and `/opt/phpthis/toolchain.json` before execution. The controller accepts neither a mutable tag nor an unverified local image ID.

Build from the framework repository root, then inspect the resulting digests:

```bash
docker build --target generation --tag phpthis-agent-evaluation-generation:review tools/agent-evaluation-controller/oci
docker build --target scoring --tag phpthis-agent-evaluation-scoring:review tools/agent-evaluation-controller/oci
docker image inspect phpthis-agent-evaluation-generation:review
docker image inspect phpthis-agent-evaluation-scoring:review
```

Image construction downloads only public build inputs. It is separate from candidate generation and scoring. The locked application dependencies must likewise be prepared and reviewed before either phase; no candidate-authored installer runs.

The local Linux arm64 verification used these immutable build identities. These images were built locally and were not published to an external registry:

| Verified output | Identity |
| --- | --- |
| Generation image | `phpthis-agent-evaluation-generation@sha256:2365c0ec7f7a8919ea5e26427b385b22d85d430a7b2e834f568244ea82a6e5ea` |
| Scoring image | `phpthis-agent-evaluation-scoring@sha256:4d17c7f96f38966aa5d17f15e1b5f1630d8f303acc3c0ef1a1e78c93b58fa799` |
| Generation relay SHA-256 | `eef4017c83216929f74504e0025821b12232190b8d87257cd8bc6186dfbfe123` |
| Actual shared tool versions | PHP `8.4.19`, Composer `2.8.12`, Python `3.11.2` |
| Actual generation CLI version | Codex `0.153.1` |
| Prepared dependency tree SHA-256 | `6a171c1660e8e78fdcbc96bf5ac655a6275d93b47a86c89a157e3a128ebee786` |
| Separate prepared Composer lock SHA-256 | `14dbaef79b71cdf19a7ec44e70aa458f1fa22fbdb5d699bf1841fb03a99c8b49` |

The integration report records these identities, the reviewed configuration hash, and the prepared dependency-tree and separate Composer-lock hashes. A new build requires its own digest and toolchain review.

Verified on 2026-09-05: all 13 scenarios passed on Linux arm64 with Docker `29.6.1` and cgroup v2. The pinned real Codex binary used the deterministic local Responses fixture with the `gpt-5.2-codex` / `high` profile; there were zero paid requests. Generation and scoring ran unchanged application checks, the public scorer passed, and every case passed an independent check for zero remaining containers and volumes with its exact owner/run-ID labels. The retained report and 218 artifact hashes were independently checked. These results establish the stated infrastructure behavior, with the disk-observation limitation below.

## Fixed transport

[`relay.py`](relay.py) runs as the generation container's PID 1 with Docker network mode `none`. It binds only `127.0.0.1:8765` inside that namespace and accepts only bounded `POST /v1/responses` JSON requests. It forwards no candidate HTTP headers. The host controller receives requests through its attached Docker stdin/stdout transport, validates and reserves the fixed model's run quota, calls only the reviewed upstream operations, then returns bounded SSE bytes. The container receives no provider credential or host TCP route.

Before any Codex child starts, the trusted controller supplies one `start` frame containing its fixed arguments, minimal environment, and prompt. The relay creates empty private temporary homes, requires Linux `PR_SET_DUMPABLE=0` and verifies that result before launch. This prevents a same-UID candidate from opening the relay's `/proc` transport descriptors or memory. Same-UID signaling can still terminate the relay and fail the run. Neither relay requests nor Codex event claims are authoritative evidence of candidate behavior; the host proxy owns observed provider usage and Docker owns the lifecycle observations.

The relay serializes requests, bounds HTTP headers and bodies, rejects ambiguous framing, and bounds combined Codex output. At normal completion it reads cgroup `memory.events` and `pids.events` itself and reports bounded temporary-storage availability before the final exit frame. Missing observations fail closed. Container destruction remains the descendant-cleanup boundary, including descendants that create another process session.

Both phases provide bounded `/candidate/tmp` scratch for the unchanged starter process tests. The fixed storage allocation is 768 MiB candidate source storage, 112 MiB `/tmp`, 64 MiB `/candidate/tmp`, 64 MiB dependency tool cache, and 16 MiB shared memory, totaling 1 GiB. Temporary scratch is separate from the frozen source snapshot. Trusted preparation creates only its empty mountpoint; exporting source does not accept nested files in the underlying mountpoint.

Disk observations are final free-space snapshots. The disk fixture keeps dedicated scratch full until that snapshot. An earlier `ENOSPC` followed by freed space is not recorded as exhaustion; this occurred during verification when Codex removed its own temporary files at shutdown. The fixed filesystem capacities still apply throughout execution.

## Optional integration command

After preparing a reviewed configuration as described in the [controller README](../README.md), run:

```bash
python3 tools/test-agent-evaluation-controller-oci.py /absolute/path/to/reviewed-fixture-configuration.json --retain-evidence
```

The configuration uses the exact ordinary controller model/profile and image/dependency schema, with a synthetic one-run reference and `spending_ceiling_usd: "0.00"`. The fixed PHP test worker alone defines `AGENT_EVALUATION_CONTROLLER_OCI_TEST_UPSTREAM`, supplies an empty credential, and selects `127.0.0.1:18765`. [`fixture-upstream.py`](fixture-upstream.py) binds that exact loopback port and fails if it is occupied. It implements only the input-token count and Responses operations and never forwards a request. The ordinary `run` CLI cannot select this upstream.

The fixture supplies a reviewed `exec_command` call through the real Codex protocol, then a final assistant response. The command changes the task's three allowlisted files, proves the named containment denials, and runs unchanged `composer check` inside generation. Successful evidence requires the actual completed command's zero exit and final check marker. Scoring runs the application check and public scorer again against frozen source. The suite also exercises unlisted files, symlinks, file mode changes, signaling, cgroup PID and memory exhaustion, bounded temporary disk storage, shared model-token quota, controller interruption, and mutation denials during scoring. Separate lower-level adapter controls use a two-second wall limit and a 16 KiB output limit without altering the task manifest or emitting run records with different task budgets. Every negative control must reach its intended phase and the real Codex transport; resource controls additionally require their exact termination reason, so an unrelated protocol failure does not count as proof. The harness independently checks retained artifact hashes, the completed or failed phase record, absence of ambient environment sentinels, and disposable-workspace cleanup. These fixtures are deterministic infrastructure evidence, not a paid model run, hidden holdout, or comparative trial. The normal `composer check` continues to need neither these images nor an OCI engine.

Failed test evidence stays in its printed private temporary directory for review. `--retain-evidence` also keeps successful evidence and a JSON suite report at the generated private temporary root; it does not accept an arbitrary output destination. Without that flag, successful evidence is removed only after the controller's own cleanup and every test assertion have been verified. A human-reviewed live run retains the controller's approved evidence separately.

For every case, the harness also queries Docker independently using both retained owner and run-ID labels, the reviewed socket, and a fresh empty Docker configuration. Bounded read-only queries must find zero matching containers and volumes. Freeze controls require successful, verified mutation output before their exact rejection checks; scoring controls attempt new-file creation as well as existing-file writes. The escaped-session test requires its actual post-fork output. Optimized Python execution is rejected so these assertions cannot be silently disabled.
