# Comparison fixture revision 6

The visible-workspace-policy calibration still recorded whole-file rereads after passing checks, a broad diagnostic-reference inventory, and no separate supplemental PHPDoc read. Revision 6 clarifies the existing workflow at the point where each instruction is needed. It does not establish improved model behavior or reduced token use.

All six fixtures receive the same changes in two protected guides:

- `.ai/change-workflow.md` keeps the diagnostic tree out of general inventories and routes lookup through the exact identifier. Completion uses already-known changed file paths; line numbers are optional, and a needed location uses a targeted symbol lookup. Whole-file rereads solely for final citations are discouraged.
- `.ai/testing.md` routes typed helper authoring directly to `docs/phpstan/phpdoc-types.md`, starting with “Local type aliases” and adding the relevant array sections only when used. This read applies before authoring, even when no diagnostic has appeared.

The complete task, API, mandatory authority, and validity gate still govern completion. Changed code and named unresolved risks still require appropriate review and checks. Unknown or unavailable diagnostic references remain a stop condition; network access and suppressions remain forbidden.

All three task revisions advance from 5 to 6 with refreshed fixture and source-context hashes. The shared reference bundle stays byte-identical, including all official payloads, its README, and provenance inventory. Fixture PHP bytes and modes, functional prompts, rubrics, workspace policies, private holdout identities, dependencies, schemas, comparison order, calibration suffixes, and budgets remain unchanged. Earlier preparations and campaigns retain their original identities.

Verification resolves the direct supplemental route in all six fixtures and rejects its removal. Fresh offline preparation compares source bytes/modes and public observations against revision 5 and runs all six canonical application gates and typed-helper checks. Prior malformed-transport evidence remains attached to its unchanged source. The full repository `composer check` remains required. These checks establish preparation readiness; any further paid calibration needs its own recorded authorization and later human review.
