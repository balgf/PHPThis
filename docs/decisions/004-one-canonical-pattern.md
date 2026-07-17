# ADR 004: One canonical pattern

Status: accepted

## Context

Aliases and multiple extension styles increase the number of plausible implementations an AI may generate.

## Decision

PHPThis exposes one supported pattern for each common task. Compatibility aliases, facades, alternate handler signatures, and convenience spellings are not added.

## Consequences

The API may feel less flexible, but examples, search results, and generated code converge on the same structure.

## Reconsider when

Never for aesthetics alone. A second pattern requires a distinct capability that cannot be expressed by the first.
