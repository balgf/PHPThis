# Issue 70: first observed context route

This maintainer-only record describes one approved implementation attempt on 2026-09-16. It is a precise derivative of seed 1 in `docs/ai-context-routing-review.md`, using the unchanged `change.simple-ping` revision 26 prompt. It is not an explanation task, an exact replay of the shorter seed, or a comparison. The other nine seeds remain unmeasured.

## Frozen inputs and evidence

| Input | Identity |
| --- | --- |
| Framework and source skeleton | `2ea439c7dc83d0c6041bdaec37114083020fe580` |
| Run | `eab6c33941d140f786fdcc3217bfcd04` |
| Model / runner | `gpt-5.4-2026-03-05`, high reasoning / Codex 0.153.1 |
| Limits | 40,000 cumulative input/output tokens; 1,200 seconds; one invocation; zero post-score repair turns |
| Source prompt SHA-256 | `e6f816c782311ded0c06a67ed2c95048c7d4689c32cd78ddfaa17ea52a5a9952` |
| Effective prompt SHA-256 | `013f230930c1f8a452844b2e7d45f20e44198543c783b6887f583c155fad6335` |
| Prepared dependency manifest SHA-256 | `d7f51a335660af969f23a4dd70eb933399481375c74817321d4d684b7aba2f4b` |
| Retained evidence manifest SHA-256 | `8b00e0715cb6db08029108688a7a2b4bc1a2f05f1c84c2c525abb3f2b04dbc63` |

The fixed sibling `agent-evaluation-runs/<run-id>/evidence/` retains the ordinary controller artifacts. The sibling preparation directory `agent-evaluation-preparation/issue-70-context-v1/` retains the approval packet, exact image/toolchain/lock identities, full source snapshot, available-context inventory, no-key preflight and unchanged application baseline checks, and the separate `observed-context-review.md` and `observed-context-metrics.json`. These local artifacts are not shipped to consumers or supplied as additional candidate context. Preserve them with their frozen implementation when reviewing this historical run; later task revisions do not revalidate it as a new trial.

## Outcome

Generation stopped after 16.898 seconds with `model_token_limit`. Two provider requests settled **25,157 input and 985 output tokens**, totaling **26,142**. Input includes 5,632 cached tokens; output includes 315 reasoning tokens. The third observed request was refused before Responses creation because its counted input could not leave the minimum 16 output tokens within the remaining 13,858-token allowance. Its exact input count was not retained. This is not a claim that 40,000 tokens were consumed.

