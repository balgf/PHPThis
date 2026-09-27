# Alpha 8 preparation

[Issue #81](https://github.com/balgf/PHPThis/issues/81) owns status and approvals. [ADR 062](../../../docs/decisions/062-bounded-alpha-8-release-scope.md) records the accepted scope; [release notes](../../../docs/releases/0.1.0-alpha.8.md) own the upgrade sequence. This directory contains reviewable preparation inputs and is excluded from the framework package. It is not a release automation or a second validity gate.

## Verified starting state, 2026-09-27

- Framework `main`: `3548cdfbb711b5130a00f228d2fbe739b4c8f756`; [CI](https://github.com/balgf/PHPThis/actions/runs/36315152239) and [Security](https://github.com/balgf/PHPThis/actions/runs/36315152246) passed.
- Latest public framework: Alpha 7 at `de07cbd449ed3e905331aeea105e528fbaa83ac3`; dedicated skeleton/main: Alpha 7 at `c0e8f5c2a7eb45e24e97dc672bd22dcc13c8684d`. Packagist indexes both exact identities and no Alpha 8 version.
- Framework rulesets 24032980, 24032628 and 24032629 enforce required main checks, preservation of `v*` tags and administrator-only tag creation. Immutable releases are enabled.
- Skeleton ruleset inventory is empty; classic main protection returns `Branch not protected`; immutable releases are disabled. Existing CI's job is `check`, and its checkout/setup actions use mutable major tags.
- Effective Git configuration has no signing format/key or automatic tag-signing setting; GPG is unavailable and `ssh-keygen` is available. An approved public signing identity and disposable verification are pending.
- Maintainer MFA, scoped publishing access and sole-maintainer recovery were confirmed in #74. No secret, private key or recovery material belongs here.

These are dated observations. Re-read effective settings and exact refs before an operation; do not infer future state from this file.

## Reviewable skeleton changes

`skeleton-ci.yml` and `skeleton-security.yml` are the reviewed replacements/additions implemented in [skeleton PR #1](https://github.com/balgf/PHPThis-skeleton/pull/1) for the dedicated repository's `.github/workflows/ci.yml` and `security.yml`. They use the same already-reviewed pinned action revisions as the framework, locked dependencies, a complete installed consumer gate, dependency audit and workflow audit. The stable validity job name becomes `PHP 8.4 validity`.

`skeleton-rulesets/main.json` requires that exact job plus `Dependency audit` and `Workflow security` from GitHub Actions, with an up-to-date base and no routine bypass. It deliberately has no framework-only PDO matrix requirement. The other two payloads preserve existing `v*` tags and restrict new release-tag creation to administrators.

Apply controls in this order when authorized: merge the pinned workflows and verify all three check contexts; enable the main/tag rulesets; enable immutable releases; re-read all effective settings. Applying required check names before their workflows exist would block ordinary branch updates. The workflow and ruleset files are committed on the PR branch; live repository settings remain pending. The framework source skeleton carries the same pinned validity workflow so a later export retains it. Preserve the dedicated security workflow, rulesets and repository-specific operations record during that export.

## Candidate and publication work

Keep the complete copied checklist in Issue #81 rather than duplicating it here. The maintainer accepted the prepared scope and upgrade notes on 2026-09-27 (Asia/Manila), after PR #82 made them reviewable. Before publication, select the planned date, freeze and approve exact candidates, record an approved public signing-key identity, and prove signing in an owned disposable repository. No signing identity has been inferred from an account or invented; inspect only public configuration and verification results.

After the framework's approved publication and verified Packagist distribution, synchronize the dedicated skeleton from the exact framework candidate's `skeleton/`. Apply the canonical public-export adjustments and resolve the exact Alpha 8 framework from Packagist; a local mirror cannot establish that lock or public availability. Then prove the clean public pair and repeat #71's retained-consumer upgrade against it. Preserve historical release artifacts and failed candidates.

`announcement.md` is draft text with visible pending identity/evidence fields. It must be completed and reviewed against the actual proven pair before any announcement.
