# External integration contract

`NOT_APPLICABLE`: the starter application contacts no external services and performs no external side effects. Replace this statement with verified contract, timeout, retry-owner, idempotency, and failure facts before adding an integration.

`NOT_APPLICABLE(CACHE)`: the starter has no local or remote cache backend. Before adopting a remote cache, record its named client boundary, supported contract and version, bounded connect and operation timeouts, retry owner and maximum attempts, and explicit backend-failure behavior. Do not silently retry, silently serve stale data, or confuse backend failure with an authoritative-data miss.
