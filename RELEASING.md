# PHPThis release process

Use this checklist for a proposed or approved release. Copy it into the release work item, retain evidence there, and leave this reusable checklist unchecked. Source preparation, candidate approval and each external publication operation require their recorded scope and authority.

## Current state and historical evidence

Alpha 8 (`v0.1.0-alpha.8`) is the latest recorded immutable source boundary. [Issue #81](https://github.com/balgf/PHPThis/issues/81) records framework `36643c068dda3619f939ac04d861c1316a0af8e9`, skeleton `1b1f3afed72880fc7852e67016349e87fc8c4c66`, signed tags, Packagist distributions and clean public installation. Package/public-proof wording was reconciled on 2026-09-27 (Asia/Manila). Verify continuing GitHub, Packagist and announcement state in the work item and hosts when needed.

Historical authority is the exact approved tag. Read its matching notes under `docs/releases/` and scope decision via `docs/decisions/README.md`; later `main` never replaces tagged evidence. [ADR 062](docs/decisions/062-bounded-alpha-8-release-scope.md) and [Alpha 8 notes](docs/releases/0.1.0-alpha.8.md) preserve their acceptance-time source-preparation wording. [Alpha 6 #37](https://github.com/balgf/PHPThis/issues/37) and [Alpha 7 #53](https://github.com/balgf/PHPThis/issues/53) retain their completed publication and announcement receipts. The [pre-consolidation release record](https://github.com/balgf/PHPThis/blob/884e7154984d774f9da8f595c820c2b978c8118a/RELEASING.md) preserves earlier identities and dates without duplicating them in the current procedure.

## Candidate and authorization

Commits after the latest tag are unreleased development. Assess that exact delta before proposing the next version, scope, notes and planned date. A proposed scope may precede an exact candidate; only an explicit human record accepts it.

Candidate approval names the version, both tag names, exact framework commit, planned date, scope, notes, announcement text and permitted operations. The skeleton commit may be `PENDING` during framework preparation; record and approve its exact commit before any skeleton tag or package write. Keep approvals and evolving proof in the external work item so recording evidence does not change candidate bytes. Source changes create a new candidate requiring fresh proof and approval.

Record authority separately for source preparation; exact candidate approval; framework commit/push; framework tag creation/push; framework Packagist update; skeleton preparation/commit/push; skeleton tag creation/push; skeleton Packagist update; each GitHub prerelease; and final announcement. A single approval may name several operations. Earlier approval, partial evidence or checklist position grants no unnamed operation. Record observed publication timestamps separately from the planned date.

## Version-neutral release gate

### Supply-chain prerequisites

Before approving a future candidate, verify the effective framework repository settings and record the result in the release work item. Configuration files and earlier successful checks do not prove continuing remote enforcement.

- `main` must reject deletion and force pushes and require successful `PHP 8.4 validity`, `PDO transport (SQLite 3.45.1, MySQL 8.4.11, PostgreSQL 17.11)`, `Dependency audit`, and `Workflow security` checks from the GitHub Actions app. Require an up-to-date base. The reviewed ruleset has no routine bypass; push a topic branch and prove its commit before advancing `main`. Independent review is required when a trusted reviewer is available; do not invent a second approver for a sole-maintainer project. If a check name changes, update the required check alongside the workflow and verify the result.
- Existing `v*` release tags must reject updates and deletion without a routine bypass. Restrict creation of new `v*` tags to repository administrators. An administrator can still change repository rules, so these settings do not eliminate account-compromise risk. Any emergency rule change needs explicit human authorization and a recorded reason, affected refs, restoration, and verification.
- GitHub immutable releases must be enabled before publishing a new framework release. Create a draft, attach and verify any intended assets, then publish it only at the existing authorized release step. Verify the resulting release's `immutable` state and attestation. This does not retroactively make Alpha 7 or another historical GitHub release immutable. Never rewrite, re-sign, or move an existing tag to retrofit these controls.
- Future framework release tags must be annotated and cryptographically signed by an approved maintainer key. Before tag creation, record the approved public key identity and verify the signing setup using a disposable local repository. Verify each new tag with `git verify-tag`, confirm its target matches the approved candidate, and record the verification before pushing. Keep private keys and recovery material outside the repository and evidence. A verified signature establishes an approved signer, not code safety; Composer does not automatically verify Git tag signatures or GitHub release attestations. Packagist's immutable non-dev source/distribution references are a separate control.
- The maintainer must confirm GitHub and Packagist MFA, securely retained recovery methods, scoped publishing credentials, and a continuity plan. Record confirmation and any accepted sole-maintainer limitation without credentials, recovery codes, or private-key material. A backup maintainer must be explicitly appointed and have appropriate access; a recovery document alone is not a second maintainer.

The reviewable framework ruleset payloads are in `.github/rulesets/` in the source repository. Verify the dedicated skeleton repository separately before applying any of these claims to a coordinated release. These prerequisites authorize no tag, package update, GitHub release, or announcement; the existing exact-operation approvals below still apply.

### 1. Freeze the release candidate

- [ ] Record the explicitly approved Composer version, framework tag, skeleton tag, exact framework candidate commit, planned release date, bounded scope record, release-notes path, candidate-specific announcement text, accountable-human authorization records, and each exact authorized next operation. Record the skeleton candidate commit now when it already exists; otherwise record `PENDING` and do not authorize a skeleton write yet.
- [ ] For an initial publication, confirm the approved framework and skeleton tags and package versions are new by checking local and remote tags plus the intended GitHub and Packagist identities. An unexplained collision stops the release and requires a new approved version. When resuming a recorded partial publication, require every existing tag and artifact to match its recorded commit and distribution evidence exactly, keep the candidate unchanged, and resume only the explicitly authorized next incomplete operation. Existing state never authorizes overwrite, tag movement, deletion and recreation, or artifact replacement.
- [ ] Confirm maintainer access to the intended GitHub repositories and Packagist package names `phpthis/framework` and `phpthis/skeleton`; do not infer availability from local package metadata.
- [ ] Confirm GitHub private vulnerability reporting is enabled for the public framework repository.
- [ ] Confirm the candidate matches its approved bounded scope and carries every earlier accepted boundary forward unless the approved scope explicitly changes it. Release notes must not imply production readiness, backward compatibility, complete CRUD, framework-owned authentication, authorization, tenancy, cache, queue, migration, configuration, permission management, WebSockets, generic middleware, SQL or DDL dialect portability, DRY validity, universal AI compliance, secret detection, grant validation, or automatic refactoring.
- [ ] Confirm the framework worktree is clean and its exact local candidate commit matches the approval record. Do not push it before the local proof in Step 2 passes. Any framework source change creates a new framework candidate that must be proved and approved again.
- [ ] Review every public API, Consumer Contract version, Strict Profile version, permanent diagnostic identifier, checker output change, and upgrade note changed since the previous release.
- [ ] Confirm `README.md`, `ROADMAP.md`, `SECURITY.md`, `docs/getting-started.md`, and the package metadata describe the same release state.

### 2. Prove the framework candidate

Run from the framework repository root:

```bash
composer validate --strict
composer install --no-interaction --no-progress --prefer-dist
composer check
```

- [ ] The complete local gate passes without a baseline, suppression, skipped required driver, or modified dependency source.
- [ ] `composer test:consumer` builds the release archive, matches the complete Composer and Git export inventory, installs a mirrored package into a clean skeleton, passes its adversarial controls, and finishes with `git-export-parity=verified` for this exact clean candidate.
- [ ] Treat `git-export-parity=skipped-dirty` only as a successful development run of the independent Composer archive, isolated installation, installed checker, application behavior, and adversarial controls. It does not satisfy this candidate gate, approve the candidate, or authorize push, tag, package, release, or any later operation. Failure to inspect Git status or to create, read, or compare the clean Git archive remains a hard failure rather than a third proof state; the fixed terminal output must expose no Git status bytes, source bytes, absolute paths, or untracked filenames.
- [ ] The framework archive contains exactly `tools/package-files.txt`; `bin/phpthis` remains executable.
- [ ] Release notes name the supported surface, exclusions, known limitations, and any breaking change without claiming evidence the candidate does not have.
- [ ] After the complete local gate passes, confirm the authorization record permits pushing the exact framework candidate commit, push it without modification, and record the pushed commit.
- [ ] GitHub CI passes both the PHP 8.4 validity job and the SQLite/MySQL/PostgreSQL PDO transport job for that exact pushed candidate commit.

### 3. Publish the framework prerelease

- [ ] Confirm the authorization record names framework tag creation and push and the framework Packagist update as separate permitted operations against the exact proved framework candidate commit.
- [ ] Create the approved framework prerelease tag from the proven commit as a signed annotated tag, verify its signature and exact target, then push that exact tag to the approved remote without moving or reusing an existing tag.
- [ ] Submit or refresh `phpthis/framework` on Packagist and wait until the exact prerelease is indexed with a preferred distribution artifact.
- [ ] Record the framework tag, commit, Packagist version, distribution reference, and observed timestamp and result of each framework publication operation in the release evidence.

At the end of this step, record the framework side as published but the overall release as partial and unproved until Steps 4 and 5 pass. Do not describe or announce the complete release yet.

### 4. Publish the skeleton prerelease

- [ ] Confirm the authorization record permits preparing, proving, committing, and pushing the dedicated skeleton candidate. That preparation authority does not authorize a skeleton tag or package update.
- [ ] Export the contents of `skeleton/` as the root of its dedicated repository; do not publish it as a nested directory of the framework package. Preserve the dedicated repository’s reviewed security workflow, ruleset payloads and repository-specific operations record when synchronizing the starter. Reconcile shared CI with the source template and re-verify the effective remote protections; an export must not remove them.
- [ ] Record the approved skeleton repository URL and confirm its package name remains `phpthis/skeleton`.
- [ ] Remove the framework-maintainer source-evaluation section from the exported skeleton README so the published package remains consumer-only and does not link to framework-repository files it does not contain.
- [ ] Remove the pre-alpha VCS `repositories` override from the exported `composer.json`.
- [ ] Replace `phpthis/framework: dev-main` with the approved framework prerelease constraint resolved from Packagist.
- [ ] Run `composer update --prefer-dist` in the skeleton repository and commit the generated `composer.lock`.
- [ ] Confirm the lockfile resolves the exact approved framework prerelease through its distribution artifact.
- [ ] Compare the installed framework's complete relative file inventory with the release source's `tools/package-files.txt`, and confirm `vendor/bin/phpthis` is executable before tagging the skeleton.
- [ ] Run `composer validate --strict` and `composer check` from the skeleton root.
- [ ] Confirm skeleton CI is configured to install locked dependencies, invoke the installed `phpthis check`, and run the application behavior tests.
- [ ] After every skeleton source and lockfile change is committed, the local gate passes, and the worktree is clean, confirm push authorization, push the exact skeleton candidate commit without modification, and record its identity and local evidence. Any later skeleton change creates a new skeleton candidate that must be proved and approved again.
- [ ] Confirm skeleton CI passes for that exact pushed candidate commit, then obtain accountable-human authorization for the exact skeleton tag creation and push and the separate Packagist update against that commit.
- [ ] Tag the proven skeleton commit and push that exact tag to the approved remote without moving or reusing an existing tag.
- [ ] Submit or refresh `phpthis/skeleton` on Packagist and wait for indexing.
- [ ] Record the skeleton tag, commit, Packagist version, distribution reference, and observed timestamp and result of each skeleton publication operation.

### 5. Prove the public distribution path

Use a new empty directory and normal Packagist resolution. Do not add a VCS repository override, path repository, local archive, symlink, or source-checkout fallback.

Replace `APPROVED_SKELETON_VERSION` with the exact approved Composer version from the external candidate record; do not run the placeholder unchanged.

```bash
composer create-project --stability=alpha --prefer-dist phpthis/skeleton phpthis-release-proof 'APPROVED_SKELETON_VERSION'
cd phpthis-release-proof
composer check
```

- [ ] Composer resolves the approved `phpthis/skeleton` and `phpthis/framework` prerelease versions from Packagist-preferred distribution artifacts.
- [ ] The installed `vendor/phpthis/framework` relative file inventory exactly matches the release source's `tools/package-files.txt`, and `vendor/bin/phpthis` is executable.
- [ ] The generated application has no unresolved template token, no consumer PHPStan configuration or baseline, and a committed-lockfile-ready dependency graph.
- [ ] The installed framework profile and application behavior tests pass through the generated application's complete gate.
- [ ] The real front controller serves the exact documented `GET /health` response on a loopback-only local server.
- [ ] Record the clean environment, PHP version, Composer version, resolved package versions, distribution references, inventory result, complete-check output, and health result without secrets or local credentials.

### 6. Announce or stop

- [ ] Update mutable repository availability or announcement wording only after both packages and the clean public path are proven; keep tagged package authority independent of mutable publication state.
- [ ] Confirm each framework and skeleton GitHub-prerelease operation is explicitly authorized, then publish both approved GitHub prereleases for the already-pushed proven tags without moving either tag.
- [ ] Confirm the observed timestamp and result of every external publication operation has been recorded separately from the planned release date, then obtain explicit accountable-human authorization for the final candidate-specific announcement.
- [ ] Publish only the approved candidate-specific announcement, with direct links to both tagged packages, its release notes and bounded scope record, every carried-forward or changed boundary needed to understand the candidate, the security policy, and the installation command.
- [ ] Preserve the release evidence with the release work item.

If any mandatory check fails before publication, stop and fix the cause on a new candidate commit. If a framework or skeleton tag, package, or GitHub prerelease already exists when a later step fails, preserve and record that exact partial-publication state, do not announce the complete release, and do not move a tag or replace an artifact. When a public prerelease is defective, document it, mark it appropriately in the package host, approve a new prerelease identity, and run the complete gate again.

## Evidence record

Record at least:

```text
Framework version/tag:
Exact framework candidate commit:
Exact-candidate approval record:
Bounded scope record:
Release-notes path:
Planned release date:
Framework Packagist distribution reference:
Framework release URL:
Skeleton version/tag:
Exact skeleton candidate commit:
Skeleton Packagist distribution reference:
Skeleton release URL:
Candidate CI URLs:
Observed external operation timestamps and results:
Public-proof date and environment:
Inventory result:
Local Git-export parity state (`verified` required):
Generated application check result:
Loopback health result:
Candidate-specific announcement reference:
Accountable-human authorization records by exact operation:
Partial-publication state or NOT_APPLICABLE:
Known limitations:
```

Do not store tokens, credentials, signing material, private package-host data, or production payloads in release evidence.
