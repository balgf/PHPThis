# ADR 008: Explicit request boundary and exact error responses

Status: accepted

## Context

The example front controller directly normalized PHP runtime values, read an unbounded input stream, and had no public failure boundary. Handlers could not inspect headers, known input failures remained uncaught, and an unknown exception could expose environment-dependent output. Adding generic request helpers or middleware would reduce visible code while enlarging the inference surface.

The previous 550-line core limit covered the Phase 0 router, HTTP values, database boundary, budgets, and query tracing. A strict runtime reader plus explicit error mapping cannot fit in the remaining 27 lines without hiding validation or moving framework behavior into the example.

## Decision

PHPThis has one `RequestBoundary`. It owns the sequence of bounded `RequestReader` ingestion, one `RequestHandler` call, and exact-class `ErrorResponseRegistry` lookup after failure. The composition root supplies the body limit, input URI, handler, and complete error map. Only the front controller reads superglobals and handles rethrown unknown failures.

`Request` carries a lowercase immutable header map. The reader applies fixed bounds to request-target bytes, top-level query parameters, header count, and header value bytes. It reads at most the configured body maximum plus one byte. Route-specific media validation remains explicit in the endpoint handler.

The Phase 1 core-source cap increases from 550 to 900 physical lines. The implemented boundary occupies 874 core lines; the remaining maintenance margin does not pre-authorize another mechanism.

## Consequences

Runtime input has one tested normalization path, handlers no longer read global state, and public client failures are stable without broad exception catches. Unknown failures retain their identity until the top-level log and generic 500 response. The boundary adds no controller, middleware chain, automatic binding, or service container.

The example's basic unknown-failure log contains no request ID, exception message, stack, or query summary. That limits diagnostics but prevents accidental leakage until a structured logging contract exists.

## Reconsider when

A real application requires streaming bodies, trusted proxy rules, uploads, richer query parsing, conflict translation, request IDs, or structured request-level logging. Each must preserve one visible request path and receive its own cost and failure tests.
