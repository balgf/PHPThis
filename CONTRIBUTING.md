# Contributing

PHPThis expects AI to author most changes, but it does not require exclusive AI authorship. Every contribution has an accountable human directing or reviewing it. Read `AGENTS.md` whether the change is authored by a person or an AI, then read the specific `.ai/` guide for the area being changed.

A contribution should:

1. State the behavior and its observable cost.
2. Prefer an existing pattern over a second equivalent API.
3. Add or update tests before broadening the core.
4. Update the relevant guide in the same change.
5. Add a decision record for a new architectural mechanism.
6. Run `composer check`, which includes guardrails, maximum-level PHPStan analysis, and tests.

AI may draft and update a proposed decision record and analyze tradeoffs. A consequential product, architecture, security, data, release, or operational decision becomes accepted only through explicit accountable-human maintainer approval; AI may record that approval.

`composer check` is the PHPThis validity gate, not an optional quality report. Fix Strict Profile diagnostics at their cause; do not add a baseline, inline ignore, wildcard exclusion, or comment exemption.

A shorter call site is not sufficient justification. New framework code must reduce ambiguity, enforce a rule, or remove repeated application code without hiding I/O or control flow.
