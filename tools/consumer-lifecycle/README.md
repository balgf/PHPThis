# Retained consumer lifecycle evaluation

[Issue #71](https://github.com/balgf/PHPThis/issues/71) retains one synthetic account directory through a public Alpha 7 baseline, a search feature on the same dependency, and an in-place source upgrade. The final consumer gate passes, including fresh-review regression cases. This is maintainer-led application evidence with a fresh qualitative agent review, not an independent adoption study, paid API campaign, production deployment, or long-term maintenance result.

## Retained evidence

- [Frozen plan and behavior prompt](issue-71/plan.md), committed in consumer tag `frozen-plan` before feature work.
- [Portable Git history](issue-71/account-directory.bundle), including source, app context, tests, locks, valid and failed tags; no vendor, SQLite file, credentials, or copied maintainer application.
- [Manifest](issue-71/manifest.json): full commit/lock/bundle hashes, file/line/byte measures, dependency identities, package-export comparison, touched files and diff hashes.
- [Fresh review receipt and disposition](issue-71/review.md), [exact prompt/interventions](issue-71/review-prompt.md), [known reading inventory](issue-71/reading.md), and [repair ledger](issue-71/repairs.md).
- Four retained patches: [baseline](issue-71/baseline.patch), [search](issue-71/search.patch), [upgrade](issue-71/upgrade.patch), [review fixes](issue-71/review-fixes.patch). The bundle is authoritative; patches help inspection.

Owner: PHPThis maintainer `balgf`. Working consumer is the project sibling `consumer-lifecycle/account-directory`; a separate `review-alpha7` clone supplied only the public installed guidance/current consumer to the fresh reviewer. The bundle makes local paths unnecessary for reproduction.

## Checkpoints and results

| Consumer tag | Framework / Contract / Profile | Complete gate | Runtime PHP bytes | App context bytes |
| --- | --- | --- | ---: | ---: |
| pristine-alpha7 | public Alpha 7 / 13 / 3 | pass, 00-pristine-check | 13,781 | 127,550 |
| baseline-alpha7 | public Alpha 7 / 13 / 3 | pass, 02-baseline | 31,242 | 65,843 |
| search-alpha7 | same Alpha 7 lock / 13 / 3 | pass, 04-search-alpha7 | 32,010 | 66,793 |
| upgraded-source | source 909977b / 18 / 4 | pass, 08-upgrade-repaired | 32,823 | 68,727 |
| reviewed-source | same source lock / 18 / 4 | pass, 09-final-consumer | 32,823 | 69,453 |

Runtime PHP means bootstrap.php plus PHP under src/public. Context means AGENTS.md plus .ai Markdown; tests, README and lifecycle/upgrade reports are separately inventoried. Baseline context shrank because entered starter guides were replaced with specific evaluation facts; this is not evidence that every application needs less context. Final runtime source has 37 files/925 physical lines; the search touched only two runtime files and added 768 bytes. The upgrade changed the front controller/bootstrap, not domain operations. Review repairs changed tests/context only.

Observed runtime: PHP/pdo_sqlite 8.4.19, SQLite 3.51.2, Python 3.14.6. Exact host/runtime repetition is a new measurement, not an inherited production or cross-engine claim. Complete gate logs and JSON capture records are numbered beside the manifest. The two historical failed tags remain failed: `search-red` expects search success but receives 400; `upgrade-red` passes static/existing behavior but receives an empty body instead of Contract 18's generic bootstrap-failure response. The intermediate `07-upgrade-instrumentation-failure` records a macOS path-alias assertion repaired by canonicalizing the test's own temporary directory, with required settings unchanged.

## What the same consumer proves

- Account-scoped Create/List with typed inputs/projections, three explicit transaction writes, separate actor membership/user association, fixed SQL and a 20-row page plus lookahead. Normal authentication denies everyone; successes use test-only injected policies.
- Preserved health, success, policy order/replacement/failures, zero protected denial/input work, malformed body/value/media/bounds, ID conflicts, complete last-write rollback, redacted responses/summaries/traces and real loopback front-controller behavior.
- One List statement on both 2- and 500-row fixtures, including literal case-sensitive name/email search and filtered continuation. Percent/underscore/SQL-looking values remain bound data. Fixed round trips are not a latency/index/full-text-search result.
- In-place Contract 14–18 applicability in the bundled consumer's docs/upgrade-source.md: raw target rejection, already-distinct PHT008 placeholders, regular source inventory, empty/pending output buffers, and the required generic outer HTTP boundary. No new skeleton replaced app context.
- Actual built-in-SAPI settings and missing-fixture failure, input non-selection, safe anonymous class handling and isolated event failure. Optional details renderer/profile inputs and their tests are explicitly not adopted.
- Fresh review added missing-membership SQL regressions, cursor representations/overflow, structural-error property-order equivalence, and exact UTF-8 byte boundaries. These are final-checkpoint additions, not retroactive Alpha 7 test claims.

## Installation and publication boundary

The public skeleton was created with `composer create-project --stability=alpha --prefer-dist --no-install --no-interaction phpthis/skeleton <consumer> 0.1.0-alpha.7`, then its committed lock installed. [Packagist identities](issue-71/public-package-identities.json) retain framework de07cbd449ed3e905331aeea105e528fbaa83ac3 and skeleton c0e8f5c2a7eb45e24e97dc672bd22dcc13c8684d. The pristine consumer's 39 files match the exact public skeleton archive; installed Alpha 7's 218 framework files match its exact Git export byte-for-byte.

Upgrade used a public VCS repository and `phpthis/framework:dev-main#909977b762ff08f823be1e2c16a4dbb704e4f93a`, then `composer update phpthis/framework --prefer-dist --no-interaction --no-progress`. Both source/dist references in the lock equal that SHA; installed 231 files match its exact Git export. [Install log](issue-71/05-source-install.log) records only one package update; PHPStan 2.2.7 and strict-rules 2.0.12 stayed fixed. The earlier baseline added the consumer's pdo_sqlite requirement. Search does not change the lock. Source evaluation is not later public-package proof, a release, or a claim about current package availability beyond the recorded observation.

The unfamiliar reviewer identified historical Alpha 7 README/setup labels that still describe Alpha 6/preparation. Current maintainer wording already identifies the public pair; this change clarifies frozen labels versus the consumer lock and explains that example paths refer to source excluded from the archive. No historical tag or vendor file was edited.

## Reproduce from the retained bundle

Use an unused destination, PHP 8.4/pdo_sqlite, Composer and Python 3; no external database, credentials, API credit or framework-maintainer source is required for the consumer gate:

```sh
git clone tools/consumer-lifecycle/issue-71/account-directory.bundle /tmp/phpthis-71-replay
cd /tmp/phpthis-71-replay
git checkout reviewed-source
composer install --no-interaction --prefer-dist
composer check
```

Repeat checkout/install/check for `baseline-alpha7` and `search-alpha7` to reproduce their narrower valid checkpoints. `pristine-alpha7` is the unmodified starter. `search-red` and `upgrade-red` deliberately return nonzero at the retained assertions; do not call them valid or suppress the assertions. Each check recreates only synthetic database contents; application code/context/history are preserved. Real HTTP tests bind only owned loopback ports and clean their child processes/private scratch trees. No production SAPI, proxy, HTTPS, auth, migration, long-term data survival or least-privilege claim follows from this SQLite fixture.

A clean temporary replay of `reviewed-source` also passed a fresh locked install and the full gate, with identical captured file hashes and a clean Git tree ([replay log](issue-71/10-bundle-replay.log)); its owned temporary checkout was then removed.

From the framework checkout, `python3 tools/consumer-lifecycle/capture.py <consumer> <new-output-stem>` captures a fresh complete gate, hashes, installed dependency identity, runtime and wall time. It refuses to overwrite evidence. Existing installed-consumer proof ownership in tools/test-consumer-project.php and tests/consumer-profile.php remains unchanged; this optional retained evaluation is not another consumer validity stage or framework feature.

## Effort and limits

The manifest retains elapsed capture durations (gate plus evidence work), commit timestamps, diffs and exact file growth/touches. Read inventory is a retrospective known-file lower bound plus a reviewer-reported receipt; it is not controlled read telemetry. Seven primary complete-gate captures include five passes and two failed upgrade outcomes, alongside focused red tests and the separately reported initial authoring/sandbox failures. The reviewer's 6h 51m 39s observed window includes the interruption/resume interval. Neither active implementation/review labor nor model tokens/cost were measured; those fields are null. No separate paid API campaign ran.

Mandatory installed framework contract/map text was 124,005 bytes / 15,644 whitespace words in Alpha 7 and 42,289 bytes / 5,548 words in the pinned source. This measures file volume only; no token, comprehension, success-rate or productivity improvement is inferred. Deferred application guide templates remain in the consumer without being adopted.

## Next concrete maintenance trigger

When the next matching public framework AND skeleton release is available, the maintainer repeats this same retained consumer's upgrade table and full gate against those exact public identities. This is written into its frozen lifecycle plan, not an installed background monitor. Retain new product changes as new frozen prompts on the same history. A future public-target repetition and actual elapsed maintenance period are distinct results; this campaign establishes the retained evaluation only.
