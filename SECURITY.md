# Security policy

## Project status

PHPThis is experimental prerelease software and is not intended for production use. Alpha 6 and `v0.1.0-alpha.6` are the latest immutable framework tag and source boundary and the latest complete coordinated framework, skeleton, and public-install release. Issue #37 records the exact framework and skeleton candidates, both tags and packages, clean exact `create-project` proof, both GitHub prereleases, and announcement as complete. ADR 054 and Issue #53 record the approved Alpha 7 identity and source-preparation scope only; both exact candidate commits remain `PENDING`, and no commit, push, tag, package, dedicated-skeleton change, release, announcement, issue closure, or production mutation is authorized. Neither boundary is a production-support commitment. Pre-alpha and alpha revisions receive no production security support and have no guaranteed response-time or remediation SLA. Private vulnerability reports are still assessed on a best-effort basis.

Any approved prerelease candidate may be announced only after its complete public-artifact gate in `RELEASING.md` passes. A partially published framework or skeleton remains unannounced until both packages and the clean public installation path are proved. This tracked policy does not record current publication state; verify that state from the exact tagged release, GitHub, and Packagist. Release preparation must confirm that GitHub private vulnerability reporting is enabled before a prerelease is announced.

## Reporting a vulnerability

Do not disclose a suspected vulnerability in a public issue or discussion.

Use GitHub's private vulnerability reporting from the repository's **Security** tab. Include the affected revision, impact, reproduction steps, and any suggested mitigation. Reports will be assessed privately before coordinated disclosure.

General hardening suggestions and non-sensitive security design discussions may use a normal GitHub issue.
