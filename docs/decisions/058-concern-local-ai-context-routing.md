# ADR 058: Concern-local AI context routing

Status: accepted

## Context

ADR 044 established universal authority and safety entrypoints, one current operational owner per concern, conditional decision history, and an exact four-file task-specific metric for a qualifying simple endpoint. It also rejected a context-size report or validity mechanism because counts alone do not establish clear routing, correct claims, or safe implementation.

The reconsideration condition in ADR 044 has now been reached. At framework revision `c00d78d1fc8789f8b938ea0af78466437787fa55`, the mandatory application read floor combines a long current Consumer Contract, a detailed knowledge-map checklist, and the starter's project-owned universal entrypoints. The Consumer Contract also includes every earlier upgrade narrative. The maintainer `.ai/application-context.md` duplicates current configuration, jobs, migrations, file-transfer, WebSocket, launcher, probe, coordination, email, release, and other concern rules despite dedicated concern owners.

This growth obscures the distinction between authority that applies to every task, current rules for one selected concern, and history needed only for an upgrade or decision review. Removing text without preserving its normative owner would weaken the framework. Measuring fewer bytes without checking route clarity and unsupported claims would not prove an improvement.

On 2026-08-24 in Asia/Manila, the accountable human approved Issue #59 and this concern-local routing decision. The approval accepts the context-ownership architecture only. It authorizes no commit, tag, package, release, announcement, or other publication operation.

## Decision

### Universal authority remains mandatory

ADR 044's universal authority, human-accountability, safety, scope, validity, and universal-red-line boundaries remain accepted. Framework-maintainer work still begins with `AGENTS.md`, `VISION.md`, `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/strict-profile.md`. Application work still begins with project `AGENTS.md`, the installed current Consumer Contract and knowledge map, `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`.

Universal files state only rules required to select and conduct every task safely. They may point to a concern owner, but they do not carry that concern's complete operating contract. A concern is loaded when the task enters it; a cross-concern task follows every entered route. No context budget permits skipping required policy, source, tests, or evidence.

### Current contract, upgrade history, and concern owners are separate

`docs/consumer-contract.md` remains the installed normative universal contract. It owns current authority and read order, AI accountability, program validity, universal behavior-evidence expectations, universal safety and red lines, the mandatory application-context inventory, and a compact normative route to each current concern owner.

Current optional or concern-specific rules are owned by the applicable installed configuration, HTTP/request, WebSocket, file-transfer, observability, jobs, CLI, coordination, Workbench, migrations, request-policy, session, CRUD, database, security, static-analysis, and other concern guides. Moving a rule changes its location, not its force. Unique requirements are transferred to the concern owner before they are removed from the universal contract; duplicated wording is removed rather than retained under two mutable owners.

`docs/consumer-contract-upgrades.md` owns the complete Contract version 1 through version 15 migration and historical narrative. The current contract identifies Contract version 15 and Strict Profile version 4 and routes an application upgrade or historical review to that document. Ordinary implementation does not load it. Contract version 15 and Strict Profile version 4 remain unchanged because this decision accepts no different application program or PHP subset.

`docs/knowledge-map.md` remains the installed public task router. Each row names the first current guide and only the shortest conditional additions needed to select another concern. Detailed implementation and review checklists live in their concern guides. An ordinary route does not require an ADR merely to apply its accepted current rule; decision records are loaded when their decision is reviewed, changed, or needed for historical or release-specific evidence.

### Maintainer routing follows the same ownership model

`.ai/application-context.md` owns only contract/map/template/skeleton routing and cross-artifact coherence: maintainer-versus-application authority, portable installed artifacts, verified placeholders and current facts, template-versus-runnable-starter separation, package and link coherence, consumer proof, and the human decision boundary.

Existing maintainer concern guides own jobs, migrations, file transfers and Amazon S3, WebSockets, HTTP and input, request policy, sessions, cache, observability, CLI, Workbench, CRUD, database, static analysis, testing, and the other concerns already routed to them. Narrow maintainer configuration, operations, and email guides close the current owner gaps. Release state and procedure remain with `RELEASING.md`. A concern-specific skeleton or template change starts with that concern's guide; application-context is added only when the change also enters shared inventory, routing, or cross-artifact coherence.

### The simple-endpoint metric reports both layers honestly

ADR 044's qualifying simple-endpoint definition and exactly four task-specific files remain unchanged: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test. The root route composition remains unchanged.

Any report of that metric also reports the universal read cost separately, with the exact revision, ordered file inventory, word method, and byte method. The four task-specific files are never described as total context. Universal files do not count toward the four task-specific files, but neither their cost nor their mandatory authority is hidden. Commands and generated verification output remain evidence activity rather than additional authoring-context files.

### Measurements are reproducible evidence, not validity

The accepted measurement method is informational. Run from the repository root with `LC_ALL=C`; count words and bytes with the platform `wc -w -c` implementation; record the exact revision and ordered paths; and keep universal and task-specific groups separate. Cross-machine word counts may differ if the `wc` implementation differs, so the implementation and raw output are part of any comparison. Bytes are the stable file-byte total.

