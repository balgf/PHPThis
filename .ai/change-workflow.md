# Change workflow

1. Restate the requested observable behavior in one sentence.
2. Name the request path and every side effect.
3. Read the smallest relevant guide and implementation files.
4. Reuse the canonical pattern. Do not create an abstraction in anticipation of future use.
5. Write the test, including a bound for queries or collection size where applicable.
6. Implement the smallest direct change.
7. Update the guide if the public pattern changed.
8. Run guardrails and tests.
9. Report files changed, behavior proven, and any unproven production concern.

A task is not complete when the code merely runs. The execution path and cost must be apparent to the next agent.
