# Observation

The protected driver records raw responses, supplied-policy steps, statement attempts including failed executions, per-SQL hashes, independent committed rows and transaction state. These are instrumented observations, not proof against malicious instrumentation bypass. Never emit secret data or change measurement code. No request telemetry service or production logging is adopted.