The before inventory at `c00d78d1fc8789f8b938ea0af78466437787fa55` is:

| Group | Exact ordered inventory | Words | Bytes |
| --- | --- | ---: | ---: |
| Maintainer universal | `AGENTS.md`, `VISION.md`, `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, `.ai/strict-profile.md` | 4,660 | 34,961 |
| Default starter universal | `docs/consumer-contract.md`, `docs/knowledge-map.md`, `skeleton/AGENTS.md`, `skeleton/.ai/README.md`, `skeleton/.ai/rules.md`, `skeleton/.ai/change-workflow.md`, `skeleton/.ai/project.md` | 20,179 | 158,507 |
| Current Consumer Contract | `docs/consumer-contract.md` | 13,717 | 106,389 |
| Installed knowledge map | `docs/knowledge-map.md` | 3,607 | 30,366 |
| Maintainer application-context task guide | `.ai/application-context.md` | 4,463 | 37,707 |

The accepted implementation, measured with the same command and inventories, records:

| Group | Words after | Bytes after | Word delta | Byte delta |
| --- | ---: | ---: | ---: | ---: |
| Maintainer universal | 4,648 | 34,450 | -12 | -511 |
| Default starter universal | 7,658 | 58,141 | -12,521 | -100,366 |
| Current Consumer Contract | 2,778 | 21,198 | -10,939 | -85,191 |
| Installed knowledge map | 1,940 | 14,496 | -1,667 | -15,870 |
| Maintainer application-context task guide | 638 | 4,829 | -3,825 | -32,878 |

Conditional and concern-specific files remain outside those universal totals and are reported separately:

| Conditional or task-specific file | Words | Bytes |
| --- | ---: | ---: |
| `docs/consumer-contract-upgrades.md` | 3,370 | 26,211 |
| `.ai/configuration.md` | 526 | 4,476 |
| `.ai/operations.md` | 638 | 5,089 |
| `.ai/email.md` | 257 | 2,094 |
| `docs/ai-context-routing-review.md` | 886 | 6,888 |

No maximum, score, trend threshold, warning, Composer failure, application-checker rule, generated report, automatic discovery, `PHT` diagnostic, or consumer validity effect follows from these numbers. A later report or advisory must explicitly revisit ADR 044 and this decision rather than quietly turning size into authority.

### Route clarity and unsupported claims require semantic review

Byte reduction is not route proof. The implementation includes one frozen bounded review covering a qualifying simple endpoint, contract upgrade, configuration, local launcher and probes, durable jobs, migrations, local and Amazon S3 file transfers, WebSockets, email, and one mixed-concern request. For every prompt, the review records the expected first owner, permitted conditional additions, forbidden unrelated context, the actual route, and unsupported-claim findings.

The [bounded AI-context routing review](../ai-context-routing-review.md) rejects invented framework runtime, automatic loading or discovery, generated policy, generic queue/storage/authentication/coordination abstractions, static-guidance-as-production-proof, skipped safety or evidence, weakened Strict Profile requirements, and a proposal presented as installed behavior. It is a bounded routing review, not a model comparison or proof that every model follows the router. Existing source-distribution and consumer checks prove artifact presence and coherence only.

## Consequences

The mandatory floor becomes smaller because upgrade history and current optional concern contracts are no longer always read. The application-context umbrella becomes a narrow coherence guide. A task that enters several concerns may still require several guides; the router exposes that cost rather than hiding it.

Maintainers must preserve one current normative owner when moving future rules, keep the current contract's incorporation routes accurate, package upgrade history, and update template, skeleton, guardrail, and installed-consumer evidence together. Historical release and decision wording remains immutable.

Legacy exact-marker checks against the former umbrella files retire only through reviewed path-and-marker pairs, gated by this accepted decision and its transfer-to-current-owner invariant. Source and installed-consumer controls prove that a known retired pair is accepted and an unlisted current marker still fails.

Because the installed starter router changed, the maintainer-only `change.simple-ping` evaluation task advances from revision 21 to revision 22 and pins the resulting source-skeleton Git tree and fixture digest. Its prompt, rubric, scorer, workspace policy, budgets, and non-comparative boundary are unchanged. This fixture maintenance records no model result and makes no comparative claim.

This decision adds no runtime API, dependency, automatic context loader, filesystem discovery, generated policy, checker rule, `PHT` diagnostic, accepted-PHP change, Contract version, Strict Profile version, or production-readiness claim.

## Reconsider when

A universal safety or authority rule cannot be applied without loading a concern guide; consumers repeatedly cannot select the correct first owner; semantic route review finds material unsupported claims or skipped concern evidence; the simple-endpoint route needs more than its accepted four task-specific files; or concern guides again accumulate into a second umbrella. Reconsider ownership and route evidence first, not a hidden loader or a byte threshold.
