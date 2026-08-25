# Security policy

## Project status

PHPThis is experimental prerelease software and is not intended for production use. Alpha 7 and `v0.1.0-alpha.7` are the latest immutable framework tag and source boundary. Closed Issue #53 records Alpha 7 as the latest completed and announced coordinated release, including both GitHub prereleases, the final announcement, and Issue closure. That recorded completion is not a production-support commitment. Pre-alpha and alpha revisions receive no production security support and have no guaranteed response-time or remediation SLA. Private vulnerability reports are still assessed on a best-effort basis.

Any approved prerelease candidate may be announced only after its complete public-artifact gate in [the release process](RELEASING.md) passes and both GitHub prereleases receive their exact separate authorizations. A partially published framework or skeleton remains unannounced until both packages, the clean public installation path, and the required GitHub prereleases are proved. This tracked policy does not prove continuing live publication state; verify that state from the exact tagged release, GitHub, and Packagist. Release preparation must confirm that GitHub private vulnerability reporting is enabled before a prerelease is announced.

## Reporting a vulnerability

Do not disclose a suspected vulnerability in a public issue or discussion.

Use GitHub's private vulnerability reporting from the repository's **Security** tab. Include the affected revision, impact, reproduction steps, and any suggested mitigation. Reports will be assessed privately before coordinated disclosure.

General hardening suggestions and non-sensitive security design discussions may use a normal GitHub issue.
