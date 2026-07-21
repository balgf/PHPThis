# ADR 030: Report-only consumer duplication advisory

Status: accepted

## Context

Repeated application code can indicate a missed named operation or an inconsistent repair surface, especially when an AI authors most changes. Mechanical deduplication is not inherently safer, however. Explicit SQL, deliberately unrolled bounded operations, security sequencing, and independent behavior proofs may repeat for valid reasons. Treating similarity as program invalidity would pressure an AI to create generic mechanisms or hide important differences.

The checker already owns one deterministic manifest of application PHP. A useful duplication signal must use that same boundary, remain finite on adversarial input, disclose no PHP source-token content, and be incapable of weakening or replacing structural checks, PHPStan, or application behavior tests.

## Decision

After structural guardrails pass, `phpthis check` gives `ApplicationDuplicationScanner` the source captured while reading the exact discovered application manifest. The scanner does not walk the filesystem. The resolved Composer dependency directory, installed framework, version-control metadata, symlinks, unsupported suffixes, and every unconventional or extensionless application path therefore retain the checker's existing ownership decision.

The scanner lexes PHP without parser flags. It removes ordinary `<?php` opening tags, whitespace, comments, and docblocks; fixed PHP grammar tokens are compared by token identity, while identifiers, variable names, numeric and string literals, heredoc labels and content, and inline HTML retain their exact bytes. A possible group requires at least 48 consecutive normalized tokens. SHA-256 indexes candidate windows only. Every candidate and maximal extension is compared token-for-token before it can be reported. Overlapping windows on the same alignment consolidate into one maximal group, identical copies across several locations share one group, and overlapping same-file occurrences are rejected while separated same-file copies remain eligible.

The first version has these fixed bounds:

- at most 4,096 manifest entries are inspected;
- at most 32 KiB of source per file and 2 MiB in total are accepted for tokenization;
- at most 16,384 normalized tokens per file, 24,000 normalized tokens in total, and 24,000 windows are retained;
- a candidate digest retains at most four exact token variants;
- at most 2,048 maximal-extension attempts, 1,000,000 exact token comparisons, 256 groups, 256 locations per group, and 32,768 containment-propagation checks are evaluated; and
- debug output shows at most ten groups, eight locations per group, and 160 source-path bytes per location before JSON-safe truncation.

Reaching any bound produces one deterministic incomplete-scan advisory. A failure thrown while generating or writing the report after source collection produces one fixed unavailable advisory without exception details. Neither case changes application validity or prevents PHPStan from running.

A complete no-match scan prints one concise pass line. A possible match prints one concise normal-mode advisory stating that application validity is unaffected and directs detailed review to `phpthis check --debug`. Debug details contain only JSON-safe application-relative filenames, line ranges, normalized token counts, group counts, and truncation state. They never contain PHP source snippets or normalized token text—including source-level symbols, identifiers, literals, and comments—and they omit fingerprints, hashes, absolute paths, and exception text. Since debug intentionally reveals relative filenames and line topology, application paths must not contain secrets or customer data.

This advisory has no `PHT` identifier, baseline, suppression, ignore path, configuration file, score, automatic refactor, or validity effect. The canonical application gate continues from it into PHPStan and then the application-owned behavior tests. A human reviews each possible group in context. Explicit SQL, unrolled finite operations, security steps, and independent proofs are not presumed defective and must not be generalized merely to silence the report.

## Consequences

Applications receive a bounded review lead without gaining another definition of valid code. Normal checks remain concise; detailed paths appear only when explicitly requested. The direct scanner suite locks threshold behavior, comment and whitespace normalization, overlap and nested-copy consolidation, equal and unequal periodic copies, deterministic ordering, output truncation, path escaping, PHP source-token-content non-disclosure, resource saturation, and selected near-limit cases within a 64 MiB subprocess budget. The isolated installed-consumer proof locks dependency and version-control exclusion, unconventional locations, unchanged structural and PHPStan failures, exit zero for duplication alone, continuation through the behavior-test stage, and PHPStan continuation after a deliberately incomplete bounded scan.

This exact-token first pass deliberately misses copied blocks below 48 tokens, renamed identifiers or literals, reordered or semantically equivalent code, non-PHP duplication, and files skipped after a resource bound. It may report intentional boilerplate or deliberately explicit code. The output is evidence for review, not a refactoring instruction or a claim that the application violates DRY.

## Reconsider when

Independent consumer feedback shows a stable false-positive or false-negative pattern that a deterministic bounded lexical rule can improve without introducing configuration, suppression, source disclosure, or a generic abstraction. Promoting any duplication finding into program validity requires a separate accountable-human decision, diagnostic design, passing and failing fixtures, migration analysis, and consumer evidence; this advisory does not pre-authorize that change.
