# Reading and effort inventory

Retrospective known-file inventory for the maintainer-led implementation, not instrumented tool telemetry or a claim of every byte read. Repeated/truncated bulk tool output is not counted as a full semantic read; hashing a file is not semantic review. All entered application source/test files authored in this campaign were inspected through their generated patch and verification. Exact touched files/bytes/lines and patches are in manifest.json; reviewer's separate receipt is in review.md.

## Framework maintainer context

Known authority: AGENTS.md, VISION.md, .ai/README.md, .ai/rules.md, .ai/change-workflow.md, .ai/strict-profile.md, and .ai/consumer-profile.md. Relevant partial reads: docs/consumer-profile.md, tests/consumer-profile.php (opening ~100 lines), docs/evaluation.md (opening sections), docs/getting-started.md, docs/consumer-contract-upgrades.md (versions 14–18), current README.md release section and docs/crud.md placement. Package/archive boundaries: .gitattributes, .gitignore, composer/phpstan definitions and installed-consumer ownership from the issue's named starting points. No framework runtime/source tests were rewritten for this consumer.

## Installed Alpha 7 authority and app

Fully read by completion (large initial requests were truncated and continued in bounded chunks): installed docs/consumer-contract.md, docs/knowledge-map.md, docs/database.md, docs/type-safety.md, docs/request-policy.md. Entered CRUD placement read docs/crud.md lines 1–95; deferred surfaces were not adopted. Inspected installed Database/{Connection,QueryBudget,QueryTrace}.php, Http/Request.php, Routing/PathParameters.php and the starter HealthRoutes/HealthHandler. Request-handling guidance was consulted at the relevant HTTP/route boundary; no claim of a full every-guide review.

Read app AGENTS.md, .ai/README.md, .ai/rules.md, .ai/change-workflow.md, .ai/project.md, composer.json and lock identity; current context for data/request-policy/configuration/observability. Architecture/operations/testing were initially partial bulk reads; entered facts were explicitly reconciled into short concrete guides. Inspected bootstrap, public/index, Routes, HealthRoutes/HealthHandler, coordinator/source/sink/summary contracts and existing tests/run.php. Optional guide labels were searched/reconciled for stale health-only facts; their unentered adoption recipes were not treated as new requirements. The app's own freeze plan, authored Create/List/search source, fixtures/tests and updated guides remain auditable in Git.

## Installed source target

Read current docs/consumer-contract.md, docs/knowledge-map.md, docs/consumer-contract-upgrades.md versions 14–18; entered docs/errors.md outer boundary and docs/request-handling.md outer/emission sections. Inspected Http/UnknownFailureBoundary.php and RequestReader's representation boundary; inventoried app emitter/includes/output states, regular files and version markers. An attempted read of installed skeleton/public/index.php failed because skeleton source is excluded; implementation followed the installed contract/concern guide and retained app, without substituting a new skeleton.

## External records and interventions

Read live GitHub issue #71 and exact Packagist Alpha 7 metadata; installed via public Composer paths. Initial user authorized the bounded issue and one fresh qualitative reviewer. No additional product/production approval was invented. Network/loopback sandbox restrictions required normal tool escalation. The reviewer was resumed only after the user requested continuation; no substantive source hints were supplied. The synthetic database/auth/feature choices were disclosed as maintainer evaluation design, not accepted production decisions.

Command durations and Git dates are available; they do not measure active labor or total tool/reading/model cost, especially across the long interruption. See repairs.md for observed unsuccessful attempts rather than inferring a one-pass implementation.
