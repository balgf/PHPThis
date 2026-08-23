# ADR 056: Bounded request-target and path bytes

Status: accepted

## Context

`RequestReader` already bounds `REQUEST_URI`, rejects a fragment marker, splits the query suffix at the first `?`, and requires the remaining path to be absolute. Direct `Request` and `Route` construction already require absolute path-only values. None of those boundaries rejected every raw ASCII control byte, DEL, or a raw space, so direct construction could retain those bytes and a raw control or space in the query suffix could escape the path-only checks after the split.

An HTTP server or proxy may reject or transform some of these representations, but PHPThis cannot make that deployment behavior its only application boundary. The same invariant must hold for alternate SAPIs, direct framework construction, tests, and any ingress that preserves a raw target. The correction must remain byte-exact: it must not introduce URL decoding, Unicode interpretation, slash or dot-segment normalization, backslash rewriting, or a broader character allowlist.

This correction rejects request and route constructions accepted by the current public API. It therefore requires a new Consumer Contract version and explicit migration rather than a wording-only clarification.

On 2026-08-23 in Asia/Manila, the accountable human directed PHPThis to implement Issue #56 and thereby accepted this bounded correction, its exact compatibility migration, and Consumer Contract version 14. This approval accepts the framework boundary only. It keeps Strict Profile version 3, permanent diagnostics `PHT001` through `PHT007`, and the 2,618-line core under the accepted 2,620-line ceiling unchanged; it selects no release identity and authorizes no tag, package, release, or announcement operation.

## Decision

PHPThis adds one exact raw-byte exclusion to its existing request-target and path rules: bytes `0x00` through `0x20`, inclusive, and byte `0x7F` are invalid. The exclusion is evaluated on bytes as supplied. It does not decode or otherwise reinterpret the input.

`RequestReader` validates the complete string-valued `REQUEST_URI` before finding the first `?` and separating the query suffix. The complete request target, including any query suffix, remains limited to 8,192 bytes, contains no `#`, and contains none of the excluded raw bytes. A target that violates any of those representation rules fails with the fixed non-disclosing message:

```text
REQUEST_URI has an invalid or oversized request-target representation.
```

Only after that validation does `RequestReader` split at the first `?`. The resulting path remains non-empty and begins with `/`. Validation occurs before query, header, upload, or body parsing and therefore before routing or handler work.

A directly constructed `Request` receives a path-only value. It remains non-empty, begins with `/`, contains no `?` or `#`, and contains none of the excluded raw bytes. A violation fails with the fixed message:

```text
Request path must be absolute and contain no query, fragment, raw space, control, or DEL byte.
```

A `Route` declaration applies the same path-only representation rules before segment parsing. A violation fails with the fixed message:

```text
Route path must be absolute and contain no query, fragment, raw space, control, or DEL byte.
```

The new exclusion deliberately leaves bytes `0x21` through `0x7E` other than the separately forbidden `#` and path-only `?`, plus bytes `0x80` through `0xFF`, outside this decision. Percent signs and percent-encoded spellings remain raw bytes. Slashes, repeated slashes, dot segments, backslashes, and high bytes are neither rewritten nor newly rejected. Existing route literal matching, placeholder grammars, precedence, overlap detection, bounds, and unchanged-byte delivery remain in force.

Consumer Contract version 14 carries version 13 forward with this compatibility change. The decision adds no Strict Profile rule, `PHT` diagnostic, checker rule, decoder, normalizer, redirect, alternate request boundary, runtime dependency, route type, or framework policy for a server or proxy.

## Compatibility migration

Before adopting Contract version 14, an application must:

1. Inventory direct `Request` construction, explicit `Route` declarations, alternate SAPI adapters, proxy or server handoff, and tests or fixtures that supply `REQUEST_URI`.
2. Remove every raw byte from `0x00` through `0x20` and every raw `0x7F` from those targets and paths. Where an external protocol deliberately uses a percent-encoded spelling, keep that spelling explicit and do not expect PHPThis to decode it.
3. Confirm that no route or handler depends on trimming, decoding, slash collapsing, dot-segment resolution, backslash rewriting, Unicode normalization, or another repair of a rejected representation.
4. Add adversarial evidence for each excluded-byte boundary in a path and in a query suffix, direct `Request` and `Route` construction, fixed non-disclosing diagnostics, and zero routing or handler work after reader rejection. Retain ordinary-path, raw-percent, slash, dot, backslash, and applicable high-byte evidence.
5. Verify the deployed server and proxy path separately when their treatment of raw request targets is material, then run the complete application gate on PHP 8.4.x.

A later prerelease that includes this decision must name the newly rejected representations and the migration above as an intentional prerelease compatibility break. This decision does not select or authorize a release identity, candidate commit, tag, package update, GitHub release, or announcement.

## Consequences

The framework rejects ambiguous or log-disrupting raw control, whitespace, and DEL bytes at every public request-target or path construction boundary with fixed messages that do not echo attacker-controlled input. A rejected runtime target stops before later request parsing and all routing and handler work.

Some direct tests, alternate ingress adapters, synthetic routes, or clients that supplied a raw space or control byte must change. Percent-encoding is not implicit acceptance of the decoded character: PHPThis preserves the encoded spelling, and routing continues to compare or validate those raw bytes under its existing grammar.

The rule does not claim that PHPThis observes bytes already rejected, decoded, or normalized by a web server or proxy. Deployment ingress behavior remains separately verified and must not be described as framework normalization.

## Reconsider when

Independent consumer and deployment evidence requires a narrower interoperable request-target grammar, an origin-form-only boundary beyond the existing absolute-path rule, explicit URL decoding, or a different raw-target source than `REQUEST_URI`. Reconsider the smallest affected boundary with exact migration and adversarial evidence rather than adding a broad sanitizer or silent normalization.
