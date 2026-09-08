# Comparison fixture revision 7

Pre-push review found two protected fixture behaviors that could distort the comparison. PHPThis returned `500` for malformed paths rejected by `Request`, while ordinary PHP returned the contract's `404`. Ordinary PHP also missed SQL preparation failures in its trace and checked its 256-statement limit only after execution.

Revision 7 corrects the three PHPThis application wrappers and the three ordinary-PHP observation implementations:

- Each PHPThis wrapper checks the existing router's allowed methods before constructing a request. Unmatched methods and malformed paths return the same normalized `404` without policy or database work. Exceptions from a matched handler retain their existing failure mapping.
- Ordinary-PHP instrumentation admits each execution before native database work, counts failed preparation, and records each successful prepare/execute pair once. Repeated execution remains separately counted; exhaustion prevents a 257th execution or preparation attempt.

The task revisions, six fixture/context hashes, and manifest pins advance together. Framework runtime, candidate handlers, task prompts and APIs, shared references, workspace policies, dependencies, budgets, calibration suffixes, and private holdout identities remain unchanged. Earlier preparations and campaigns retain their original inputs.

Bounded synthetic regressions exercise malformed routing, failed preparation and execution, repeated statements, and rejection before durable mutation at the query limit. Fresh offline preparation runs all six canonical application gates, compares unchanged public observations against revision 6, verifies the corrected negative cases, and checks exact source/mode changes. The full repository `composer check` remains required. This establishes fixture readiness; it supplies no new model result or authority for another paid campaign.
