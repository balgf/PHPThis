# ADR 031: Bounded Alpha 3 tooling release scope

Status: accepted

## Context

Alpha 2 established Consumer Contract version 7, Strict Profile version 2, permanent diagnostics `PHT001` through `PHT006`, the PHP 8.4.x runtime boundary, the bounded framework runtime, and the accepted application-owned patterns recorded in ADR 029. ADR 030 subsequently added one checked, report-only consumer duplication advisory. That advisory helps an AI and its accountable human notice exact repeated application code, but deliberately does not define valid PHPThis code or justify automatic abstraction.

A new prerelease must distinguish that tooling addition from a runtime, contract, profile, or production-support expansion. It must also preserve the permanent no-magic and explicit-SQL boundaries rather than presenting the advisory as permission to introduce generic helpers merely to reduce repetition.

## Decision

Alpha 3 is accepted as a tooling-only release that carries the complete bounded Alpha 2 claim forward unchanged and adds ADR 030's report-only consumer duplication advisory.

The exact approved identity is:

- Composer version: `0.1.0-alpha.3`
- framework tag: `v0.1.0-alpha.3`
- skeleton tag: `v0.1.0-alpha.3`

Publication state is external. This decision approves the bounded source claim and exact identity; it does not assert that either tag, either Packagist package, either GitHub prerelease, or the public installation path exists. Every external operation remains subject to the ordered gates, accountable-human authorization, and recorded evidence in `RELEASING.md`.

Alpha 3 adds no framework runtime class, runtime behavior, route behavior, HTTP behavior, database behavior, application service, or application pattern. Consumer Contract version 7, Strict Profile version 2, diagnostics `PHT001` through `PHT006`, and the supported PHP range `~8.4.0` remain unchanged. The Alpha 2 consumer profile and every capability ownership decision in ADR 029 remain the current application and core boundary.

The only authored-code checking addition is the bounded advisory from ADR 030. `phpthis check` uses the existing application manifest and captured source to inspect exact runs of at least 48 normalized PHP tokens. A complete no-match scan prints a concise pass line. A possible group or incomplete bounded scan prints an advisory, and a caught report-generation failure prints one fixed unavailable advisory; application validity is unaffected in each case. Duplication alone returns success, creates no `PHT` diagnostic, and does not prevent PHPStan or the mandatory application-owned behavior-test stage from running. Detailed application-relative locations remain debug-only and disclose no PHP source-token content.

Consumers upgrading from Alpha 2 make no required source, configuration, runtime, Consumer Contract, or Strict Profile change. They must rerun their complete project check and account for the additional duplication-advisory output in any exact command-output assertions or automation. A possible group requires contextual human review; it does not authorize automatic rewriting, a shared abstraction, or weakening deliberately explicit SQL, security sequencing, bounded operations, or independent tests.

Alpha 3 continues every permanent exclusion, including:

- no ORM, Active Record, model or repository layer, lazy loading, query builder, generated SQL, binding or placeholder helper, generic paginator, or SQL-dialect abstraction;
- no implicit or global scope, observer magic, automatic discovery, reflection-based wiring, service container, facade, global helper, macro system, dynamic proxy, or autowiring mechanism;
- no framework-owned authentication, authorization, tenancy, validation, logging, cache, queue, scheduler, migration, storage, filesystem, or distributed-lock abstraction; and
- no production-readiness, backward-compatibility, security-SLA, complete-CRUD, portable application-SQL, deployment, capacity, or exactly-once-effect claim.

Before the framework tag is created, one clean pushed framework candidate commit must pass the complete local gate, exact CI validity and PDO transport jobs, installed-consumer proof, package inventory, and executable checks. The framework's Packagist-preferred distribution must then be verified before the dedicated skeleton is updated, locked, proved, and tagged. Both indexed packages and a clean public `composer create-project --stability=alpha phpthis/skeleton` installation plus loopback health proof must pass before announcement. The exact commits, release date, artifact references, CI URLs, public-proof environment, and accountable-human publication authorization belong in external release evidence so they do not mutate the candidate being proved.

## Consequences

Alpha 3 gives consumers a bounded review signal for possible exact duplication without changing which applications are valid. Existing Alpha 2 applications on PHP 8.4.x can evaluate the release by updating the exact prerelease, rerunning the complete gate, and reviewing any advisory output. Automation that assumed an exact pre-PHPStan output stream may need an assertion update even though its validity result does not change.

The advisory can miss short, renamed, reordered, semantically equivalent, non-PHP, or resource-bound-skipped duplication and can report intentional explicit code. Passing it is not proof that an application is DRY, and receiving a possible group is not proof of a design defect. Alpha 3 remains experimental evaluation software with no production-support or backward-compatibility commitment.

## Reconsider when

Independent consumer evidence justifies changing the advisory threshold, normalization, bounds, visibility, or validity effect; a supported PHP minor changes; or a capability moves between framework core and application ownership. Any permanent diagnostic, suppression, configuration, automatic refactor, runtime addition, contract change, or profile change requires a separate accountable-human decision, migration analysis, and complete evidence. Alpha 3 does not pre-authorize those changes.
