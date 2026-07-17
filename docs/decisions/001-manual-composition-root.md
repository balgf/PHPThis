# ADR 001: Manual composition root

Status: accepted

## Context

Autowiring containers resolve dependencies through reflection, naming rules, or runtime configuration. A service locator hides dependencies at the use site.

## Decision

Applications construct handlers and dependencies manually in one composition root. Routes receive constructed handler objects.

## Consequences

The object graph is searchable and debuggable with base PHP. Wiring becomes longer as an application grows, but duplication stays visible and can be addressed only after evidence.

## Reconsider when

A real application demonstrates that manual wiring is a dominant maintenance cost and a proposed alternative preserves a generated, reviewable object graph without runtime discovery.
