# Evaluation dimensions

A correct change satisfies the public functional contract, preserves protected files, and passes the unchanged complete application gate. The external scorer evaluates response correctness, authority boundaries, durable-state behavior and instrumented resource behavior separately. It records every failed check; one green public smoke is not a complete result. Exact scorer cases and expected values are external and are never provided to generation.

The comparison uses competent typed PHP applications with the same functional prompt, PHP version, resolved PHPStan/strict-rules versions, model settings and generation budget. The PHPThis condition adds the installed framework runtime, documentation and Strict Profile. This comparison measures that combined condition, not a causal isolation of documentation, and is not a comparison against every PHP framework.

Instrumented PDO/QueryTrace observations are protected from edits and candidate-reported counters are ignored. They do not prove resistance to malicious in-process instrumentation bypass; source review remains a separate required condition for resource claims. Query counts do not establish rows scanned, query plans, concurrency safety or production database performance.
