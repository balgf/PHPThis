# HTTP contract

`Request` and `Response` are immutable values. A handler returns a response; it does not emit output or terminate the process.

Rules:

- Normalize the method and path once in `RequestReader`; do not normalize again in handlers.
- Pass parsed input explicitly. Do not read superglobals in handlers.
- Store normalized request header names as lowercase keys and use explicit array access; do not add a header helper.
- Parse a JSON body once with a concrete `Command::fromJson` factory; reject missing, unknown, and wrongly typed fields.
- Set the outer body limit in the composition root, enforce endpoint-specific limits before decoding, and use `JSON_THROW_ON_ERROR` with an explicit depth.
- Enforce route-specific media types in the handler before parsing or database work.
- Set status, body, and headers explicitly.
- For `Cache-Control`, validators, or `Vary`, also follow `.ai/cache.md`. PHPThis explicitly includes `no-store` on framework-owned 404, 405, and unknown-failure 500 responses and on current skeleton/example handlers; protected responses additionally use `private`. It does not automatically inject or replace policy on arbitrary handler responses.
- Represent response cookies only as validated `ResponseCookie` values in `Response::$cookies`; never encode `Set-Cookie` in the ordinary header map.
- Keep cookie names, values, paths, expiration, and security attributes explicit. `SameSite=None` and the `__Secure-` or `__Host-` prefixes require `Secure`; `__Host-` also requires `Path=/`.
- Encode JSON with `JSON_THROW_ON_ERROR` and set its content type.
- Emit the response only after `RequestBoundary::handle` returns.
- Treat redirects, files, and streams as future explicit response types, not array conventions.
