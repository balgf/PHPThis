# ADR 059: Bounded application source-prefix discovery

Status: accepted

## Context

ADR 010 requires one framework-owned consumer check whose discovered application manifest drives structural checks and PHPStan. The current application contract already requires PHP source to use the `.php` extension, with a narrow allowance for extensionless files whose first bytes are either lowercase `<?php` or the exact `#!/usr/bin/env php` launcher followed immediately by lowercase `<?php`.

`ApplicationChecker::discoverPhpFiles()` nevertheless used one narrower 128-byte test for both extensionless admission and unsupported-suffix rejection. It recognized only those canonical spellings at byte zero. PHP can execute a long opening tag after a UTF-8 BOM or leading ASCII whitespace, the long tag is case-insensitive, and the short echo tag `<?=` is always available. An application-owned `.inc` file with one leading LF therefore escaped the syntax profile, duplication review, PHT007 manifest-wide checks, and PHPStan even though `require` executed its PHP.

Searching every byte of every non-PHP artifact for PHP-looking text would create an unbounded content scan and would misclassify ordinary Markdown, HTML, XML, and source examples. Reading through a symlink to decide whether its target looks like PHP would make discovery depend on content outside the owned application tree. The discovery boundary needs one exact bounded and fail-conservative rule without claiming to identify arbitrary mixed-content PHP.

On 2026-08-25 in Asia/Manila, the accountable human approved the exact prefix grammar, the 4,096-byte bound plus one-byte lookahead, conservative ambiguity and unreadability failures, the all-application-symlink rejection, and Consumer Contract version 16. The same instruction separately authorizes committing and pushing this accepted source patch and closing Issue #60. It selects no release identity and authorizes no tag, package, GitHub release, announcement, or other publication operation.

## Decision

`phpthis check` keeps one deterministic application source manifest:

- every owned regular file whose extension compares case-insensitively as `.php` enters the manifest regardless of its first bytes;
- the only admitted extensionless forms remain the existing canonical forms: byte-zero lowercase `<?php`, or byte-zero exact `#!/usr/bin/env php`, one PHP-PCRE newline sequence, and immediate lowercase `<?php`. In both forms the long tag is followed by EOF or ASCII HT, LF, CR, or space;
- every other regular file has at most its first 4,096 bytes classified, with one additional lookahead byte read only to resolve whether a possible prefix at the inspection boundary is recognized, disproved, or still possible;
- the broader direct detection prefix is one optional UTF-8 BOM, followed by zero or more ASCII HT, LF, VT, FF, CR, or space bytes, followed by either case-insensitive `<?php` with the long-tag boundary above or `<?=`;
- the broader launcher detection prefix is byte-zero exact `#!/usr/bin/env php`, one PHP-PCRE newline sequence, then one optional UTF-8 BOM and zero or more ASCII preamble-whitespace bytes, followed by case-insensitive `<?php` with the long-tag boundary above or `<?=`. It accepts no BOM or whitespace before the launcher;
- a broadly detected prefix under any non-`.php` extension fails with one application-relative diagnostic and no source bytes. A broadly detected but noncanonical prefix in an extensionless file also fails and directs the consumer to a canonical prefix or `.php`;
- when a continuing file reaches the inspection boundary while its bytes still form only a possible direct or post-launcher preamble or partial detected tag, discovery fails as ambiguous. The terminal repairs are to use `.php` for PHP source, use an exact canonical byte-zero prefix for extensionless PHP, or put decisive non-PHP content within the bound;
- when a non-`.php` regular file's bounded prefix cannot be read, discovery fails with one fixed application-relative diagnostic rather than silently treating the file as non-PHP; and
- the resolved Composer dependency directory and version-control directories remain excluded before source inspection. Every other symlink entry in the application tree fails before prefix inspection; the checker neither reads target content nor traverses a linked directory.

The classifier does not search after arbitrary prose, HTML, XML, a non-UTF-8 byte-order mark, an alternate launcher, or a configuration-dependent short `<?` tag. Those are not accepted ways to hide application PHP. Documentation and examples with PHP-looking text after decisive non-PHP content remain ordinary non-source artifacts. Applications must not execute non-`.php` mixed-content files and must not treat this prevention guard as an adversarial content scanner.

The sorted manifest continues to drive the syntax profile, PHT007 and the other structural checks, the bounded report-only duplication advisory, and the temporary framework-owned PHPStan configuration. Structural checks and duplication still reuse one captured source read after discovery. Discovery never executes a candidate file.

