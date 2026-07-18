# ADR 009: Project-owned AI context

Status: accepted

## Context

PHPThis can define portable implementation rules, but it cannot know a consuming application's domain vocabulary, source paths, production scale, authorization boundaries, integrations, deployment model, or dangerous operations. Leaving those facts implicit recreates the inference problem the framework is intended to reduce.

The framework repository already has a root `AGENTS.md` and `.ai/` directory. Those files guide PHPThis maintainers and refer to framework-only examples, tests, guardrails, and source paths. Copying them into an application would create incorrect instructions. The repository also uses the word "harness" for executable evaluation, so using `harness/` for AI guidance would make two different mechanisms share one name.

## Decision

Every PHPThis application should own and commit a thin root `AGENTS.md` plus a root `.ai/` directory. `AGENTS.md` defines the read order. `.ai/README.md` routes a task to the smallest relevant application guide. Detailed rationale and accepted decisions remain under the application's `docs/decisions/` directory.

The installed framework package supplies one versioned consumer contract. It is the portable validity floor. Application instructions add verified project facts and may strengthen the contract, but cannot weaken or suppress it. Framework upgrades never overwrite application-owned context.

PHPThis publishes a documentation-only template under `templates/application/`. Its placeholders must be resolved from real project evidence before feature work begins. It is separate from the framework-maintainer `.ai/` directory and does not claim to be a complete application skeleton or validity runner.

The canonical term is `application AI context`. "Harness" remains available for executable test and evaluation infrastructure.

## Consequences

An AI receives both a stable framework contract and local application facts without loading the entire documentation tree. Projects must maintain their context as architecture and operational assumptions change. Incorrect context becomes an explicit project defect rather than hidden conversational knowledge.

The first template contains only documentation. Consuming projects must still configure their own autoloading, PHPStan paths, reusable profile enforcement, guardrails, and tests. A future skeleton may automate that only after the application shape is proven.

## Reconsider when

An installable application skeleton exists, a tool-neutral discovery standard replaces the root entrypoint, or real projects demonstrate that the proposed files cause repeated context loading or ownership ambiguity. Preserve the separation between framework-owned rules, application-owned facts, and executable evaluation in any replacement.
