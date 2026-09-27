# ADR 062: Bounded Alpha 8 release scope

Status: proposed

## Context

Alpha 7 remains the latest published coordinated framework/skeleton release. Its framework tag `v0.1.0-alpha.7` identifies `de07cbd449ed3e905331aeea105e528fbaa83ac3`, Consumer Contract 13, Strict Profile 3 and diagnostics PHT001–PHT007. The preparation baseline is merged `main` at `3548cdfbb711b5130a00f228d2fbe739b4c8f756`, whose CI and Security workflows passed. That baseline carries accepted ADRs 055–061, Contract 18, Profile 4 and PHT008; it is not a release candidate.

The maintainer requested Alpha 8 preparation on 2026-09-27 (Asia/Manila). This proposed record makes the scope reviewable; it does not record acceptance of unseen notes or approval of either exact candidate. [Issue #81](https://github.com/balgf/PHPThis/issues/81) owns the evolving evidence, approvals and publication state.

## Proposed decision

Prepare one coordinated experimental release with Composer version `0.1.0-alpha.8` and framework/skeleton tags `v0.1.0-alpha.8`. Both exact candidate commits and the planned publication date remain `PENDING`. Keep the version tag-derived; add no root Composer version field. The [draft release notes](../releases/0.1.0-alpha.8.md) own the release-specific upgrade sequence; existing operational guides continue to own their detailed contracts.

The bounded rollup contains:

| Area | Included scope |
| --- | --- |
| Consumer validity | ADR 055 value-free Composer configuration scripts; ADR 057 distinct named SQL placeholders/PHT008; ADR 059 bounded source-prefix inspection and application-symlink rejection |
| HTTP behavior | ADR 056 raw request-target/path byte bounds; ADR 060 rejection of pending output before emission; ADR 061 application-owned generic-first outer failure handling and explicit disclosure profiles |
| Database transport | Native `PDO::connect` behind the existing Connection/PHT005 boundary, with unchanged direct SQL ownership and exact-engine transport evidence |
| Authoring guidance | ADR 058 concern-local context routing, reduced mandatory reading, source-grounded reviewer walkthrough, native attribute boundaries and optional application-owned operation coordination |
| Optional integration | LLPhant/Qdrant guide and reproducible consumer from #79; deterministic model protocols plus real disposable Qdrant, with the documented incompatible default paths and evidence limits |
| Maintainer evidence | Isolated evaluation tooling and recorded observations from #66–#70/#73, restriction-cost analysis #72, retained same-consumer feature/upgrade/review evidence #71, and supply-chain work #74 |
| Distribution | Matching source skeleton and templates, release notes, inventory/export checks, signed framework tag and verified repository release controls, then the matching public skeleton and clean public installation proof |

Preserve PHP `~8.4.0`, zero third-party framework runtime dependencies, the accepted 2,620-line core ceiling and current 2,618-line implementation. No new public runtime API, framework service, diagnostic beyond the already accepted PHT008, contract/profile increment beyond 18/4, or new paid evaluation belongs in preparation. Existing application policy and optional integration adoption remain human-owned.

## Consequences and verification

This release deliberately tightens accepted application source and runtime states. The notes must describe Contract 13 → 18 and Profile 3 → 4 as prerelease compatibility changes, including migration of the deployed HTTP entrypoint. Carry Alpha 7's prior cookie, session, file-transfer and other boundaries forward unchanged; no optional provider or service becomes a default.

Preparation starts with the 232-path baseline inventory. Adding this proposed scope and the draft notes produces 234 paths; that count is a source-preparation expectation, not an artifact proof. Existing installed-consumer checks must verify both documents and their proposal status. The complete clean exact-candidate gate, matching skeleton, preferred-distribution installation and final announcement follow [RELEASING.md](../../RELEASING.md). Earlier PR checks and synthetic consumer proofs cannot replace those gates.

Retain the measured limits of each evaluation: no generalized productivity, model-quality, ANN-performance, independent-adoption, production-readiness or cross-prerelease compatibility claim. After the exact public pair exists, repeat #71's retained consumer upgrade against it; the existing source upgrade is not that future result.

## Authority and reconsideration

Preparation and review are underway. Exact-candidate approval, a planned publication date, signing identity and each consequential publication operation remain in Issue #81. This proposal changes no remote protection setting, tag, package or GitHub release. A later accepted scope still does not approve an unidentified candidate.

Reconsider the scope if a new feature or contract decision enters the candidate, an upgrade loses application behavior, a required gate fails, or package/source identities diverge. Preserve failed predecessors and existing artifacts; never move a released tag to repair them.
