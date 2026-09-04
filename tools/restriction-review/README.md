# Restriction costs and maintenance outcomes

Status: completed bounded investigation; recommendations and proposed decisions below are **not accepted policy changes**.

Review date: 2026-09-05, Asia/Manila. Source baseline: `de7353ec2ff881cd85392dd59a6bd08ab7e9d64f` (unreleased Consumer Contract 18 / Strict Profile 4). Scope: [Issue #67](https://github.com/balgf/PHPThis/issues/67). Only this framework repository, its recorded public evidence, and disposable synthetic fixtures were inspected. No separate consumer, private application, chat history, or model evaluation was inspected or run.

## Recommendations

| Item | Recommendation | Reason and present boundary |
| --- | --- | --- |
| Markdown/PHP file ratio | **Revise, by a proposed decision:** replace the hard ratio with a documentation review trigger and task-outcome evidence. | The actual guard can be satisfied by an empty Markdown file without improving coverage. Keep the existing guard until that decision is accepted; this report neither removes it nor endorses padding. |
| 2,620-line core ceiling | **Retain for now; clarify its claim.** | It makes runtime growth visible and has an explicit accepted scope. It is not a correctness or whole-application complexity measure. Formatting pressure alone does not establish a better replacement. |
| PHT007 / unknown prefixed environment names | **Clarify the conflict; defer migration or rule expansion.** | Exact-key access and one-file ownership remain useful. The recorded consumer policy cannot be preserved simply by deleting enumeration. No checker defect is demonstrated by that rejection. |
| Complete statements and no SQL/binding helpers | **Retain; clarify which restriction owns which evidence.** | The statement family exposes authority and cost but requires repeated repair sites. Existing behavior tests catch a binding mistake that the static rule does not. No tested equivalent helper or evidence for relaxing the boundary exists here. |

These conclusions preserve strict types, explicit composition and I/O, finite parameterized SQL, and every current check. Wider consumer outcomes would improve confidence; they are not required to investigate the reproduced limits now.

## What is being measured

A **validity guarantee** names the property actually checked, such as exact-key environment reads or finite SQL constants. An **architectural preference** chooses where behavior and authority remain visible. A **growth proxy** counts artifacts without establishing their meaning. These categories can coexist in one rule.

Use the following failure classification:

- **Checker false positive:** source satisfies the documented accepted rule but the checker rejects it. None was demonstrated in these experiments. Documented conservative cases are not evidence that the environment conflict is such a bug.
- **Intentional unsupported capability:** the accepted rule excludes a PHP capability, such as zero-argument environment enumeration. The checker correctly rejects that capability.
- **Application-policy conflict:** an application requires behavior excluded by the framework boundary. A checker-passing edit that removes that behavior is a policy change, not an equivalent repair.
- **Coverage limit:** a passing check does not establish an unclaimed property. PHT008's lack of binding-array validation is such a limit; the SQL experiment below uses behavior tests to expose its consequence.

The measurements below are fixed-case observations, not causal estimates of framework productivity. No alternative framework, model, production workload, reviewer timing, or provider token telemetry was measured.

## 1. Markdown files outnumber PHP files

**Current owners.** [VISION.md](../../VISION.md) states the rule; [the guardrail catalogue](../../docs/guardrails.md) documents it; `distributionGuardrailFailures()` in [distribution.php](../guardrails/distribution.php) enforces `count($markdownFiles) > count($phpFiles)`. The policy is already present in initial source commit `ed1e4234a0bfac57fd09c0dd396c91c59a834f15`. No dedicated accepted ADR giving a quantitative rationale for this ratio was found in the decision inventory. [ADR 011](../../docs/decisions/011-ai-first-authoring.md) owns the broader AI-first knowledge interface, not a demonstrated causal relationship between file ratio and review quality.

**Intended prevention.** Read as a growth proxy, the rule discourages framework expansion without accompanying knowledge artifacts. That is an inference from the vision and guard, not measured evidence that the chosen ratio prevents undocumented behavior.

**Actual scope.** The guard scans repository files, excludes `vendor/` and `tmp/`, counts `.md` and `.php` files plus `bin/phpthis`, and does not check whether a counted document covers a counted PHP file. Templates, skeleton, examples, tests, and maintainer tools influence the totals. This is a maintainer gate, not a ratio required of an installed application.

**Reproduction.** `python3 tools/restriction-review/reproduce.py` uses the exact baseline in a temporary directory and invokes the unchanged real guard:

| Case | Markdown / PHP | Core lines | Guard result |
| --- | --- | ---: | --- |
| Baseline | 250 / 239 | 2,618 | Pass |
| Add 11 inert PHP files containing only the opening tag and strict-types declaration | 250 / 250 | 2,618 | Fail: `Markdown files (250) must outnumber PHP files (250).` |
| Add one zero-byte Markdown file | 251 / 250 | 2,618 | Pass |

The inert controls add 11 files and 33 PHP lines outside core. The final change adds one file and zero documentation bytes. This is a sensitivity test, not a suggested repair or a claim that a real feature needs eleven files. Conversely, consolidating related documentation can worsen the ratio without deleting any knowledge. That last possibility follows from the count definition; it was not a separately executed consolidation experiment.

**Proposed decision R1 — not accepted.** Stop making the raw file ratio a validity failure after an accountable maintainer accepts a replacement documentation-review process. Keep the counts visible as a change-review prompt, alongside named evidence for new behavior, current-owner routing, and link/distribution checks. Do not replace it with another universal ratio, token ceiling, coverage percentage, or automated prose-quality claim.

Compatibility: no framework runtime or consumer API changes; maintainer `composer guard` outcomes would change for otherwise valid source below the ratio. Regression controls for a future implementation must prove that missing required contracts, broken current-owner routes, package omissions, invalid PHP, and behavior failures still fail. Demonstrate that adding empty Markdown cannot repair those failures. Compare retained-ratio and review-trigger approaches on several complete maintenance tasks before claiming reduced review effort. Acceptance, implementation, and any larger evaluation remain future work.

## 2. Physical core-line ceiling

**Current owners.** [ADR 049](../../docs/decisions/049-bounded-response-cookie-profile.md) accepts a narrowly scoped 2,620-physical-line ceiling for the 2,618-line response-cookie correction. [VISION.md](../../VISION.md) and [the guardrail catalogue](../../docs/guardrails.md) carry that boundary. The same distribution guard totals `count(file($path))` only for PHP files under `src/` and rejects values above 2,620. Blank lines and comments count; tools, verification, example, and consumer code do not.

**Intended prevention and existing evidence.** The cap makes additions to the core require explicit review and resists growth into implicit framework services. The accepted decision scopes the increase; remaining lines do not authorize new behavior. The evidence establishes a small counted runtime and its tests, not optimal complexity, readability, or application maintenance cost.

**Reproduction.** The optional reproducer adds three blank lines to `src/Application.php` in its disposable baseline, then restores it. The guard changes from pass at 2,618 to the sole failure `Core source has 2621 physical lines; the accepted response-cookie profile limit is 2620.` The significant PHP token sequence remains identical. This proves formatting sensitivity, not that an arbitrary refactor preserves behavior. The repair is deletion of the three blank lines in one file; no product behavior or document changes were needed.

A tracked-file inventory at the baseline illustrates the counting boundary:

| Source area | PHP files | Physical PHP lines |
| --- | ---: | ---: |
| Framework runtime `src/` | 33 | 2,618 |
| Checked application `example/` | 101 | 6,489 |
| Starter `skeleton/` | 16 | 1,554 |
| Verification implementation | 12 | 6,309 |
| Maintainer tests | 33 | 17,795 |
| Maintainer tools | 42 | 48,020 |
| Root autoloader and `bin/phpthis` | 2 | 54 |
| Total counted PHP | 239 | 82,839 |

These totals count committed files, including comments and blanks, without dependencies. They are not an application deployment size or a claim that the core cap caused code to move elsewhere. They show why core lines alone cannot describe the whole delivered maintenance surface. The example is a sanitized reference application, not an observed production consumer.

**Decision recommendation R2 — retain, not a replacement approval.** Keep the existing cap and scoped architecture review while recording significant behavior/API changes, direct dependency paths, complete task churn, and required repairs separately. For a necessary correctness or security fix that needs readable space, present a scoped budget decision and behavior evidence; do not compress code merely to fit. A review-trigger alternative could avoid formatting-only failures but would lose the automatic stop against unreviewed runtime growth. This review does not establish the governance reliability or outcome data needed to replace that stop.

Compatibility: retaining the guard changes no accepted program or runtime. Any future trigger proposal must identify who reviews runtime expansion and prove retained no-magic, explicit-I/O, API-surface, package, and behavior controls; compare fixes, additions, and refactors, including their application impact. A lower line count alone is not a successful outcome.

## 3. PHT007 and unknown-name rejection

**Current owners.** [ADR 036](../../docs/decisions/036-one-typed-application-configuration-boundary.md), [the current profile](../../docs/strict-profile.md), and [configuration guidance](../../docs/configuration.md) own direct `\getenv('EXACT_LITERAL_KEY')` access in one application-owned file and separately typed process configurations. [EnvironmentAccessProfile::inspect() and ::boundaryFailures()](../../verification/EnvironmentAccessProfile.php) implement the structural rule; [ApplicationChecker::check()](../../verification/ApplicationChecker.php) invokes it over application sources. [Existing profile fixtures](../test-strict-profile.php), [installed rejection controls](../test-consumer-project/profile-controls.php), and [typed-configuration proof](../test-consumer-project/configuration.php) supply positive and negative evidence.

**Intended prevention and limits.** The rule exposes names and centralizes reads before typed configuration enters behavior. It supports review of process/credential separation; it does not prove correct validation, least privilege, secret handling, launch-path coverage, or absence of every indirect read. The profile documents conservative and unrecognized forms explicitly.

[ROADMAP.md](../../ROADMAP.md) records the separate WebSocket consumer's policy conflict and unapproved real migration/proposed decision 002. That is existing source-recorded evidence. This review did not open that consumer or revalidate its historical 65-file/564-assertion exercise.

**Synthetic reproduction.** The optional reproducer compares a 22-line typed configuration boundary with a 27-line variant adding:

```php
foreach (\getenv() as $name => $unusedValue) {
    if (str_starts_with($name, 'ISSUE67_WS_') && $name !== 'ISSUE67_WS_MODE') {
        throw new InvalidArgumentException('Invalid synthetic configuration.');
    }
}
```

This deliberately rejected variant is a fixture, not valid application guidance. Both versions then read the exact known name and accept only the synthetic value `local`. Eight fresh processes receive fully specified synthetic environments; no host environment is enumerated by those processes. Outputs are only `accepted` or `rejected`, with empty stderr.

| Input | Exact reads: 0 PHT007 findings | Enumeration: 1 PHT007 finding |
| --- | --- | --- |
| Valid known input | Accept | Accept |
| Valid input plus an unknown prefixed name | Accept | Reject |
| Missing known input | Reject | Reject |
| Invalid known input | Reject | Reject |

The diagnostic points to the zero-argument `\getenv()` call. A separate two-file control produces two one-file-boundary findings. This executes the real PHT007 enforcement stage, not an entire installed application gate. The existing focused PHT007 fixture section additionally passed 38 assertions and 35 parseability checks during investigation; the complete framework gate remains separate.

The modeled repair is five deleted lines in one boundary file. It passes the structural rule **and loses unknown-name rejection**. No policy-preserving direct canonical repair was demonstrated. This is an intentional unsupported capability producing an application-policy conflict, not a checker false positive. `getenv()` obtains values as well as names, so an enumeration proposal cannot be described as names-only access merely because a loop ignores its values.

**Proposed decision R3 — clarify now, defer expansion.** State the incompatibility when evaluating such a migration. Retain PHT007; neither silently drop the application policy nor bypass the checker. An accountable decision must choose changed application policy, a separately evidenced launch-boundary mechanism, or a narrowly specified framework capability. This review accepts none of those options. Deployment-side rejection would need evidence for every launch path and is not shown equivalent here.

Compatibility of clarification: no new accepted syntax or changed checker result. A future rule expansion needs an explicit Contract/Profile migration and its accepted syntax, bounds, secrecy and ownership limits. Regression controls must retain exact-key acceptance, one-file confinement, missing/empty/invalid known-value behavior, unknown-name handling, separate profile inputs/no fallback, mutation/superglobal/callable rejection, value-free diagnostics, documented conservative cases, and installed-consumer/complete gates. Real-consumer migration effort and deployment coverage remain unmeasured.

## 4. Complete statements and repeated repairs

**Current owners and distinct scopes.** [The database guide](../../docs/database.md) and [ADR 014](../../docs/decisions/014-sql-data-and-finite-structure.md) require direct finite parameterized SQL and reject helpers that hide SQL or bindings; finite operation-local constant fragments are permitted, with complete statements preferred. [ADR 022](../../docs/decisions/022-application-owned-finite-data-paths.md) deliberately selects the stricter eight-complete-statement recipe for the checked document list. Do not describe that application choice as a universal ban on every constant fragment. [ADR 057](../../docs/decisions/057-distinct-named-sql-placeholder-occurrences.md) owns distinct placeholder occurrences and their explicit repair.

Enforcement is complementary: PHT003 rejects direct lexical database calls in loops; PHT006 establishes finite nonblank SQL constants; PHT008 checks distinct named occurrences; the database budget bounds statement count. The example's `document list source uses direct raw SQL without ORM binding or pagination helpers` test additionally pins its complete-statement shape. [ListDocumentsHandler](../../example/src/Documents/ListDocuments/ListDocumentsHandler.php) and [request-policy tests](../../tests/request-policy.php) expose authority predicates, order/filter choices, typed projection, pagination, and bounds. None of these checks alone establishes SQL meaning, matching bindings, plans, or production authorization.

**Real SQL maintenance case.** Commit `b5b71ef6289d32982b99b08c57315fe2864eaaec`, “Prove Alpha 2 consumer profile,” changes the existing Create path into account-scoped user creation. [ADR 029](../../docs/decisions/029-alpha-2-consumer-profile-rollup.md) accepts the example-domain requirement: associate the created user with its account while keeping user relationships separate from principal authorization. The maintained [TransactionalCreateUser](../../example/src/Users/CreateUser/TransactionalCreateUser.php) is byte-identical between that commit and the review baseline.

| SQL owner measure | Before → after |
| --- | ---: |
| Physical lines | 103 → 175 |
| Bytes | 3,368 → 7,125 |
| Direct complete statements | 3 → 4 |
| Explicit binding entries | 9 → 26 |

The owner diff is +83/-11 lines. All three existing statements changed and one `account_users` insert was added. The four statements spell out four requested/resolved-account equality predicates and two principal-membership predicates, with four bindings of the requested account, nine of the resolved tenant, and two of the principal. The restriction matters concretely: those predicates and bindings remain explicit beside each statement, so this scope change requires reviewing all four sites. The old statements need different edits: conditional user insertion, account-scoped event selection, and job publication changed from `VALUES` to account-scoped `SELECT`. The additional statement follows the accepted relation-table requirement; neither its I/O cost nor all 72 net owner lines can be attributed solely to the no-helper rule.

The complete commit touches 89 files (+1,840/-340): 38 PHP files (+1,602/-254), 46 Markdown files (+226/-80), and five other files. The PHP subsets include 28 application files (+287/-90), seven test files (+1,047/-118), one maintainer tool (+265/-43), and one core file (+1/-1). The core edit tightens a failure cache header; it adds no SQL mechanism. The task also includes capability rollup, runtime constraints, policy, migration, and evidence, so these are complete-task costs rather than SQL-only overhead. Nearest new evidence includes `tests/consumer-profile.php` (+664/-0), `docs/consumer-profile.md` (+49/-0), and ADR 029 (+50/-0).

Current [consumer-profile behaviors](../../tests/consumer-profile.php) check four statements and four committed effects on empty and 500-row fixtures; zero protected SQL for denial/invalid input; direct rejection of mismatched accounts and missing membership; and complete rollback after final job publication or budget failure. These existing behaviors run in the complete framework gate. No historical missed edit, repair iteration count, helper counterfactual, or comparative reviewer time is recorded for this task. The required coordinated edits are observed; their effect on human effort and defect rate remains unmeasured.

**A maintenance boundary with no repeated SQL edits.** Commit `d4ec6c3d281a02a918cc2fa8b4077aa45ebac6be`, “Document application-owned JSON success envelopes,” changes the document-list handler's two response paths, empty and nonempty, by +11/-3 lines without editing its eight SQL statements. The whole commit touches 30 files (+1,107/-54), including 18 PHP files (+1,062/-47) and 10 Markdown files (+41/-3). This provides a contrasting task shape: repeated SQL does not require every representation change to be repeated eight times.

The original finite-list implementation, `3f337be5de5631fee5dd08a204dd830fe67c23b6`, touched 64 files (+3,016/-135), including 35 Markdown files (+248/-32), 24 example PHP files (+852/-54), and two test files (+1,597/-31). It also carried other context/test organization work. Git rename records are counted once, using their destination paths; these subsets must not be added to the total again. All eight SQL bodies remain byte-identical from that introduction to the review baseline. The actual PHT008 commit, `c3b5d9dc5d9090a8d892e2c34cc5b4bbcf683b72`, changed no example SQL file. There is no measured historical repeated-placeholder migration pain in this application.

**Synthetic repair sensitivity.** Run `python3 tools/restriction-review/sql-maintenance.py`. It takes the already compliant baseline handler and deliberately reintroduces one repeated tenant placeholder in each statement, then models the migration required by ADR 057. It does not imply this application ever contained the synthetic defect.

The current handler is 474 lines / 23,275 bytes, with eight SQL bodies totaling 198 lines and 84 named placeholder occurrences. Repairing the modeled duplicate requires eight SQL renames and eight explicit binding additions in one PHP file: +16/-8 lines and no modeled Markdown edit. The observed extra repetition is local and inspectable; that does not show its human review cost or prove a helper would preserve visibility.

| Variant | PHT008 findings | Existing selected behaviors passing / failing | Interpretation |
| --- | ---: | --- | --- |
| Synthetic repeated tenant placeholder in all eight statements | 8 | 11 / 0 | SQLite happens to execute the rejected shape; runtime success does not make it valid PHPThis or portable. |
| Repair seven of eight statements | 1 | 11 / 0 | Static enforcement locates the missed occurrence even though these SQLite behaviors still pass. |
| Rename all occurrences but omit one new binding | 0 | 7 / 4 | Static success does not prove the parameter arrays match SQL. The affected SQLite branch returns an incorrect empty result; behavior tests detect it. |
| Fully repaired baseline | 0 | 11 / 0 | Both evidence layers pass for the exercised cases. |

The partial variant needs one more SQL rename and binding addition. The unbound variant needs its omitted binding restored. No suppression, relaxed level, or runtime helper is used. The four failing behaviors cover the eight-branch/empty-filter contract, cursor/lookahead traversal, scale invariance, and invalid stored-rank projection. Failures are retained in the script's result rather than omitted from a success score.

On PHP 8.4.19 / SQLite 3.51.2, the complete repair also preserves response-body hashes against the repeated/partial variants across 24 first/continuation page observations: two directions, four nonempty category shapes, and 3/500-row fixtures. Each observed nonempty-shape page uses one statement and returns at most 50 rows. Four explicit empty selections use zero statements and return zero rows. This compares exercised outputs, not query plans, concurrency, a snapshot-pagination guarantee, or another engine.

The script uses the unchanged maximum PHPStan profile, overriding only its temporary cache location. Four `.php.fixture` variants and two copied pinned test inputs run in temporary storage. The runtime includes 32 unchanged repository files (36 included files total with temporary inputs); these are executed dependencies, not a human or agent context-read count. It rejects tracked runtime/checker changes that would mix newer implementation with the pinned task and reuses the checkout's installed development dependencies. Historical commit statistics, modeled repair churn, and runtime results are emitted separately. The source-shape test is intentionally excluded from the 11 runtime behaviors because the negative fixtures violate that shape; the unchanged real source-shape test remains in the complete framework gate.

**Proposed decision R4 — retain and clarify.** Preserve direct finite SQL and visible bindings. Keep this eight-statement recipe and review each affected statement plus behavior case when a predicate or binding changes. No new helper, automatic statement generator, mandatory cross-application layout, or rule change is proposed here. ADR 022 already permits reconsideration if a statement family becomes too large to review safely, or independent applications establish a smaller equivalent contract. This bounded example has not established either result.

Compatibility: the recommendation changes no accepted source or public API. Any future abstraction proposal must show complete SQL/binding/authority visibility, supported-shape coverage, unknown-selector rejection, equivalent outputs and failure semantics, fixed I/O counts, engine-specific plans and authority evidence, and policy-preserving repairs across independent applications. Lower line count or fewer edit sites alone would not meet that bar.

## Complete-task outcome measures

For a future comparative evaluation, the proposed measures below keep each task and its failures separate instead of collapsing them into one score. They are not additional authoring requirements:

| Measure | Record | What this review establishes |
| --- | --- | --- |
| Correctness and authority | Exact task/revision, accepted behavior, denial and failure semantics, required checks and missed cases | Existing behavior evidence plus the bounded controls above; no production certification |
| Churn | Source/test/document additions and deletions, files changed, repeated edits and repairs | Counted per experiment/task; not a proxy for quality |
| Context and locality | Universal inventory separately; actual full/section reads, incidental search results, revisits, and omissions | The PHT007 investigation recorded 6 universal plus 15 focused paths; other complete read/time inventories are unmeasured |
| Duplication | Named repeated logic and binding sites; which edits must remain synchronized; behavior after a missed edit | Structural repetition can be located and a missed repair tested; no generic helper comparison |
| Review effort | Human minutes, navigation failures, defects found/missed, repair iterations under a fixed task | Human time, cognitive load, and comparative review effort are unmeasured |
| Operational cost | Statement/external-call counts plus rows, plans, memory, concurrency and failures on the actual engine | Existing fixed-statement SQLite evidence; broader workload and production costs unmeasured |
| Lifecycle | Clean install, real change, dependency upgrade, policy migration, recovery and retained failures | Existing source records only; no newly inspected complete independent consumer |

The PHT007 focused read inventory, beyond the six universal entrypoints, was `README.md`, `ROADMAP.md`, `composer.json`, `.ai/configuration.md`, `.ai/static-analysis.md`, `docs/configuration.md`, `docs/strict-profile.md`, `docs/consumer-contract.md`, ADR 036, `verification/EnvironmentAccessProfile.php`, `verification/ApplicationChecker.php`, `bin/phpthis`, `tools/test-strict-profile.php`, `tools/test-consumer-project/profile-controls.php`, and `tools/test-consumer-project/configuration.php`. Counts include deliberate section reads, not necessarily every byte of those files. Incidental search snippets were not completely inventoried. Snapshot preparation copies baseline files mechanically; that is not an agent context-read measurement.

The additional historical SQL search examined eight commits across four current operation paths: CreateUser, GetDocument, UpdateDocumentTitle, and ListDocuments. Focused account-maintenance reads included ADR 029, `.ai/consumer-profile.md`, the before/after transaction owner, and sections of consumer-profile documentation and tests. This records the bounded search that selected the real task; it is not an exhaustive repository-history or context-cost measurement.

Compare retaining the size guards with replacing them by review triggers using the same behavior, authority, failure, and lifecycle tasks. Retention offers a predictable stop but can produce artifact/formatting repairs without semantic benefit. Triggers permit judgment and consolidation but can miss growth unless reviews actually happen. Prefer observed missed defects, successful policy-preserving repairs, complete churn, and review effort over either raw size score. The comparative/context/lifecycle tasks in [backlog #72](https://github.com/balgf/PHPThis/issues/72) can supply later evidence; this report does not claim it already exists.

## Reproduction and distribution boundary

The optional scripts in this directory are fixed historical-source experiments. They do not add a Composer stage, consumer checker, suppression, PHPStan baseline, generated application policy, runtime helper, or model runner. They operate in temporary fixtures, use installed development tooling only where stated, and report bounded observations. Run from the framework source checkout; they are not installed application commands. Python 3 is used only for these optional maintainer reproductions.

This directory remains excluded from Composer and Git release archives by the existing `/tools` policy. The current runtime package inventory, Consumer Contract, Strict Profile, historical ADRs, templates, skeleton, and accepted validity guards remain unchanged. `.ai/testing.md` routes maintainers here; `docs/evaluation.md` identifies the source-only report without making it a consumer requirement.

Reproducer prerequisites are Python 3.9+, Git, and the supported PHP 8.4.x runtime with the normal project extensions. The SQL experiment also requires the existing framework development dependencies and PDO SQLite. The size/environment script reconstructs 519 exact tracked Git blobs and verifies their identities and executable modes; this avoids `git archive` omitting the maintainer inputs through `export-ignore`. It executes the real guard and PHT007 owner without inheriting host environment values. The SQL script uses the recorded source/tests and current matching runtime plus installed development tools. Each script removes its temporary fixtures and bounds individual child execution and captured output; neither is a containment or model-evaluation system.

Investigation corrections retained: an initial `php -n` setup omitted this machine's configured tokenizer extension and was corrected to preserve normal extensions while disabling prepend/append hooks; that was a reproducer setup failure, not a PHT007 finding. Initial history aggregation missed brace-form rename paths; the retained SQL script parses NUL-delimited rename records, and the counts above use that corrected result.

Validation on the review date: both optional reproductions passed, including the real account-maintenance inventory and the four synthetic SQL variants. The complete `composer check` passed: guardrails reported 251 Markdown files, 239 PHP files, and 2,618 core lines; static analysis, strict-profile controls, installed guidance and isolated consumer checks passed with the unchanged 230-file release inventory; PHPUnit reported 189 tests and 195 assertions. The isolated consumer result was explicitly `git-export-parity=skipped-dirty`. These are working-tree development results, not committed-archive parity, a release, or package-host availability.
