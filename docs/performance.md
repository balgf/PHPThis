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

## Database observability

`QueryTrace` performs monotonic timing and one bounded in-memory aggregate update per PDO attempt. It does not perform logging I/O. Repeated exact-SQL fingerprints reveal indirect N+1 behavior that remains under a generous query budget, while fixture-size tests still provide the proof that query count is constant.

Reported `execute_duration_us` values cover prepare, parameter binding, and execute. They do not claim to measure result fetching, hydration, network log delivery, or complete request latency.

## Routing

Exact routes are indexed once when `Router` is constructed. Construction time and route-table memory grow with the number of routes; exact matching and allowed-method lookup use direct array access and must not scan the route list. The repository guardrail rejects loops in those request-time lookup methods.

Run `composer benchmark:routing` to record construction, memory, hit, miss, and allowed-method lookup measurements for 100, 1,000, and 10,000 literal routes. The benchmark reuses one lightweight handler so that it isolates route-table cost. It reports measurements without hardware-dependent pass/fail timing thresholds.
