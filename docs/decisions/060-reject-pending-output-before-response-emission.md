# ADR 060: Reject pending output before response emission

Status: accepted

## Context

`ResponseEmitter::emit()` rejected only `headers_sent()`. PHP output buffering can retain earlier bytes while that function still reports `false`, so the emitter could then send status and headers and append an ordinary or local-file body whose declared framing did not include those pending bytes. A nested empty top buffer could also hide bytes retained at a lower active level.

The emitter cannot safely discard, rewrite, or absorb unknown earlier output into an already selected immutable response. Doing so would transfer application buffer ownership into framework code, conceal the source of the violation, and still not establish correct status, header, cookie, or body semantics.

On 2026-08-25 in Asia/Manila, the accountable human directed implementation of Issue #62's fail-closed pending-output policy for ordinary and local-file responses. This accepts Consumer Contract version 17 for the local source patch. It authorizes no commit, push, issue closure, tag, package, GitHub release, announcement, or other publication operation.

## Decision

At the start of every `ResponseEmitter::emit()` call, before selecting the ordinary or local-file path, PHPThis rejects emission as `ResponseEmissionFailed(true)` when either:

- `headers_sent()` reports that PHP has already committed headers; or
- `ob_get_status(true)` reports a non-zero `buffer_used` value for any active PHP-managed output-buffer level.

No active buffer, one empty active buffer, and nested active buffers whose every level is empty remain valid infrastructure. The all-level check means an empty top buffer cannot hide pending bytes below it.

The emitter only inspects the entry state. It does not clean, flush, close, reorder, rewrite, copy, or incorporate application-owned buffers or prior bytes. Rejection occurs before file access, response status, ordinary headers, separate `Set-Cookie` fields, ordinary body output, or local-file bytes. The same rule therefore applies to both response paths and a `true` failure prevents the visible front controller from selecting its pre-header replacement response.

This is a snapshot of the state PHP reports at entry, not an output transaction or synchronization mechanism. PHPThis does not claim to observe private state retained by a custom output handler, bytes written after the check, native output outside PHP's managed buffers, or buffering and delivery in the SAPI, web server, reverse proxy, compression layer, TLS terminator, kernel, network, or client.

## Compatibility migration

Consumer Contract version 17 carries version 16 and Strict Profile version 4 forward with permanent diagnostics `PHT001` through `PHT008`. It rejects an emission call that Contract version 16 allowed to proceed when PHP-managed output buffers already contained bytes.

Before adopting Contract version 17, an application must:

1. Inventory every ordinary and local-file `ResponseEmitter::emit()` call and every include, bootstrap, debug statement, template, warning-display setting, output handler, and buffer that can run before it.
2. Remove unintended early output at its owner. Do not clear, discard, rewrite, or fold those bytes into the selected response as an upgrade workaround.
3. Keep any intentional capture or infrastructure buffers empty when emission begins, and add real PHP-buffer evidence for empty success, top-level pending bytes, and lower-level pending bytes below an empty top buffer wherever those shapes apply.
4. Verify the visible front controller does not attempt a fallback for `ResponseEmissionFailed(true)`, record custom-handler and deployed-SAPI limitations, and run the complete application gate on PHP 8.4.x.

A later prerelease containing this decision must publish the newly rejected emission state and migration as an intentional compatibility break. This decision selects no release identity and authorizes no external publication operation.

## Evidence and limits

The isolated framework proof uses real PHP output buffers while the namespaced `headers_sent()` control remains false. It proves ordinary rejection with pending top-level bytes and a cookie-bearing response; local-file rejection with pending lower-level bytes below an empty top buffer; exact preservation of prior bytes and buffer depth; no status, header, cookie, body, file-byte, or file-open work; ordinary success through one empty buffer; and local-file success through nested empty buffers. Existing response framing, cookie, file verification, fixed-chunk output, pre-header failure, and already-sent-header evidence remains in force.

The installed-consumer proof independently executes the packaged ordinary and local-file emitter paths through real PHP buffers and rereads the packaged contract, guidance, decision, and source. The existing real-SAPI file-transfer proof does not inject early output or certify custom output-handler internals, concurrent output, deployed buffering, or delivery. Those limits remain application and deployment evidence.

Because the current starter authority advances to Consumer Contract version 17, the maintainer-only `change.simple-ping` evaluation task advances from revision 24 to revision 25 and pins the resulting source-skeleton Git tree and fixture digest. Its prompt, rubric, scorer, workspace policy, budgets, and non-comparative boundary remain unchanged. This fixture maintenance records no model result and makes no comparative claim.

## Consequences

The emitter now fails before it can append a response to buffered prior bytes, so its declared framing is not knowingly contradicted by PHP-managed pending output. Empty capture and infrastructure buffers continue to support tests and deliberate deployment composition.

Applications must fix early output at its source. `ResponseEmissionFailed(true)` is deliberately conservative even when PHP has not transmitted the pending bytes: the selected response can no longer own the complete output, so a replacement would be equally ambiguous.

The implementation adds no public type, method, dependency, configuration switch, middleware, streaming abstraction, buffer owner, Strict Profile rule, `PHT` diagnostic, or core line. The core remains 2,618 physical lines under the accepted 2,620-line ceiling; the remaining two lines authorize no adjacent mechanism.

## Reconsider when

A supported SAPI cannot expose reliable all-level byte counts, PHP changes the documented output-buffer status shape, or a separately accepted response type needs an explicit streaming or output-ownership protocol. Reconsider that concrete boundary with representative SAPI evidence rather than adding automatic cleanup, hidden fallback, request-aware emission, or a generic output-buffer abstraction.
