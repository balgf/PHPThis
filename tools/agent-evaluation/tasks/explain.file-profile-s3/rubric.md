# Explanation review rubric

Apply this rubric to the retained final response and event evidence. Structural collection checks are scored separately by the controller. Do not infer semantic correctness from file names, keyword presence, or a structurally complete run.

Each dimension records `pass`, `fail`, or `unknown` with concrete evidence.

## Route selection

Pass when the response starts from `.ai/file-transfers.md` and enters only the current file-transfer, security, testing, and ADR 053 material needed to answer the question. Fail when it selects an unrelated owner, treats historical rationale as current operational authority, or omits a necessary current owner. Use `unknown` when the retained evidence cannot establish the route.

## Necessary concern coverage

Pass when the response covers one selected file profile, the retained common multipart and request-policy boundary, application policy and source, behavior evidence, isolated real-AWS verification, deployment evidence, and the complete application gate. Fail when a necessary concern is omitted or treated as optional. Use `unknown` only when the response evidence is unavailable or indeterminate.

## Unsupported claims

Pass when every claim is grounded in the pinned workspace and the response avoids claiming automatic adoption, a generic storage facade, a framework AWS dependency, general S3-compatible support, production certification, `nosniff` equivalence, a range-free guarantee, or real AWS behavior from static guidance. Fail on any unsupported claim. Use `unknown` when the retained evidence cannot resolve a claim.

## Answer correctness

Pass when the response answers conditionally yes through deliberate `AMAZON_S3_ADR053` adoption, keeps `LOCAL_ADR026` unchanged, and explains that a real consumer decision needs its own record and evidence. Fail when it presents the switch as automatic, already proven, or forbidden by the framework. Use `unknown` when the response does not permit a reliable judgment.

## Repairs

Pass when observable corrections repair an earlier route or claim without introducing a new error. Fail when an observable error remains or a repair makes the answer worse. Use `unknown` when no correction is needed or the retained stream cannot establish self-correction.

## Clarification

Pass when the generic review answers directly and reserves consumer-specific questions for approving an actual migration. Fail when it blocks the review on unnecessary details or approves a concrete migration without required consumer facts. Use `unknown` when the retained evidence cannot establish whether clarification was justified.

An overall human-review pass requires passing route selection, necessary concern coverage, unsupported claims, and answer correctness, with no failed dimension. Repairs and clarification may remain `unknown` only when their evidence explains why the observation is unavailable or not applicable.