## Compatibility migration

Consumer Contract version 16 carries version 15 and Strict Profile version 4 forward. Permanent diagnostics PHT001 through PHT008 remain unchanged. Detecting executable prefixes under unsupported suffixes repairs enforcement of the existing source-naming contract, but the complete fail-closed boundary also rejects some project shapes that Contract version 15's checker allowed: a non-source application symlink, a continuing non-PHP file whose bounded start remains a possible source prefix, or an unreadable non-`.php` regular file. That compatibility change requires a new Consumer Contract version even though it accepts no additional PHP program shape and adds no `PHT` diagnostic.

Before adopting Contract version 16, an application must:

1. Inventory every symlink outside the resolved Composer dependency directory and version-control directories. Replace each with an application-owned regular file or real directory, or move genuinely dependency-owned material behind the resolved dependency boundary; do not depend on checker traversal or target-content inspection.
2. Give every application PHP file the `.php` extension unless it begins with one of the two exact canonical extensionless prefixes. Replace unsupported suffixes and noncanonical extensionless prefixes rather than adding an ignore.
3. Make every non-PHP regular file readable. When its first 4,097 bytes could remain a source preamble at the decision boundary, put decisive non-PHP content within the first 4,096 bytes or relocate the artifact outside the application tree under an already excluded ownership boundary.
4. Run the complete application gate on PHP 8.4.x and retain application tests for every executable entrypoint and any migrated asset or generated-file path.

A later prerelease containing this decision must identify the new all-application-symlink, ambiguous-prefix, and unreadable-prefix failures plus the migration above as an intentional prerelease compatibility break. This decision selects no release identity and authorizes no tag, package, release, or announcement.

## Evidence and limits

Installed-consumer evidence must prove direct, UTF-8-BOM, BOM-plus-whitespace, all-six-leading-ASCII-whitespace, case-variant, `<?=`, post-launcher BOM/whitespace, exact-boundary, over-limit ambiguous, partial-prefix, 4,096-byte whitespace EOF, 4,097-byte whitespace ambiguity, unreadable-prefix, and disproved-lookahead controls. It must retain the exact direct and launcher extensionless forms, reject noncanonical direct and launcher extensionless prefixes, ignore prose-first Markdown, exclude the resolved dependency and VCS directories, walk hidden and unconventional application directories, and reject linked directories plus canonical-source, detected-source, broken, and ordinary non-source file symlinks with fixed no-follow diagnostics. Normal and assertion-failure paths restore every created fixture in `finally`.

The proof invokes only the installed public checker. A source canary and filesystem sentinel prove that discovery neither discloses source bytes nor executes a candidate file. The evidence does not prove detection after decisive non-PHP content, alternate launcher behavior, non-UTF-8 BOM handling, configuration-dependent short-tag behavior, hostile concurrent filesystem mutation, or release-archive parity from a dirty worktree.

Because the current starter authority advances to Consumer Contract version 16, the maintainer-only `change.simple-ping` evaluation task advances from revision 22 to revision 23 and pins the resulting source-skeleton Git tree and fixture digest. Its prompt, rubric, scorer, workspace policy, budgets, and non-comparative boundary are unchanged. This fixture maintenance records no model result and makes no comparative claim.

## Consequences

The common accidental suffix bypass closes with fixed per-file prefix work. A non-PHP artifact whose inspected start remains indistinguishable from a detected source preamble can now fail conservatively; its diagnostic states finite repairs instead of guessing its content. A consumer using application-tree symlinks must replace them before adopting Contract version 16.

The application manifest is complete for contract-shaped `.php` and canonical extensionless source, not for arbitrary files that a consumer might execute contrary to the contract. This decision adds no runtime API, dependency, configurable ignore, baseline, second manifest, new PHPStan configuration path, `PHT` diagnostic, Strict Profile version, source execution, dependency traversal, VCS traversal, or symlink following.

## Reconsider when

A real consumer needs an application-owned executable form that cannot use `.php` or either canonical extensionless prefix; a legitimate application ownership model requires symlinks; ordinary non-source artifacts repeatedly hit the ambiguity bound; PHP changes the portable opening-tag grammar; or a bounded parser can distinguish mixed executable content from documentation without scanning arbitrary file bodies or introducing consumer configuration.
