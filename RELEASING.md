# PHPThis release process

Alpha 1 scope is preserved in `docs/decisions/018-bounded-alpha-1-release-scope.md`, Alpha 2 in `docs/decisions/029-alpha-2-consumer-profile-rollup.md`, Alpha 3 in `docs/decisions/031-bounded-alpha-3-release-scope.md`, Alpha 4 in `docs/decisions/035-bounded-alpha-4-release-scope.md`, and Alpha 5 in `docs/decisions/040-bounded-alpha-5-release-scope.md`. Alpha 5 and `v0.1.0-alpha.5` are the latest release identity and tag recorded by the repository source record. Publication state is external: verify GitHub and Packagist rather than inferring live availability from repository text.

This is the maintainer release gate, not an application programming manual. Copy the checklist into the release work item and attach evidence there. Keep this canonical checklist unchecked and reusable.

Tags, GitHub releases, repository creation, and Packagist publication are consequential external writes. Perform each only after explicit accountable-human authorization for that release. Do not announce a release until the post-publication proof passes.

## Immutable release history

Historical release authority means the exact bytes reachable from the approved tag. A later `main` file at the same path may contain a clarification, but it is current documentation rather than evidence of the tagged release. Never rewrite or move the historical tag to make those copies agree; inspect the tag itself.

## Approved Alpha 1 identity

The accountable human approved the following release identity on 2026-07-19 (Asia/Manila):

- Composer version: `0.1.0-alpha.1`
- Framework tag: `v0.1.0-alpha.1`
- Skeleton tag: `v0.1.0-alpha.1`
- Release notes: `docs/releases/0.1.0-alpha.1.md`

This approved the version and tag names only. The exact candidate commit, release date, and accountable-human publication authorization belong in the external release evidence attached to the release work item; embedding them in tracked release notes would change the candidate commit. That approval did not itself authorize creation of either tag, either package-host entry, either GitHub release, or the announcement. Alpha 1 remained subject to the complete gate recorded by its tagged source, and its publication state must be verified from external evidence.

## Approved Alpha 2 identity

The accountable human approved the following release identity and gated publication sequence on 2026-07-21 (Asia/Manila):

- Composer version: `0.1.0-alpha.2`
- Framework tag: `v0.1.0-alpha.2`
- Skeleton tag: `v0.1.0-alpha.2`
- Release notes: `docs/releases/0.1.0-alpha.2.md`

This approves the exact version and tag names and authorizes the following operations only after their preceding gates pass: commit and push the framework candidate; create and push both approved tags; submit or refresh both Packagist packages; create both GitHub prereleases; and announce Alpha 2 only after the clean public-installation proof succeeds. The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence attached to the release work item. If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.

## Approved Alpha 3 identity

The accountable human approved the following release identity and gated publication sequence on 2026-07-21 (Asia/Manila):

- Composer version: `0.1.0-alpha.3`
- Framework tag: `v0.1.0-alpha.3`
- Skeleton tag: `v0.1.0-alpha.3`
- Release notes: `docs/releases/0.1.0-alpha.3.md`

This approves the exact version and tag names and authorizes the following ordered operations only after their preceding gates pass: commit and push the framework candidate; create and push the framework tag; submit or refresh the framework Packagist package and verify its distribution; update, prove, commit, push, and tag the dedicated skeleton; submit or refresh the skeleton Packagist package and verify its distribution; prove the clean public installation path; create both GitHub prereleases; and announce Alpha 3. The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence attached to the release work item. If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.

## Approved Alpha 4 identity

The accountable human approved the following release identity and gated publication sequence on 2026-07-23 (Asia/Manila):

- Composer version: `0.1.0-alpha.4`
- Framework tag: `v0.1.0-alpha.4`
- Skeleton tag: `v0.1.0-alpha.4`
- Release notes: `docs/releases/0.1.0-alpha.4.md`

This approves the exact version and tag names and authorizes the following ordered operations only after their preceding gates pass: commit and push the framework candidate; create and push the framework tag; submit or refresh the framework Packagist package and verify its distribution; update, prove, commit, push, and tag the dedicated skeleton; submit or refresh the skeleton Packagist package and verify its distribution; prove the clean public installation path; create both GitHub prereleases; and announce Alpha 4. The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence attached to the release work item. If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.

## Approved Alpha 5 identity

The accountable human approved preparation of the following bounded release scope and exact identity on 2026-08-01 (Asia/Manila):

- Composer version: `0.1.0-alpha.5`
- Framework tag: `v0.1.0-alpha.5`
- Skeleton tag: `v0.1.0-alpha.5`
- Release notes: `docs/releases/0.1.0-alpha.5.md`