There are no unsettled reservations. At the [standard prices verified on 2026-09-16](https://developers.openai.com/api/docs/pricing), the estimated token charge is **USD 0.0649955**: uncached input at USD 2.50/M, cached input at USD 0.25/M, and output at USD 15/M. This is not an invoice or account-balance observation. The approved conservative bound was USD 0.60; the smoke proxy enforces token reservations rather than a separate dollar meter. No retry was executed.

Only preparation, generation and cleanup completed. All recorded shell commands were reads. No frozen patch, candidate application check, public-scorer result, or score record exists; implementation correctness remains **unscored**. The successful pre-run application baseline is not candidate success. All 20 manifest-listed artifacts matched their hashes and approved inputs on independent review. Cleanup verified zero remaining owned containers or volumes. The proxy-derived `external-actions.approved=false` reflects budget blocking and does not independently establish an unauthorized action.

## Observed route

The following is retained command-event order. `installed` means `vendor/phpthis/framework/`. Every path was read once; each retained output exactly matches its requested source excerpt. These are shell-output observations, not proof of provider delivery or model attention.

| Event line | File | Requested / returned lines | Returned bytes |
| ---: | --- | --- | ---: |
| 5 | `.ai/project.md` | 1–260 / all 28 | 1,165 |
| 7 | installed `docs/consumer-contract.md` | 1–220 / all 183 | 27,373 |
| 9 | `.ai/README.md` | 1–220 / all 64 | 9,330 |
| 11 | `.ai/rules.md` | 1–260 / all 31 | 3,223 |
| 13 | installed `docs/knowledge-map.md` | 1–260 / all 77 | 14,859 |
| 15 | `.ai/change-workflow.md` | 1–260 / all 44 | 5,287 |
| 18 | installed `docs/request-handling.md` | 1–260 / all 213 | 38,217 |
| 20 | `.ai/testing.md` | 1–240 / all 68 | 31,128 |
| 22 | `src/HealthRoutes.php` | 1–240 / all 16 | 259 |
| 24 | `src/Routes.php` | 1–240 / all 16 | 230 |
| 26 | `tests/run.php` | 1–260 / 260 of 445 | 9,768 |

The six explicit universal reads returned 7,976 words / 61,237 bytes. The available seven-file consumer universal inventory was 8,554 words / 65,427 bytes; it also includes `AGENTS.md`, whose automatic client loading is unknown here. Explicit read order differs from the prescribed sequence, but lack of an explicit entrypoint read does not prove its instructions were absent. The six-file maintainer universal inventory was separately measured at 4,768 words / 35,421 bytes and was not supplied to the candidate. Counts use `LC_ALL=C /usr/bin/wc -w -c`; the preparation records ordered source hashes, installed-byte parity and the exact tool identity. None of these sizes is a token estimate.

Five task-specific files were inspected, returning 9,598 words / 79,602 bytes. Relative to the expected authoring set, the extras were the testing guide and root route composition; the new handler was not authored. This is not a completed four-file result. The first current concern guide was correct. No question, edit, repair or unsupported completion claim was observed; completed concern coverage and final-answer correctness remain unscored. The last assistant message is an intermediate status update.

## Supported repair and limits

The frozen application router contains overlapping instructions: the qualifying simple-endpoint row includes its nearest behavior test, while the unqualified “Add or change tests” row also selects `.ai/testing.md`. The agent identified the task as simple, announced it would read test guidance, and followed both routes. This supports clarifying the testing row's precedence in the application template and skeleton. Only routine behavior tests for a qualifying simple endpoint stay on that dedicated route; other test changes, including ancillary tests for non-simple features, keep their testing owner. Universal authority, automated behavior evidence and the complete gate remain mandatory.

The root-composition read is an observed extra inspection, but the existing route already says root composition remains unchanged. No contradictory owner was identified for a second repair. The run does not establish that either extra read caused the budget stop. Event outputs may differ from what the client supplied upstream; the third create was refused, and full provider requests are not retained. No transcript, per-file token attribution, complete compliance result or model-performance conclusion can be reconstructed from these artifacts.

The before/after written-route review uses these representative requests:

| Request | Frozen baseline | Clarified route |
| --- | --- | --- |
| Add a qualifying dependency-free endpoint and its behavior tests | Endpoint row and unqualified testing row both match | Endpoint row owns routine evidence; the nearest behavior test remains in the four-file set |
| Add regression tests as the primary task, including tests for an existing simple endpoint | Testing row | Testing row; the endpoint-implementation exception does not apply |
| Add a query-accepting endpoint and its behavior tests | Request-handling and testing concerns both entered | Both concerns remain entered; the routine simple-endpoint exception does not apply |
| Add an endpoint and change shared test support or runner organization | Testing concern separately entered | Testing concern remains separately entered; the whole task cannot claim the simple four-file bound |

These checks establish written precedence only; source guards and installed-consumer proofs separately check distribution coherence. The clarification adds 38 words / 288 bytes to each application router, making the default-consumer universal inventory 8,592 words / 65,715 bytes. It is not a measured context-size or performance reduction. The changed source skeleton requires smoke revision 27 with refreshed fixture/tree/manifest pins; the prompt, rubric, policy, budgets and comparison fixtures remain unchanged.

No after-change model trial, explanation trial or repeated matched comparison has been approved or performed. Issue 70 therefore remains incomplete. This record introduces no context-report command, context-size validity rule, diagnostic, automatic discovery or revised ADR 044/058 decision.

## Later observation (2026-09-24)

A separately approved after-change revision-27 trial later ran against the clarified route. Its retained reads omitted `.ai/testing.md`, but generation again stopped at the 40,000-token reservation boundary before a patch or score. The sibling `agent-evaluation-preparation/issue-70-route-after-v1/` packet records its ordered reads, bounded usage, cleanup, and review; one incomplete before/after pair does not establish a general improvement or task correctness. Separate explanation trials are retained under their pinned revisions. This dated note updates the issue evidence without rewriting the original frozen observation above.
