# ADR 003: Query budgets

Status: accepted

## Context

Visible SQL can still produce N+1 behavior when a statement is executed repeatedly.

## Decision

Every `Connection` requires a `QueryBudget`. The connection records a statement before preparing it and throws when the next statement would exceed the limit.

## Consequences

Runaway statement counts fail early and tests can assert usage. A generous budget can still hide smaller N+1 cases, so fixture-size invariance tests remain mandatory.

## Reconsider when

The mechanism can be replaced by a stronger statement-count contract with equal local visibility and lower integration cost.