This approval authorizes source preparation and local verification only. It does not authorize committing or pushing the candidate, creating or pushing either tag, changing the dedicated skeleton repository, submitting or refreshing either Packagist package, creating either GitHub prerelease, or announcing Alpha 5. Those external operations require later explicit accountable-human authorization after the candidate evidence is reviewed. The exact candidate commits, release date, artifact references, and gate evidence belong in the external release evidence attached to the release work item. If any mandatory check fails, the next external operation remains unauthorized until a new candidate passes.

## Reusable release state model

Keep these four states distinct:

1. **Latest recorded release:** Alpha 5 is the latest immutable release identity and tag known to the repository source record. Its exact tagged source, release notes, scope decision, and external release evidence remain historical. Inspect that release from `v0.1.0-alpha.5`, not from mutable files on a later `main`; verify GitHub and Packagist state separately.
2. **Unreleased `main`:** commits after the latest recorded tag are unreleased source. They are neither part of Alpha 5 nor an approved next candidate, and they establish no external publication state.
3. **Proposed next candidate:** a maintainer may assess the unreleased delta and draft a bounded scope, version, tags, notes, and announcement. A proposal authorizes no candidate identity, tag, package, repository update, GitHub release, or announcement.
4. **Approved candidate:** only an explicit accountable-human record may approve the exact version, framework and skeleton tags, framework candidate commit, planned release date, bounded scope, release notes, candidate-specific announcement text, and each authorized next operation. The skeleton candidate commit may remain explicitly `PENDING` until the dedicated skeleton is updated and proved, but it must be recorded and approved before any skeleton tag or package write. Record the framework and skeleton candidate approvals plus evolving evidence in the external release work item so proof does not require modifying either candidate commit. Keep the planned release date distinct from the observed timestamp of every external publication operation.

Authorization is enumerable, not implied by reaching a checklist step. Record separately whether the accountable human authorizes candidate preparation; framework commit and push; framework tag creation and push; framework Packagist update; skeleton commit and push; skeleton tag creation and push; skeleton Packagist update; either GitHub prerelease; and the final announcement. An earlier scope, candidate, or publication approval does not authorize a later operation unless that exact operation is named.

Preparing a proposal, proving or publishing an approved candidate, and inspecting an older release are different tasks. Preparation starts from the exact diff after the latest recorded tag and stops before external writes. Proof and publication use only the approved candidate record in the external release work item. Historical inspection uses the exact requested tag and its tagged notes and scope decision; current `main` may contain later clarifications and is not historical release evidence.

## Version-neutral release gate

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
- [ ] `composer test:consumer` builds the release archive, matches the complete Composer and Git export inventory, installs a mirrored package into a clean skeleton, and passes its adversarial controls.
- [ ] The framework archive contains exactly `tools/package-files.txt`; `bin/phpthis` remains executable.
- [ ] Release notes name the supported surface, exclusions, known limitations, and any breaking change without claiming evidence the candidate does not have.
- [ ] After the complete local gate passes, confirm the authorization record permits pushing the exact framework candidate commit, push it without modification, and record the pushed commit.
- [ ] GitHub CI passes both the PHP 8.4 validity job and the SQLite/MySQL/PostgreSQL PDO transport job for that exact pushed candidate commit.

### 3. Publish the framework prerelease

- [ ] Confirm the authorization record names framework tag creation and push and the framework Packagist update as separate permitted operations against the exact proved framework candidate commit.
- [ ] Create the approved framework prerelease tag from the proven commit without moving or reusing an existing tag, then push that exact tag to the approved remote.
- [ ] Submit or refresh `phpthis/framework` on Packagist and wait until the exact prerelease is indexed with a preferred distribution artifact.
- [ ] Record the framework tag, commit, Packagist version, distribution reference, and observed timestamp and result of each framework publication operation in the release evidence.

At the end of this step, record the framework side as published but the overall release as partial and unproved until Steps 4 and 5 pass. Do not describe or announce the complete release yet.

### 4. Publish the skeleton prerelease

- [ ] Confirm the authorization record permits preparing, proving, committing, and pushing the dedicated skeleton candidate. That preparation authority does not authorize a skeleton tag or package update.
- [ ] Export the contents of `skeleton/` as the root of its dedicated repository; do not publish it as a nested directory of the framework package.
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
Generated application check result:
Loopback health result:
Candidate-specific announcement reference:
Accountable-human authorization records by exact operation:
Partial-publication state or NOT_APPLICABLE:
Known limitations:
```

Do not store tokens, credentials, signing material, private package-host data, or production payloads in release evidence.
