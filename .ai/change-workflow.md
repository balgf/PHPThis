# Change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the request path and every side effect.
3. Read the smallest relevant guide and implementation files.
4. Surface any consequential product, architecture, security, data, release, or operational choice for human judgment before treating it as accepted.
5. Reuse the canonical pattern. Do not create an abstraction in anticipation of future use.
6. Write the test, including a bound for queries or collection size where applicable.
7. Implement the smallest direct change.
8. Name the current documentation owner and behavior evidence for each observable change. Update the applicable guide, or explain why no public documentation update applies.
9. Run guardrails and tests.
10. Report files changed, behavior proven, consequential decisions, and any unproven production concern.

A task is not complete when the code merely runs. The execution path and cost must be apparent to the next agent.

Before merge, the accountable maintainer reviews that documentation and evidence. For runtime changes, the change description also explains the concrete need, public API and dependency impact, visible execution path, and complexity shifted to verification tools or consuming applications. Keep code readable; file and physical-line counts are informational. New consequential mechanisms still require an accepted decision. [ADR 063](../docs/decisions/063-maintainability-review-over-size-proxies.md) records this replacement for the former size gates.
