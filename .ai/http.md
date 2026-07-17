# HTTP contract

`Request` and `Response` are immutable values. A handler returns a response; it does not emit output or terminate the process.

Rules:

- Normalize the method and path once in the front controller.
- Pass parsed input explicitly. Do not read superglobals in handlers.
- Parse a JSON body once with a concrete `Command::fromJson` factory; reject missing, unknown, and wrongly typed fields.
- Enforce request byte limits before decoding and use `JSON_THROW_ON_ERROR` with an explicit depth.
- Set status, body, and headers explicitly.
- Encode JSON with `JSON_THROW_ON_ERROR` and set its content type.
- Emit the response only after `Application::handle` returns.
- Treat redirects, files, streams, and cookies as future explicit response types, not array conventions.
