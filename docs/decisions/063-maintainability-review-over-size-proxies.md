# ADR 063: Maintainability review instead of size proxies

Status: accepted

## Context

Issue #67 reproduced two limits of the repository's size gates: an empty Markdown file repaired the Markdown/PHP ratio, and three blank core lines failed the 2,620-line ceiling without changing significant PHP tokens. Counts made growth visible but did not establish documentation coverage or readable implementation.

On 2026-10-08, the accountable maintainer approved Issue #85's replacement policy: retain informational counts and require pre-merge review of documentation and runtime scope.

## Decision

The Markdown/PHP ratio and physical core-line ceiling no longer determine `composer guard` success. Its existing counts remain informational. There is no replacement numerical threshold.

The existing maintainer change workflow requires the change description to identify:

- Each observable change, its current documentation owner and behavior evidence, or why no public documentation update applies.
- For runtime changes, the concrete need, public API and dependency impact, visible execution path, and complexity shifted to verification tools or consuming applications.

The accountable maintainer reviews these points before merge. New consequential mechanisms still require an accepted architectural decision. Formatting, comments and necessary readable code do not spend a line allocation.

## Consequences

This removes the automatic stop against growth by count. Human review judges documentation coverage and justified runtime growth; passing guards do not prove those judgments. Strict analysis, architectural prohibitions, required contracts, routing, package inventories and behavior checks remain mandatory.

This decision partially supersedes ADR 049 only for its core-line allocation. Historical decisions, release records and the fixed #67 reproductions remain unchanged. Consumer Contract 18, Strict Profile 4, PHT001–PHT008 and runtime behavior are unchanged. These review duties belong to framework maintainers, not installed applications.

`tests/MaintainabilityGuardrailsTest.php` exercises the real guards in disposable repository copies. Crossing the old count thresholds, consolidating incidental Markdown and adding harmless core whitespace pass. Empty Markdown cannot repair a missing contract, broken current route, omitted required package entry or strict-types violation.

## Reconsideration

Revisit the review process if complete changes repeatedly omit required documentation or add unjustified runtime complexity. Use those concrete failures to choose a repair; a higher count alone is not evidence of either problem.
