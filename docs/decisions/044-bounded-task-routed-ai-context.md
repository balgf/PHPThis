# ADR 044: Bounded task-routed AI context

Status: accepted

## Context

PHPThis is an AI-first framework, but its maintainer, skeleton, and application-template entrypoints had accumulated several thousand words of optional-concern detail before an AI reached the task guide, source, or tests. The same mutable authoring rules appeared in root `AGENTS.md`, `.ai/rules.md`, task indexes, concern guides, the Consumer Contract, and historical decisions. That duplication increased context cost and allowed current prose to drift.

Issue #32 identified concrete contradictions: root final-class guidance allowed a non-final extension-point class that PHT002 rejects; one loop prohibition omitted `do` even though PHT003 covers it; a parser-value private-constructor rule appeared to govern identifiers whose application convention may choose another coherent visibility; CRUD guidance scaffolded absent Update and Delete operations; one application-context guide used a brittle ADR-number range; and some current route prose used untyped placeholders without identifying them as shorthand.

The existing vision also said that a simple endpoint should require at most four task-specific guide or code files without defining which endpoint qualified, which universal entrypoints were excluded, or which four files were counted. A measurable locality contract must not hide a concern merely to meet a number.

On 2026-08-08 in Asia/Manila, the accountable human approved Issue #32 and this bounded task-routing decision.

## Decision

### Current authority and historical rationale

Root `AGENTS.md` files are universal entrypoints. They own the authoring role, human decision boundary, authority order, read order, early database scope gate, project validity gate, context safety, and genuinely universal red lines. They do not restate complete optional session, cache, job, migration, WebSocket, file-transfer, Workbench, or other concern contracts.

Each `.ai/rules.md` contains only current rules that apply to every change in its repository or application. `.ai/README.md` is the task router. Each concern's current operational guide owns its mutable concern-specific authoring contract and routes to the concrete execution path and evidence. Durable rationale remains in `docs/decisions/`; an accepted ADR is not an additional always-read implementation manual.

For framework-maintainer work, the universal entrypoints are `AGENTS.md`, `VISION.md`, `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/strict-profile.md`. For application work, they are `AGENTS.md`, the installed Consumer Contract, the installed knowledge map, `.ai/README.md`, `.ai/rules.md`, `.ai/change-workflow.md`, and `.ai/project.md`. The early database setup gate may inspect only the prompt and already-current application facts before these entrypoints when unresolved scope must be clarified; after clarification, the ordinary read order resumes.

Concern-specific rules live in the current guide routed by `.ai/README.md`; do not copy them into this universal entrypoint.

Ordinary implementation starts with one current operational guide. Read an ADR only when reviewing or changing the decision it records; do not load historical ADRs merely to apply the current guide.

### Exact simple-endpoint locality

A simple endpoint is an unprotected route on one exact literal path that fits an existing named route-area manifest, uses a dependency-free handler, accepts no application-owned body or path parameters, performs no database, session, server-side cache, process-configuration, request-handler-decorator, or external I/O work, and requires no new product, architecture, security, data, release, or operational decision.

Query, form, or application-owned header input also disqualifies the endpoint because it requires its own boundary contract. The external-I/O exclusion applies to endpoint-owned work; an application's already-adopted outer request boundary and terminal request-summary path remain mandatory. A route also leaves the simple category as soon as it enters another concern or needs an unresolved consequential decision.

After universal entrypoints, a simple-endpoint change has exactly four task-specific files: one current operational guide, the existing named route-area manifest, the dependency-free handler, and the nearest behavior test.

The count is the number of distinct task-specific files inspected or authored after the universal entrypoints. The existing named route-area manifest visibly constructs the dependency-free handler inline in the exact `Route` declaration. The root `Routes::create()` already includes that route area and remains unchanged. The nearest behavior test is the one application-owned test file that proves the endpoint's observable success and applicable route/method failure behavior. Focused commands, the final complete project check, generated reports, and unchanged tool configuration are verification activity rather than additional authoring-context files.

If an endpoint needs a new route area, changes root route composition, gives its handler any constructor dependency, needs another guide or code boundary, or splits its nearest evidence across files, the task is not reported as satisfying this simple-endpoint metric. Dependency-bearing handlers retain ordinary visible construction in the root and are passed into their named route-area manifest. The task follows the broader routed concern contract instead; an AI must not skip required context, policy, or evidence to preserve the count.

### Reconciled universal wording

Current authoring surfaces use these rules consistently:

- Every named class is final. Express extension points with interfaces, never non-final classes.
- Never execute a database call inside `for`, `foreach`, `while`, `do`, or recursive traversal.
- An operation-specific request, command, or projection parsed from external `mixed` uses a private constructor. This requirement does not set identifier constructor visibility; an application-owned identifier follows its recorded coherent convention.
- Current route declarations use exact typed placeholders. A bare placeholder may appear only when the surrounding prose explicitly labels it human-readable shorthand and also identifies the exact typed declaration.
- The current CRUD guide owns one canonical reference tree. An operation that is not implemented is described as absent; guidance does not scaffold an empty file merely to complete a hypothetical Create, Read, Update, and Delete set.
- Current scope statements name the behavior they cover instead of relying on an ADR-number range that becomes stale when another decision is accepted.

These are current-contract reconciliations, not edits to immutable ADR history. Consumer Contract version 10 and Strict Profile version 3 remain unchanged.

### Advisory boundary

A report-only context-size or repeated-rule advisory was considered and is not adopted. Word and token counts are tool-dependent, deliberate application-owned safety repetition may be necessary, and neither determines program validity. Human review remains responsible for whether task routes stay compact and unambiguous. No context report script, `ApplicationChecker` rule, `PHT` diagnostic, or consumer-size validity gate is added.

## Consequences

An AI can distinguish universal authority from concern-specific implementation rules and historical rationale. Ordinary work begins from one current guide, while a narrowly defined simple endpoint has one exact four-file task-specific budget. Optional-concern safety remains owned by its routed guide instead of disappearing with duplicated entrypoint prose.

Maintainers must keep routers accurate and resist copying a new concern contract back into universal files. A complex task can still require many files; the locality metric exposes that scope rather than rewarding skipped evidence. Applications whose route composition cannot fit the exact simple shape do not claim the metric until their own explicit structure supports it.

No runtime API, dependency, automatic discovery, generated policy, consumer validity diagnostic, Consumer Contract version bump, or Strict Profile version bump is introduced.

## Reconsider when

Independent consumer work shows that a qualifying endpoint cannot be implemented safely through one operational guide, one existing named route-area manifest, one dependency-free handler, and one behavior-test file; universal entrypoints again acquire concern-specific detail; or repeated human review finds material routing drift that narrow source-distribution evidence cannot catch without creating false authority. Reconsider the smallest measurable routing contract, not a hidden context loader or a weaker safety rule.
