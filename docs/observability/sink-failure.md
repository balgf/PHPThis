# Terminal sink authority

The application owns and injects one sink. After the immutable response and closed summary are fixed, the coordinator makes exactly one synchronous sink invocation attempt.

Any sink `Throwable` is contained at that boundary. The coordinator does not retry, call a fallback logger, emit another event, replace the sink, or mutate response status, headers, body, or cookies.

An invocation attempt is not durable delivery. Destination buffering, transport, retention, backpressure, outage handling, and monitoring remain explicit application operations policy. No framework sink, logger facade, global helper, middleware, event pipeline, or discovery mechanism is authorized.
