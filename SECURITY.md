# Security policy

## Project status

PHPThis is experimental prerelease software and is not intended for production use. Alpha 7 and `v0.1.0-alpha.7` are the latest immutable framework tag and source boundary. Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure. That recorded completion is not a production-support commitment. Pre-alpha and alpha revisions receive no production security support and have no guaranteed response-time or remediation SLA. Private vulnerability reports are still assessed on a best-effort basis.

Any approved prerelease candidate may be announced only after its complete public-artifact gate in [the release process](RELEASING.md) passes and both GitHub prereleases receive their exact separate authorizations. A partially published framework or skeleton remains unannounced until both packages, the clean public installation path, and the required GitHub prereleases are proved. This tracked policy does not prove continuing live publication state; verify that state from the exact tagged release, GitHub, and Packagist. Release preparation must confirm that GitHub private vulnerability reporting is enabled before a prerelease is announced.

## Reporting a vulnerability

Do not disclose a suspected vulnerability in a public issue or discussion.

Use GitHub's private vulnerability reporting from the repository's **Security** tab. Include the affected revision, impact, reproduction steps, and any suggested mitigation. Reports will be assessed privately before coordinated disclosure.

General hardening suggestions and non-sensitive security design discussions may use a normal GitHub issue.

## Framework supply-chain checks

The framework's `Security` workflow runs on pushes, pull requests, a weekly schedule, and manual dispatch. `Dependency audit` runs `composer audit --locked` over production and development dependencies without installing or executing them. `Workflow security` runs the pinned zizmor scanner against `.github`, including the framework workflows and Dependabot configuration, with its pedantic audits and online checks. Both jobs use read-only repository permissions; checkouts do not persist credentials. Findings and service failures fail the relevant job. Investigate the cause and rerun a transient failure; do not treat unavailable advisory data as a clean audit or bypass a required check. Scheduled failures require maintainer triage even when no code changed.

The existing `composer check` remains the complete framework validity gate. The network-dependent security jobs supplement it. An audit only covers known advisories at the time it runs; workflow analysis is static and cannot establish that an upstream action, runner, downloaded tool, or container is trustworthy. There are no repository scanner suppressions. Any proposed exception needs a specific finding, rationale, owner, expiry, and accountable-human review.

Action commits and CI service images are pinned to full SHAs and digests. Dependabot proposes weekly action and root Composer updates, with a seven-day cooldown for ordinary version updates. Security updates remain separately enabled and urgent fixes may be reviewed immediately. Every update requires review and the complete applicable gates; there is no automatic merge. The maintainer reviews the zizmor version alongside its action update because the explicit scanner input is not updated automatically. PHP 8.4 and Composer 2 continue to receive patch updates; an action pin does not freeze everything that action downloads.

Dependabot's Actions updater does not update the workflow's service-container digests. The maintainer must review the official Redis, MySQL, and PostgreSQL images at least monthly and when an advisory affects them, and propose digest changes in a reviewed PR. Keep the configured version range, both Redis services, and the exact database certification versions coherent; a server-version change also requires the database matrix, guard, and transport evidence to change together.

These controls cover the framework repository. They do not establish the dedicated skeleton repository's settings or add a security tool to consuming applications. [Issue #74](https://github.com/balgf/PHPThis/issues/74) records implementation and live settings evidence; the [release process](RELEASING.md) owns branch, tag, signing, account, and publication checks.

## PHP security-analysis coverage

PHPStan, the Strict Profile, and behavior tests remain useful evidence but are not a general taint analysis or vulnerability scan. [Psalm's taint analysis](https://psalm.dev/docs/security_analysis/) is a candidate for a separate bounded evaluation. That evaluation must map actual request sources through PHPThis's value and handler boundaries to SQL, HTML, filesystem, and process sinks, and prove detection and safe handling with adversarial fixtures before proposing a required job. Application-owned authorization and deployment policy still require consumer-specific review. No PHP taint-analysis coverage is claimed by this workflow-hardening change.
