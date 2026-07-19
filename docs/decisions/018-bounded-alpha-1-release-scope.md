# ADR 018: Bounded Alpha 1 release scope

Status: accepted

## Context

When this decision was accepted, PHPThis had an independently installable framework archive, a framework-owned consumer checker, a runnable skeleton, application-owned automated behavior evidence, database query-scaling controls, and a public repository. It remained pre-alpha at that point because neither `phpthis/framework` nor `phpthis/skeleton` had been published as a tagged Packagist prerelease and the actual public distribution path had not been exercised.

The reference application intentionally proves only bounded parts of Create, List, and Get. Create identity and conflict behavior, authorization, tenancy, Update, and Delete remain application decisions or future evidence. Treating all conventional framework capabilities as Alpha prerequisites would turn the first release into an unbounded full-stack project and weaken the evidence-first development model.

Alpha 1 needs an explicit claim boundary so an AI can distinguish a release blocker from a desirable later capability and so a human evaluator knows what the prerelease does not promise.

## Decision

Alpha 1 validates the PHPThis installation and development pattern. Its supported release surface is limited to the behavior documented and checked by the tagged version:

- the PHP 8.4 Consumer Contract version 3, Strict Profile version 2 with `PHT001` through `PHT006`, installed `phpthis check` command, knowledge map, application AI context, and mandatory application-owned automated behavior-test stage;
- ordinary constructor composition, immutable request and response values, bounded runtime request ingestion, exact-class error mapping, validated response cookies, and explicit response emission;
- indexed literal routes and the one bounded trailing `{name:positive-int}` route form with immutable typed delivery;
- the thin PDO transport, finite direct SQL checks, unique named bindings, explicit transactions, query budgets, and bounded redacted query traces;
- the optional lazy native-file session transport, while authentication and all session-backed security policy remain application-owned;
- the independently packaged health-only skeleton with resolved project-owned AI context and a complete profile-plus-behavior-test gate.

Alpha 1 is experimental evaluation software. It is not production-ready and makes no backward-compatibility promise across prereleases. Complete CRUD is not an Alpha 1 release prerequisite. The partial Create, List, and Get reference evidence does not promise a generic CRUD runtime, complete Create identity or conflict semantics, Update, Delete, authentication, authorization, tenant isolation, CSRF policy, migrations, queues, templating, uploads, streaming, workers, a cache mechanism, or a production deployment topology. PDO transport certification does not make application SQL dialect-independent.

The core is feature-frozen for Alpha 1 except for a demonstrated release blocker, correctness defect, or security defect within the accepted surface. New mechanisms and completion of the reference CRUD profile move after the first alpha rather than silently expanding its gate.

Alpha 1 may be announced only after every mandatory step in `RELEASING.md` passes. In particular:

- the framework tag is installed through Packagist's preferred distribution artifact and its complete package inventory matches `tools/package-files.txt`;
- `skeleton/` is exported as the root of its own repository, removes the pre-alpha VCS override, requires the approved framework alpha constraint, commits its lockfile, and is published as `phpthis/skeleton`;
- the exact public `composer create-project --stability=alpha phpthis/skeleton` path succeeds in a clean directory and the generated application passes its complete check.

The exact prerelease version, tag names, skeleton repository URL, package publication, and release announcement remain separately authorized release operations. Accepting this scope does not perform or pre-authorize those external writes.

Alpha 1 must not be announced unless every mandatory release gate passes. This decision does not record mutable publication state; verify current state from tagged releases, Packagist, and the external release evidence.

This decision changes no runtime API, Consumer Contract version, or Strict Profile version.

## Consequences

The first alpha can be honest and useful without reproducing a full-stack framework. Evaluators receive a small checked foundation and a real consumer path, with explicit warnings about unsupported product and operational policy.

Incomplete reference CRUD policy no longer creates an indefinite publication blocker. Those gaps remain visible roadmap work and cannot be presented as supplied behavior. Applications adopting PHPThis still own their complete identity, conflict, authorization, tenancy, data, security, and deployment decisions.

The two-package release requires coordinated tags, Packagist metadata, a committed skeleton lockfile, and post-publication evidence. Local archive success alone cannot satisfy the release gate. A failed public proof delays the announcement; it is not waived by a green source checkout.

Breaking changes may occur during the alpha series, but each release must keep its contract, diagnostics, decisions, and upgrade impact explicit. Production support and a security-response SLA remain unavailable.

## Reconsider when

At least two independent applications have exercised the packaged alpha through materially different work, or evidence shows that a capability excluded here is necessary for the installation and authoring pattern itself. Reconsider one bounded release claim with executable evidence; do not infer stable or production support from the existence of an alpha tag.
