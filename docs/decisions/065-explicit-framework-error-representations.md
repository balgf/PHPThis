# ADR 065: Explicit framework error representations

Status: accepted

## Context and authority

Framework-generated 404, 405 and unknown-failure 500 responses had fixed formats. Applications need to align these with their public API without replacing routing or the failure lifecycle. On 2026-10-15, the accountable maintainer approved issue #102's body/content-type API, byte limits, unchanged defaults, independent early fallback and Consumer Contract 19 migration.

## Decision

Add final readonly `ErrorRepresentation(contentType: string, body: string)`. Content type is nonblank, at most 256 bytes and subject to `Response`'s existing header-value control-byte rejection. Body is at most 65,536 bytes. Invalid construction throws a fixed redacted `InvalidArgumentException`; values are never truncated. These are code-owned public literals, never derived from a request, exception or internal state. The value validates bounds and header safety, not media-type grammar or body semantics.

`Application` accepts optional `notFound` and `methodNotAllowed` representations after its router. `UnknownFailureBoundary` accepts an optional `representation`; `respond()` still receives no arguments. Omitted/null values preserve every existing default byte and header.

The existing owners fix status, `Allow`, cache policy and correlation. Representation configuration contains no arbitrary header map, cookies, local file, callback or renderer. Known failures retain exact-class response mapping; application validation remains application-owned. Session and correlation copies preserve the complete response.

The front controller still constructs the unconfigured built-in 500 before bootstrap. Custom values are created inside protected composition and affect only successfully composed request handling. Invalid configuration or composition failure retains the built-in fallback. Existing outer development-details eligibility and response-emission behavior remain unchanged.

## Compatibility and maintenance

Consumer Contract 19 carries version 18 forward and permits this optional representation policy. Strict Profile 4 and PHT001–PHT008 are unchanged. Existing applications may keep all defaults without PHP edits. Adopters record compatibility for the new public format and retain client handling for the built-in early-failure 500. Package installation never rewrites application context.

This adds one immutable value and two optional constructor extensions, with no dependency or new execution layer. Applications own only their literal representations and client compatibility. Current instructions and examples live in [Error responses](../errors.md#framework-error-representations); [upgrade guidance](../consumer-contract-upgrades.md#contract-version-19) owns migration. Runtime, real-front-controller, session-copy and installed-package tests cover the policy; counts and prose alone do not prove it.

The maintainer-only smoke fixture advances from revision 32 to 33 because its source skeleton now carries Contract 19 guidance. Only current fixture/admission identities change; frozen comparison and explanation tasks, prompts, budgets and retained trials remain unchanged. Synthetic validation adds no model result or paid-run authorization.

## Reconsideration

Consider additional representation fields only for a concrete consumer need and an explicit decision about their ownership. Request-dependent negotiation, exception rendering and alternative server adapters remain separate decisions.
