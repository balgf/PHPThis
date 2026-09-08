# Offline diagnostic reference

Before fixing a diagnostic, read this README once, then the exact reference below. This is the authorized offline equivalent of fetching diagnostic documentation. Keep the network disabled.

For an upstream PHPStan identifier, read `docs/phpstan/<identifier>.md` from the candidate root. Use the identifier exactly as reported; no catalog search or full `sources.json` read is required. The bundle contains all 1,109 authored diagnostic pages at the pinned commit: all 1,105 official catalog identifiers and four additional diagnostic pages.

If the exact reference is unknown, missing, unreadable or fails its recorded integrity check, stop and report the identifier and unavailable reference. Do not invent a replacement, fetch a website, waive the requirement or edit this protected bundle. A maintainer must supply the reference outside the run.

## PHPThis-owned diagnostics

When PHPThis is installed, `phpthis.pht001`, `phpthis.pht005`, `phpthis.pht006` and `phpthis.pht008` belong to its installed `vendor/phpthis/framework/docs/strict-profile.md`. Read the matching `PHT001`, `PHT005`, `PHT006` or `PHT008` entry and relevant section there before fixing it. These are not upstream PHPStan identifiers. An absent owner/reference or any other unknown identifier remains a stop condition; the plain PHP condition has no PHPThis extension reference.

## Repair constraints

The pinned analyzer and actual producing/consuming code remain authoritative. Preserve behavior and truthful types. Upstream ignore examples and metadata do not authorize ignores, exclusions, baselines, inline `@var` overrides, `assert()` type overrides, analyzer/profile changes, added dependencies, or casts/type widening merely to silence a diagnostic. Run `composer check` after the fix.

For typed test helpers, locate and read only the relevant sections of `phpdoc-types.md`: “Local type aliases”, “Array shapes”, “General arrays” or “Lists”. Its links and other upstream links are not required transitive reads or permission to fetch more material. Report any genuinely required reference outside this bundle.

## Provenance

Diagnostic pages, `phpdoc-types.md` and `LICENSE` preserve official MIT-licensed source bytes at `phpstan/phpstan` commit `d8ed7dd9d5ccfc37324392be86f3ac6d79effd52`. `sources.json` records exact URLs, Git blobs, sizes, SHA-256 hashes and complete coverage. It is an audit inventory, not a mandatory context dump. `CLAUDE.md` is upstream maintainer guidance and is excluded. This is a documentation snapshot, not a claim of exhaustive parity with a particular PHPStan release or every extension. All six fixtures receive identical bundle bytes.
