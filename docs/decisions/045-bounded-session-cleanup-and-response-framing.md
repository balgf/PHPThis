# ADR 045: Bounded session cleanup and response framing

Status: accepted

## Context

The native-file session boundary already treats session work as an explicit, lazy lifecycle, but cleanup after a failed mutation or request finalization could obscure the failure that caused cleanup to run. A second failure during native close, destruction, or state reset must not silently replace the original application or session failure, nor leave the lifecycle in a reusable partial state.

`Response` also accepted some contradictory ordinary-response framings that the emitter could not safely repair: final informational statuses, `Transfer-Encoding`, a declared `Content-Length` inconsistent with the supplied body, and body or length metadata on statuses that do not carry a representation. Local-file responses already had a narrower explicit framing boundary.

On 2026-08-08 in Asia/Manila, the accountable human approved this bounded correction without a logger, retry path, response-emitter request knowledge, or new framework service abstraction.

## Decision

### Session cleanup failure precedence

`SessionLifecycle` makes a bounded cleanup attempt for every active or unissued native state on `update()`, `regenerateAndUpdate()`, `invalidate()`, `finish()`, `abort()`, and destruction of never-emitted state. Failed update or pre-regeneration callback cleanup preserves the begun request and any earlier pending cookie or unissued state that remains coherent. Once native regeneration has started, cleanup clears local ownership and pending-cookie state and makes the bounded ordered identifier-discard attempts rather than emitting stale state. Failed invalidation cleanup likewise clears live pending-cookie ownership before it escapes. A failed discard can leave non-issued or obsolete native storage for the configured cleanup policy. `finish()` and `abort()` are terminal and reset local request state even when cleanup fails. Cleanup follows prerequisite order; it does not retry or attempt an unsafe dependent action after its prerequisite fails.

When an operation has an original failure and cleanup succeeds, PHPThis rethrows that exact original `Throwable` instance. When cleanup also fails, it throws the narrow redacted `SessionCleanupFailed` failure, retaining both the original failure and cleanup failure for framework-level diagnosis without exposing native session identifiers, paths, state values, or exception messages in its public text. An explicit `abort()` with no earlier failure reports its cleanup failure directly. PHPThis neither logs, retries, suppresses, nor converts these failures into a response inside the lifecycle.

`RequestBoundary` retains its existing responsibility: known application failures select their registered response, unknown failures trigger session abort before escaping, and normal or registered responses pass through session finalization. A `SessionCleanupFailed` aggregate is deliberately excluded from registered response mapping and escapes through the generic redacted unknown-failure path; cleanup failure cannot select an unrelated public response after a known response was chosen. The lifecycle does not make a successful session mutation atomic with earlier database or external work.

Framework evidence injects native-session cleanup faults through the existing isolated test boundary. It proves the original failure identity when cleanup succeeds; deterministic retention and redaction when both failures occur; coherent begun-request and earlier pending state after failed update or regeneration cleanup; invalidation commit-failure precedence without a stale live cookie; terminal reset after finish or abort; no retry after cleanup failure; and normal finalization of a registered original response after successful operation cleanup. This adds no production fault-injection seam.

### Ordinary response framing

`Response` accepts final response statuses from `200` through `599`; it rejects final informational `1xx` statuses. It rejects every `Transfer-Encoding` ordinary header. An ordinary content-bearing response may omit `Content-Length`; when supplied, it must be the canonical decimal string equal to `strlen($body)`. A `204`, `205`, or `304` response has an empty ordinary body and no `Content-Length`.

The existing `LocalFileBody` contract remains stronger: its ordinary body is empty, its status and framing restrictions remain explicit, and it carries exact file bytes through the sole fixed-chunk emitter path. This decision does not add range support, protocol switching, callbacks, streamed ordinary bodies, or automatic header injection.

`HEAD` remains application-owned and explicit. An application declares its own `HEAD` route and returns an empty body without `Content-Length` under this current safe subset. PHPThis does not fall back from `HEAD` to `GET`, suppress a `GET` body, give `ResponseEmitter` a request, or infer a representation length. Such behavior requires a future explicit decision with representation-equivalence and cache evidence.

Framework evidence covers accepted omitted and exact ordinary lengths, rejected final informational status, transfer encoding, mismatched length, and prohibited bodyless-status framing, while preserving local-file behavior. Applications test the exact status, body, and headers their routes select.

Consumer Contract version 11 carries version 10 forward and adopts these runtime construction constraints. Strict Profile version 3 remains unchanged: this decision adds neither accepted-PHP syntax nor a diagnostic, dependency, logger, middleware, service, permission mechanism, ORM, or generic response/session helper.

## Consequences

Session failures remain diagnosable without converting cleanup into an unbounded recovery mechanism, erasing the causative failure, or exposing the aggregate as a registered client failure. The aggregate retains the existing generic external path.

Applications constructing contradictory ordinary responses now fail at construction rather than emitting an ambiguous result. A previous final `1xx`, `Transfer-Encoding`, mismatched ordinary length, or `204`/`205`/`304` body or length must be replaced with one explicit supported response. An application that needs protocol-specific `HEAD`, `304`, or streaming semantics records and proves a separate decision rather than relying on implicit emitter behavior.

## Reconsider when

Independent consumer evidence requires a smaller safe `HEAD` representation rule, a specific protocol switch, or a response-body type with its own explicit emission and failure contract. Reconsider one narrow response type or protocol rule at a time. Do not solve it with request-aware emission, automatic fallback, a generic stream abstraction, or hidden session recovery.
