# ADR 066: Server-reported peer address

Status: accepted

## Context and authority

Applications need peer metadata for network policies without reading superglobals in handlers. On 2026-10-15, the accountable maintainer approved issue #103's API, absent/malformed outcomes, limited server provenance and Consumer Contract 20 after reviewing parsing and Nginx/FPM preparation evidence.

## Decision

Add final readonly `PeerAddress` with a private constructor, public string `address`, and validating `fromString(string): self`. Accept bare IPv4/IPv6 literals of at most 45 bytes through native `FILTER_VALIDATE_IP`, preserving accepted bytes. Invalid factory input throws fixed redacted `InvalidArgumentException`.

Append optional `?PeerAddress $peerAddress = null` to `Request`; preserve the same instance through `withPathParameters()`. `RequestReader` uses only `REMOTE_ADDR` after method/target validation. Absence produces null; a present non-string or invalid literal throws redacted `InvalidRequest` before other parsing, body I/O, session begin or handling. Existing exact-class mapping owns the response.

This validates server-reported metadata. It cannot establish the original connection peer, undo server rewriting or authenticate a user. Headers never supply a fallback. Proxy trust, comparisons, address-family rules, allowlists and required-peer denial remain application-owned. There is no port, DNS lookup, principal, tenant, mutable context or hidden I/O.

## Compatibility and maintenance

Consumer Contract 20 carries 19 forward; Strict Profile 4 and diagnostics remain unchanged. Direct request calls remain compatible. Previously ignored malformed server metadata now fails ingestion. Applications verify their server source and preserve the value in explicit request copies.

One value and the existing reader/copy path meet the need without another execution layer or package dependency. [Request handling](../request-handling.md#server-reported-peer-address) owns current instructions; [upgrade guidance](../consumer-contract-upgrades.md#contract-version-20) owns migration. Runtime, installed-package, canonical-controller and disposable Nginx/FPM checks cover the recorded behavior without certifying other deployments or Swoole.

The current smoke fixture advances to revision 34 because starter guidance changes. Historical tasks, prompts, budgets and paid-trial evidence stay frozen. No new model campaign is authorized.

## Reconsideration

Add ports, normalization or trusted-proxy interpretation only for a concrete consumer need and separately accepted provenance and compatibility rules.
