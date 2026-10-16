# ADR 070: Fixed early HTTP error representations

Status: accepted

## Context and decision

Issue #137 asks whether an application can use a fixed public 500 format for catchable failures before its terminal coordinator exists. The maintainer approved the protected sequence and Consumer Contract 24 on 2026-10-16. Existing `ErrorRepresentation` and `UnknownFailureBoundary` types are sufficient; no core API, dependency, setting, callback, renderer or additional HTTP entrypoint is added.

Keep the unconfigured built-in response before application work. Inside the existing outer `try`, an application may construct and assign a complete fixed response before configuration, bootstrap and composition. Selection uses public source literals and the existing value types, with no request/exception data, environment/configuration reads or external I/O. Ordinary class autoloading stays inside the protected step. Failed construction/loading retains the built-in response and stops startup. Later catchable failures use the selected generic response under the existing disclosure policy.

The existing pre-start emission fallback uses the selected generic body/content type, retaining status 500, `Cache-Control: no-store`, its fixed log event and one-attempt limit. Early generic responses retain `private, no-store`. Pending or started output permits no replacement, append or buffer cleanup. Failed fallback emission is not retried. Outer event isolation, detail eligibility, renderer-failure recovery, coordinator correlation and terminal summaries retain their owners and guarantees.

## Consequences and evidence

Contract 24 replaces ADR 065's requirement that every early failure use the built-in format with this optional protected selection. ADR 061's generic-first and disclosure guarantees remain binding. Invalid custom selection still requires clients to accept the built-in response. Failures before autoload and initial fallback construction, process exhaustion/termination, server-generated errors and lost output ownership remain outside the guarantee.

The starter and executable example retain their default formats. Applications explicitly adopt, record and test a changed public format; dependency installation performs no migration. Strict Profile 4 and PHT001–PHT008 are unchanged. Current sequence and phase ownership live in [Error responses](../errors.md#outer-http-failures); [Contract 24 migration](../consumer-contract-upgrades.md#contract-version-24) owns adoption.

Real-SAPI cases exercise startup phases, invalid/loading selection, distinct content types, event/renderer isolation, pre-start fallback, pending/started output and genuine coordinator outcomes. Installed-consumer checks verify the shipped types and starter sequence. The complete framework gate remains required; local built-in-server evidence is not target-deployment certification.
