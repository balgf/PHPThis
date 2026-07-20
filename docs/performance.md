# Performance policy

Performance claims need a measurable resource and a bound.

For database code, record statement count, result cardinality, and representative execution plans. For framework code, record request latency and memory only after a stable scenario exists.

A convenience API is rejected when it can hide:

- database or network I/O;
- iteration proportional to user-controlled data;
- unbounded allocation or result collection;
- runtime scanning, reflection, or generated proxies;
- retries or fallback work.

Optimize after measurement, but install cheap limits early. Query budgets and explicit collection bounds are correctness constraints, not micro-optimizations.

## Cache evidence

A cache changes where work occurs; it does not remove the need to bound that work. Performance reports for an adopted cache name the backend and topology and report cold and warm scenarios separately. They include cache-operation count, payload size, hit/miss outcome, database statement count, request latency, and the tested fixture cardinality. A warm-cache latency result is not evidence that the cold path avoids N+1 queries.

Cache observability is a separate bounded aggregate from `QueryBudget` and `QueryTrace`. Do not emit one log event per cache operation or retain keys and values. Expiration, eviction, backend failure, invalidation, stale refill during a concurrent authoritative write, serialization, and concurrent misses are measured behavior rather than assumed backend portability.

`RequestReader` bounds body materialization at the configured maximum plus one detection byte. It also bounds request-target bytes, top-level query count, header count, and each header value. These are allocation guards, not complete request-latency or denial-of-service protection; the web server must enforce compatible transport limits before PHP.

## Database observability

`QueryTrace` performs monotonic timing and one bounded in-memory aggregate update per PDO attempt. It does not perform logging I/O. Repeated exact-SQL fingerprints reveal indirect N+1 behavior that remains under a generous query budget, while fixture-size tests still provide the proof that query count is constant.

ADR 023 permits one application-owned terminal event to summarize at most eight explicitly registered database sources. It reports aggregate and per-source budget and trace evidence without adding per-query I/O. A sink attempt is outside query timing, may fail without changing the response, and does not prove delivery latency or durable storage.

Reported `execute_duration_us` values cover prepare, parameter binding, and execute. They do not claim to measure result fetching, hydration, network log delivery, or complete request latency.

## Query-scaling proof

`composer test:query-scaling` compares the accepted bounded aggregate read with an intentionally invalid N+1 negative control. Both return identical JSON for 2-user and 50-user fixtures. The accepted implementation stays at one statement; the negative control grows from 3 to 51, repeats its child-query fingerprint 50 times, is rejected by `PHT003`, and is stopped before statement 4 when given a budget of 3. A separate 125-user fixture traverses the accepted keyset as 50, 50, and 25 users, with one fresh budget and trace per request, one statement per accepted page, and no missing or repeated identifiers.

The negative source uses a `.php.fixture` suffix and is never accepted application code. The proof explicitly submits it to the same Strict Profile checker used by the repository validity gate before executing it in an isolated subprocess.

This is a statement-count and N+1 proof, not a bound on total database work. A query budget does not limit rows scanned, join fan-out, result-fetch cost, or lock duration; representative execution plans and production-database integration tests remain required.

## Routing

Routes are indexed once when `Router` is constructed. Fully literal routes retain direct method/path access. Routes with at most two full-segment `positive-int` or bounded `token` parameters use a deterministic state index. Typed matching and allowed-method lookup traverse the bounded request path and compiled transitions, not the declared route list or an index collection. Construction time and route-state memory grow with the number and segment count of routes; request-time work grows with the bounded request path rather than by iterating the route table. Repository guardrails permit the path-segment traversal and reject lookup-reachable route or index scans.

Run `composer benchmark:routing` to record construction, memory, literal and nested typed hit, ordinary miss, oversized-token miss, and literal and typed allowed-method lookup measurements while route groups grow through 100, 1,000, and 10,000 entries. At the largest size the router holds 10,000 fully literal routes, 10,000 one-token branching routes, and 10,000 nested routes with two typed parameters. The branching routes deliberately add many literal siblings beside a reused positive-integer transition so construction evidence would expose an accidental repeated-sibling scan; the timed nested lookup targets the last compiled branch with a 64-byte token. The benchmark reuses one lightweight handler so that it isolates route-state cost. It reports measurements without hardware-dependent pass/fail timing thresholds. Tests separately exercise the same 20,000-parameterized-route branching shape, exact-literal precedence, bounded token parsing, and overlap rejection.
