# Issue 79: retained LLPhant / Qdrant consumer

This is one synthetic application-owned integration proof. The public adoption owner is [docs/rag.md](../../../docs/rag.md); this report records observations, not a production architecture or model recommendation. The approved service scope was disposable local Qdrant with explicit setup/teardown and deferred general migrations. No paid model request was made.

## Reproduce the reviewed source

From a framework checkout, use an unused destination:

```sh
git clone tools/consumer-lifecycle/issue-79/rag-document-qa.bundle /tmp/phpthis-79-consumer
cd /tmp/phpthis-79-consumer
git checkout reviewed-rag
composer install --no-interaction --prefer-dist
composer check
```

PHP 8.4/curl/mbstring, Git and Composer are required. The normal gate starts only owned loopback HTTP fixtures and requires no Docker, credentials or paid provider. To opt in to the separate real-service evidence on a local Unix-socket Docker daemon:

```sh
bash tests/qdrant.sh
```

The wrapper pins Qdrant's image, creates its own bridge network/container, exposes only an ephemeral loopback port, uses bounded tmpfs with no host data volume, and tears down the collection, container and network. The bridge is not an egress firewall. Settings are test-specific, including the 1 MiB WAL; they are not production capacity guidance. No test uses a shared Qdrant service.

The [reviewed replay record](28-reviewed-replay.json) identifies every source file, dependency revision, PHP/cURL runtime, bundle hash and the clean checkout result. The [replay log](28-reviewed-replay.log) includes strict Composer validation, locked installation and the complete gate. Earlier v1/v2 bundles preserve the exact bytes named by records 18/19; the final bundle also contains their commits and tags. `planned-baseline` preserves pre-implementation context; at `boundary-tests-red`, `php tests/rag.php` deliberately fails before the feature exists. Those tags do not represent later behavior.

## Exact tested identities

| Component | Identity |
| --- | --- |
| PHP / application contract | PHP 8.4.19; Consumer Contract 18 / Strict Profile 4 |
| PHPThis | `a6a08c80ecb54eb282b00359e7f476506e12bc0b`, installed from public VCS |
| LLPhant | 1.0.3 / `52fcd7afd58e6d289b9e304ea357695ece9dc518` |
| Qdrant PHP client | 1.0.0 / `37357400e91a22db0c58b9193940a3aa54f95ec3` |
| HTTP path | Explicit Guzzle PSR-7 2.13.1 factories/requests; application cURL loopback client, libcurl 8.18.0 / OpenSSL 3.6.1 |
| Qdrant | 1.19.1 / `6ab21cac18ebb6f4ae29102c7f8f5cc11affd5de` |
| Container digest | `sha256:12364fe851b9f17356fc88189fc06d1b521262e04659ec7345975b00c9246a10` |
| Embedding fixture | Ollama protocol, `phpthis-fixture-embedding-v1`, 8 dimensions, version `fixture-8-v1` |
| Generation fixture | Anthropic Messages protocol, `phpthis-fixture-chat-v1`; canned JSON answer, no real inference |

The lock also retains transitive Guzzle 7.15.5, OpenAI PHP client 0.19.2 and discovery 1.20.0; their presence is not evidence that their default runtime paths are compatible. These observations were made on 2026-09-27. This is source-revision evidence, not proof of a later public package publication.

## Acceptance evidence

