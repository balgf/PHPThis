# Stateless Swoole HTTP experiment

[Issue #100](https://github.com/balgf/PHPThis/issues/100) evaluates whether an explicit Swoole adapter merits further work. This is a source-checkout experiment, outside Consumer Contract 18's supported HTTP path. Read the [proposed adoption boundaries](proposal.md) before considering a consumer integration. Framework code, dependencies and the conventional front controller are unchanged.

## Replay

Use the framework checkout with its locked Composer development dependencies installed, Python 3 and Docker. The image build downloads PHP build dependencies and Swoole; execution uses disposable, network-isolated containers. No host PHP extension or configuration change is needed.

From the repository root:

```sh
docker build --tag phpthis-swoole-proof:issue100 tools/runtime-proof/issue-100/runtime
python3 tools/runtime-proof/issue-100/run.py --framework "$PWD"
# Optional matched measurements after correctness checks:
python3 tools/runtime-proof/issue-100/run.py --framework "$PWD" --benchmark
```

Each run freezes this fixture into `tmp/swoole-proof/<run>/source.tar.gz`, materializes `.php.fixture` files in its owned temporary directory, and retains hashes, checks and cleanup receipts. The runner removes its containers and materialized directory on success or failure; the reusable image remains. It creates no named volumes and publishes no host ports. Checks use the actual Swoole extension, maximum-level PHPStan, strict rules and framework diagnostics. This optional Docker experiment is separate from the required repository `composer check` gate.

## Execution and coverage

Nginx sends the same fixture handlers to one PHP-FPM worker or one Swoole worker. Every request constructs a fresh PDO connection, budget, trace, correlation ID and coordinator. Both paths reuse the canonical summary classes from `skeleton/src/Observability`. The Swoole bridge maps native input explicitly, reuses `RequestReader` through bounded in-memory input, and emits a bounded response through native Swoole methods. Hooks and connection pooling are disabled; SQLite PDO remains synchronous.

The synthetic database has 400 documents and three events per document. A constant SQL join returns at most 50 documents using one statement. The caller-selected tenant number is a fixture partition selector, with no authentication or authorization claim. Routes for injected failures, runtime metadata and rollback exist only inside the isolated proof.

The 222-response check covers bounded reads, invalid input, body/header/target limits, routing failures, generic exceptions, budget exhaustion, rollback, and recovery. Repeated requests alternate partitions and markers. Sixteen concurrent clients exercise 64 requests per runtime; Swoole's correctness mode deliberately yields after composition to overlap request contexts. Correlation IDs stay unique, and all 222 responses match one terminal summary with their expected query counts. Separately, denying database-file access proves generic pre-coordinator failures and subsequent recovery on both paths.

This does not qualify sessions, cookies, uploads, file delivery, streaming, WebSockets, HTTP/2, TLS, production authentication or long-running memory behavior. Native metadata normalization, complete emitter equivalence (including failed emission), worker recycling and in-flight shutdown still require adoption evidence. The recorded shutdown checks stop an idle worker; they do not prove graceful completion of in-flight requests.

## Recorded measurements

Run `20261013T234652` took place on **2026-10-14 Asia/Manila**, against framework `cfb0ae656c5beaff0974ad2576648d1745579e41`. It used PHP 8.4.19, Swoole 6.2.3 and an ARM64 Docker image identified in [metadata](evidence/measured/metadata.json). Both runtimes used the same image, handler, SQL, fixture, fresh-connection policy, OPcache enabled, JIT disabled and private summary destination. Nginx uses FastCGI for FPM and HTTP for Swoole.

The server container has one CPU and 512 MiB; the separate client has one CPU and 256 MiB. Each condition has three alternating-order repetitions, 128 warmup requests and 1,500 measured requests per repetition. All 36,000 measured responses passed body decoding and fixture assertions. Samples lasted about 0.4–0.6 seconds. Client CPU consumption stayed below 22% of one CPU, with 21 MiB peak RSS.

Throughput is the median of three repetition rates; latency quantiles pool 4,500 requests per condition. p95/p99 use nearest rank. The client uses closed-loop HTTP/1.1 keep-alive, so runtimes receive different arrival rates at capacity; this is not a latency comparison at a fixed offered load. Latencies include client decoding/checks and exclude initial connection establishment, which remains in throughput elapsed time.

| Rows | Concurrency | Runtime | Requests/s | p50 ms | p95 ms | p99 ms |
| --- | --- | --- | ---: | ---: | ---: | ---: |
| 2 | 1 | FPM | 2,886 | 0.339 | 0.389 | 0.487 |
| 2 | 1 | Swoole | 3,145 | 0.312 | 0.355 | 0.465 |
| 2 | 16 | FPM | 3,133 | 4.687 | 5.622 | 13.627 |
| 2 | 16 | Swoole | 3,543 | 3.053 | 4.394 | 36.368 |
| 50 | 1 | FPM | 2,595 | 0.375 | 0.425 | 0.505 |
| 50 | 1 | Swoole | 2,822 | 0.349 | 0.387 | 0.429 |
| 50 | 16 | FPM | 2,873 | 5.195 | 9.009 | 11.119 |
| 50 | 16 | Swoole | 3,456 | 3.442 | 4.966 | 34.834 |

Throughput improved approximately 9–20%; high-concurrency p99 worsened. Its cause was not profiled. These short local read measurements establish neither sustained production performance nor benefits from asynchronous database I/O. Keep the integration experimental until a concrete consumer workload justifies further work and the remaining boundaries are proven.

## Evidence and repairs

[Evidence inventory](evidence/manifest.json) hashes the retained source snapshot, raw request timings and check results. [Aggregated results](evidence/benchmark-summary.json) retain per-condition throughput ranges. Compressed JSON and JSONL can be read with Python's `gzip` module. The measured snapshot predates packaging; the inventory verifies that its application, runtime configuration and test bytes match the checked-in fixtures. The packaged replay independently rechecks correctness and cleanup.

The preparation exposed two extension-build requirements, Nginx temporary-path permissions, and empty FastCGI `CONTENT_LENGTH` metadata rejected by the framework. The test client initially assumed JSON for the framework's plain-text 404/405; its assertions were corrected. The minimal image lacked a `kill` executable, so shutdown uses a direct Python signal. A first benchmark stopped when Nginx closed a keep-alive connection; the client now honors connection closure without retrying failed requests, and the explicit connection limit exceeds a sample. [Attempt records](evidence/attempts.json) preserve these failures, fixes and source/log hashes alongside the successful evidence.
