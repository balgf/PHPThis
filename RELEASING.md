# PHPThis release process

Alpha 1 scope is accepted in `docs/decisions/018-bounded-alpha-1-release-scope.md`. Publication state is external: this reusable checklist intentionally does not claim whether Alpha 1 has been published.

This is the maintainer release gate, not an application programming manual. Copy the checklist into the release work item and attach evidence there. Keep this canonical checklist unchecked and reusable.

Tags, GitHub releases, repository creation, and Packagist publication are consequential external writes. Perform each only after explicit accountable-human authorization for that release. Do not announce a release until the post-publication proof passes.

## Approved Alpha 1 identity

The accountable human approved the following release identity on 2026-07-19 (Asia/Manila):

- Composer version: `0.1.0-alpha.1`
- Framework tag: `v0.1.0-alpha.1`
- Skeleton tag: `v0.1.0-alpha.1`
- Release notes: `docs/releases/0.1.0-alpha.1.md`

This approves the version and tag names only. The exact candidate commit, release date, and accountable-human publication authorization belong in the external release evidence attached to the release work item; embedding them in tracked release notes would change the candidate commit. This approval does not create or authorize creation of either tag, either package-host entry, either GitHub release, or the announcement. Alpha 1 must not be announced until the complete gate below passes, and its publication state must be verified from external evidence.

## Alpha 1 release gate

### 1. Freeze the release candidate

- [ ] Record the exact framework prerelease version, tag, candidate commit, release date, and accountable-human publication authorization.
- [ ] Confirm maintainer access to the intended GitHub repositories and Packagist package names `phpthis/framework` and `phpthis/skeleton`; do not infer availability from local package metadata.
- [ ] Confirm GitHub private vulnerability reporting is enabled for the public framework repository.
- [ ] Confirm the candidate scope matches ADR 018 and release notes do not imply production readiness, backward compatibility, complete CRUD, authentication, authorization, tenancy, or SQL dialect portability.
- [ ] Confirm the worktree is clean and the candidate commit is pushed.
- [ ] Review every public API, Consumer Contract version, Strict Profile version, permanent diagnostic identifier, and upgrade note changed since the previous release.
- [ ] Confirm `README.md`, `ROADMAP.md`, `SECURITY.md`, `docs/getting-started.md`, and the package metadata describe the same release state.

### 2. Prove the framework candidate

Run from the framework repository root:

```bash
composer validate --strict
composer install --no-interaction --no-progress --prefer-dist
composer check
```

- [ ] The complete local gate passes without a baseline, suppression, skipped required driver, or modified dependency source.
- [ ] GitHub CI passes both the PHP 8.4 validity job and the SQLite/MySQL/PostgreSQL PDO transport job for the candidate commit.
- [ ] `composer test:consumer` builds the release archive, matches the complete Composer and Git export inventory, installs a mirrored package into a clean skeleton, and passes its adversarial controls.
- [ ] The framework archive contains exactly `tools/package-files.txt`; `bin/phpthis` remains executable.
- [ ] Release notes name the supported surface, exclusions, known limitations, and any breaking change without claiming evidence the candidate does not have.

### 3. Publish the framework prerelease

- [ ] Create the approved framework prerelease tag from the proven commit without moving or reusing an existing tag.
- [ ] Submit or refresh `phpthis/framework` on Packagist and wait until the exact prerelease is indexed with a preferred distribution artifact.
- [ ] Record the framework tag, commit, Packagist version, and distribution reference in the release evidence.

At the end of this step, treat the public artifact and skeleton path as unproved until Steps 4 and 5 pass. Do not announce Alpha 1 yet.

### 4. Publish the skeleton prerelease

- [ ] Export the contents of `skeleton/` as the root of its dedicated repository; do not publish it as a nested directory of the framework package.
- [ ] Record the approved skeleton repository URL and confirm its package name remains `phpthis/skeleton`.
- [ ] Remove the pre-alpha VCS `repositories` override from the exported `composer.json`.
- [ ] Replace `phpthis/framework: dev-main` with the approved framework prerelease constraint resolved from Packagist.
- [ ] Run `composer update --prefer-dist` in the skeleton repository and commit the generated `composer.lock`.
- [ ] Confirm the lockfile resolves the exact approved framework prerelease through its distribution artifact.
- [ ] Compare the installed framework's complete relative file inventory with the release source's `tools/package-files.txt`, and confirm `vendor/bin/phpthis` is executable before tagging the skeleton.
- [ ] Run `composer validate --strict` and `composer check` from the skeleton root.
- [ ] Confirm skeleton CI installs locked dependencies, invokes the installed `phpthis check`, and runs the application behavior tests.
- [ ] Tag the proven skeleton commit, submit or refresh `phpthis/skeleton` on Packagist, and wait for indexing.
- [ ] Record the skeleton tag, commit, Packagist version, and distribution reference.

### 5. Prove the public distribution path

Use a new empty directory and normal Packagist resolution. Do not add a VCS repository override, path repository, local archive, symlink, or source-checkout fallback.

```bash
composer create-project --stability=alpha phpthis/skeleton phpthis-alpha-proof
cd phpthis-alpha-proof
composer check
```

- [ ] Composer resolves the approved `phpthis/skeleton` and `phpthis/framework` prerelease versions from Packagist-preferred distribution artifacts.
- [ ] The installed `vendor/phpthis/framework` relative file inventory exactly matches the release source's `tools/package-files.txt`, and `vendor/bin/phpthis` is executable.
- [ ] The generated application has no unresolved template token, no consumer PHPStan configuration or baseline, and a committed-lockfile-ready dependency graph.
- [ ] The installed framework profile and application behavior tests pass through the generated application's complete gate.
- [ ] The real front controller serves the exact documented `GET /health` response on a loopback-only local server.
- [ ] Record the clean environment, PHP version, Composer version, resolved package versions, distribution references, inventory result, complete-check output, and health result without secrets or local credentials.

### 6. Announce or stop

- [ ] Update mutable repository landing-page or announcement wording only after both packages and the clean public path are proven; keep tagged package authority independent of mutable publication state.
- [ ] Publish the approved GitHub prereleases for both proven tags without moving either tag.
- [ ] Publish the approved Alpha 1 announcement with direct links to both tagged packages, release notes, ADR 018, the security policy, and the installation command.
- [ ] Preserve the release evidence with the release work item.

If any mandatory check fails, stop the announcement and fix the cause on a new candidate commit. Never move a published tag or replace a published artifact. When a public prerelease is defective, document it, mark it appropriately in the package host, and publish a new prerelease version after the complete gate passes.

## Evidence record

Record at least:

```text
Framework version/tag:
Framework commit:
Framework Packagist distribution reference:
Framework release URL:
Skeleton version/tag:
Skeleton commit:
Skeleton Packagist distribution reference:
Skeleton release URL:
Candidate CI URLs:
Public-proof date and environment:
Inventory result:
Generated application check result:
Loopback health result:
Accountable-human publication authorization:
Known limitations:
```

Do not store tokens, credentials, signing material, private package-host data, or production payloads in release evidence.