| Issue criterion | Concrete evidence and limit |
| --- | --- |
| 1. Identities/providers | Lock, source hashes and runtime metadata in record 28; explicit synthetic model/version identities above. No live provider compatibility claim. |
| 2. Bounded document Q&A | `IngestDocument`, `AskQuestion`, `QuestionHandler` and `tests/rag.php` in the bundle; complete routed HTTP JSON responses with validated citations. |
| 3. Explicit composition | `bootstrap.php`, `LlphantModels`, protocol adapters and directly constructed Qdrant client. Selected execution has zero discovery attempts. `tests/rejected-integrations.php` separately reproduces the excluded OpenAI message-factory and LLPhant Qdrant constructor discovery paths before HTTP. |
| 4. Authority | Independently replaced policies, denial/non-entry/order tests; real tenant/document/reader/version decoys are filtered out before generation; returned payloads are also validated. Normal bootstrap is deny-all, not production auth. |
| 5. Lifecycle | Separate create/delete CLI; deterministic tenant/document/version/slot UUIDs; waited batch delete/upsert, repeat/shrink/delete and interrupted-write tests. Single writer, non-atomic replacement with an explicit gap/replay policy. |
| 6. Bounds/failures | Finite bodies/chunks/vectors/results/context/output, per-operation external call budgets, 200 ms connect / 2 s total Qdrant deadline, zero retries and redacted HTTP traces. `tests/transport.php` exercises actual timeout, overflow, redirect, rejection and refusal. Fixtures do not prove live model deadlines. |
| 7. Real service | [Reviewed Qdrant log](29-qdrant-reviewed.log): 6 versus 134 points, both exact and approximate-eligible requests, 134 indexed vectors, fixed three-call successful queries; empty queries skip generation. Wrong dimensions, interrupted replacement and missing collections fail. |
| 8. Routed guidance | One public guide, conditional maintainer/application routes, explicit starter non-adoption and two template adoption fields. Native framework/skeleton dependencies remain unchanged; evidence and bundles remain excluded from packages. |
| 9. Gates/review | Clean reviewed consumer replay, retained failures and repairs below, and [complete framework gate](30-framework-check.log) with [source/result record](30-framework-check.json): 189 tests / 195 assertions passed, including the installed guide and 232-file archive. The local dirty-worktree gate is not release evidence; PR CI separately checks the committed revision. |

Exact search is the selected policy. Approximate-eligible search may still use a scan or payload-index lookup for the narrow filter. Timings are individual local samples, not throughput/tail-latency or ANN benchmarks. Vector geometry is deliberately simple, answers are canned, and fixture usage counters are synthetic; there is no semantic quality, grounding, token-efficiency or price result.

The proof does not cover real credentials/providers, deployed HTTP/SAPI/proxy behavior, concurrent ingestion/revocation, durable jobs, schema migration, operational recovery, HA, production data retention or deletion from backups. Before live adoption those remain explicit application choices and verification work.

## Failures and repairs retained

- **01–05:** the first baseline check could not bind PHPStan sockets in the sandbox; the permitted rerun passed. The first boundary test failed before implementation. Initial type errors were repaired with precise iterable documentation and validated transport methods/URL construction, without suppressions.
- **06–10:** Docker's internal network did not publish the loopback port; the owned network became an ordinary bridge. Qdrant 1.19.1 rejected scan thresholds 0/1 (minimum 10), then its default WAL exceeded the 64 MiB disposable filesystem; explicit test WAL sizing repaired setup. Diagnostic 09 contains only the fixed synthetic setup validation error. Containers/networks were cleaned after every attempt.
- **11–15:** the fixture server initially indexed a raw superglobal outside the allowed boundary; fixed endpoint files removed that access. Strict Composer validation warned about manifest commit/exact-version constraints; compatible manifest ranges plus the committed lock retain every tested package/source/dist identity unchanged. The complete consumer and validation passed.
- **16–17, 20, 22, 24:** distribution guards required the new 232-file package inventory and one additional installed guidance proof. Starter context changes required `change.simple-ping` revision 30 and matching fixture/manifest/controller pins. The prompt, scorer, budgets, other tasks and prior results were preserved. No evaluation campaign ran.
- **18–19, 21:** clean bundle replays passed. Review added maximum four-chunk ingestion, zero storage work on invalid embeddings, and oversized retrieval rejection.
- **23, 25–28:** the excluded-path compatibility probes reproduced both discovery gaps. Their first placement incorrectly put consumer-only dependencies under framework analysis; they were moved into the retained consumer and checked there. Independent probe processes avoid stale static-analysis assumptions about third-party callbacks; no exclusion, baseline, cast or ignore was added. The reviewed complete gate passes.

Logs retain failed observations; not every intermediate working tree was committed. Baseline, deliberate red, implementation and reviewed checkpoints are retained in Git. Raw timing samples and package identities should not be treated as current measurements after an upgrade.
