# External integration contract

`NOT_APPLICABLE`: the starter application contacts no external services and performs no external side effects. Replace this statement with verified contract, timeout, retry-owner, idempotency, and failure facts before adding an integration.

`NOT_APPLICABLE(CACHE)`: the starter has no local or remote cache backend. Before adopting a remote cache, record its named client boundary, supported contract and version, bounded connect and operation timeouts, retry owner and maximum attempts, and explicit backend-failure behavior. Do not silently retry, silently serve stale data, or confuse backend failure with an authoritative-data miss.

`NOT_APPLICABLE(WEBSOCKETS)`: the starter has no WebSocket runtime package or protocol integration. Before adoption, read installed `vendor/phpthis/framework/docs/websockets.md` and record the selected mature third-party package and exact supported version, contract source, non-secret configuration, failure ownership, update policy, and any external authentication, broker, proxy, or TLS boundary in `.ai/websockets.md` and `.ai/operations.md`. Keep retries, replay, acknowledgement, delivery, and backend-failure behavior explicit; do not invent a generic gateway, channel, broadcaster, pub/sub, or event-bus abstraction.
