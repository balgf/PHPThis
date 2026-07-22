# ADR 033: Application-owned request-handler decorators

Status: accepted

## Context

Some HTTP concerns apply to more than one route but do not belong in routing, transport ingestion, terminal response handling, or a domain operation. Repeating a small visible action can be correct, but forcing every reusable request concern into a handler can also obscure its one responsibility. A generic middleware pipeline would create a second execution model whose order, termination, dependencies, and I/O are difficult for an AI and reviewer to recover locally.

PHPThis already has one sufficient runtime seam: `RequestHandler::handle(Request): Response`. A narrowly constrained application wrapper can reuse that seam without adding middleware concepts to the framework or weakening the visible route-to-handler path.

## Decision

Consumer Contract version 9 accepts one optional application pattern named an **application-owned request-handler decorator**. Strict Profile version 2 remains unchanged. PHPThis adds no core class, runtime dependency, diagnostic, middleware interface, or composition facility, and the 2,600-line core ceiling remains unchanged.

An application-owned request-handler decorator is a final application class that implements the existing `RequestHandler` interface and receives exactly one downstream `RequestHandler` through its ordinary constructor. It has one narrowly named route concern. It is not a generic middleware implementation and does not implement or introduce another handler, middleware, interceptor, filter, or continuation interface.

Composition occurs only in the handler argument of an explicit `Route`. A route may use one decorator or explicit nested decorators, but the complete outer-to-inner order remains visible beside that route declaration. The decorator chain is not assembled by an array, loop, registry, priority value, configuration file, helper, factory, service container, discovery mechanism, attribute, or reflection. Shared constructor dependencies may be constructed elsewhere; the route still shows the complete decorator order and terminal handler.

For every call to `handle`, a decorator:

- receives the immutable `Request` selected for that route;
- either returns an explicit early `Response` without calling downstream, or calls its one downstream handler exactly once;
- passes the exact same `Request` instance to downstream without replacing, enriching, or attaching context to it;
- does not catch, wrap, translate, suppress, retry, or otherwise replace an exception from downstream or its own visible work;
- may return the downstream `Response` unchanged; and
- when changing a returned downstream response, constructs one explicit immutable replacement and preserves every unchanged status, header, body, `ResponseCookie`, and `LocalFileBody` field.

A decorator may perform bounded, narrowly named application I/O only when that cost and ownership are visible in its class name, constructor dependencies, call site, and tests. Database work uses a separately named `Connection`, `QueryBudget`, and `QueryTrace`, obeys the direct finite-SQL rules, and has a constant statement bound across materially different fixture sizes. Other external work has an equivalent finite attempt, byte, time, and failure policy. An early response performs no downstream work. A downstream failure does not trigger a second downstream call or a decorator-owned fallback side effect.

The pattern cannot wrap `Application`, `RequestBoundary`, the application terminal request-summary coordinator, or `ResponseEmitter`. Those boundaries retain their one fixed ownership and ordering. A decorator cannot move routing, runtime ingestion, error registration, session finalization, correlation, terminal-summary construction, sink invocation, or response emission into a route-local wrapper.

Applications do not add a generic or framework middleware interface, pipeline, stack, runner, registry, priority list, discovery rule, `$next` callable, request-context bag, request attributes, or framework-owned decorator. Principal, tenant, authorization, session, cache, and domain values remain in their existing explicit typed paths rather than being smuggled through `Request`.

An adopting application proves the direct route composition and, for each decorator, the early-return and downstream path that apply, zero-or-one downstream invocation, exact request identity, exception identity, immutable response-field preservation, bounded named I/O, and explicit nesting order. It also proves that a short circuit prevents every downstream query, mutation, and external effect. These are behavior and review obligations rather than a new static diagnostic.

## Consequences

An application can reuse a small request-level concern without asking PHPThis to own a middleware runtime. The route remains the local source of truth for order, and the existing handler interface remains the only dispatch contract. A reader can see whether a route short-circuits, which dependencies may perform I/O, and which terminal handler runs.

Explicit nesting repeats composition where several routes share the same concern. That repetition is deliberate: hiding it in a generic pipeline would make order and applicability non-local. The report-only duplication advisory may identify repeated decorator construction, but that finding does not authorize a registry, helper, or automatic abstraction.

This decision does not turn ADR 020 request policy, ADR 023 terminal summaries, session finalization, caching, logging, exception mapping, or emission into middleware. Each retains its accepted ownership, cost, and failure contract.

## Reconsider when

At least two independent applications demonstrate that direct route-local nesting itself causes a concrete correctness or review failure that cannot be solved by one named decorator around one downstream handler; or evidence shows that a specific concern must execute outside the route-handler boundary to preserve transport or terminal semantics. Reconsider one bounded execution requirement, not a generic middleware ecosystem.
