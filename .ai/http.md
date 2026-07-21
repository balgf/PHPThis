# HTTP contract

`Request` and `Response` are immutable values. A handler returns a response; it does not emit output or terminate the process.

Rules:

- Normalize the method and path once in `RequestReader`; do not normalize again in handlers.
- Pass parsed input explicitly. Do not read superglobals in handlers.
- For multipart `POST`, use only the bounded typed `Request::$uploads` map. Require one exact field in an application boundary; never pass raw `$_FILES` or trust client filename, path, media type, or reported size.
- Store normalized request header names as lowercase keys and use explicit array access; do not add a header helper.
- Parse a JSON body once with a concrete `Command::fromJson` factory; reject missing, unknown, and wrongly typed fields.
- Construct the complete final readonly command before downstream operation behavior. When a separate typed operation seam is justified, call it only with that command. Invalid input causes zero seam calls and no operation-owned downstream I/O or mutation; record any protected-request policy work deliberately ordered before parsing.
- Keep validation, deliberate field normalization, output encoding or escaping, and authorization as separate visible decisions; none is a generic input helper.
- Set the outer body limit in the composition root, enforce endpoint-specific limits before decoding, and use `JSON_THROW_ON_ERROR` with an explicit depth.
- Enforce route-specific media types in the handler before parsing or database work.
- Set status, body, and headers explicitly.
- For `Cache-Control`, validators, or `Vary`, also follow `.ai/cache.md`. PHPThis uses `no-store` on framework-owned 404 and 405 responses, `private, no-store` on the unknown-failure 500, and at least `no-store` on current skeleton/example handlers; protected example outcomes use `private`. It does not automatically inject or replace policy on arbitrary handler responses.
- Represent response cookies only as validated `ResponseCookie` values in `Response::$cookies`; never encode `Set-Cookie` in the ordinary header map.
- Keep cookie names, values, paths, expiration, and security attributes explicit. `SameSite=None` and the `__Secure-` or `__Host-` prefixes require `Secure`; `__Host-` also requires `Path=/`.
- Encode JSON with `JSON_THROW_ON_ERROR` and set its content type.
- Emit the response only after `RequestBoundary::handle` returns.
- Before emission, preserve the application-owned ADR 023 coordinator's generated `X-Request-ID` and one failure-isolated terminal sink attempt. Do not add this behavior to `RequestBoundary`, `ResponseEmitter`, middleware, or a global logger.
- For a local file, use only `LocalFileBody` with an application-resolved absolute path and expected bytes. Keep the ordinary body empty, set exact `Content-Length` and representation headers in the handler, preserve the file body through response copies, and let the emitter verify and stream fixed chunks.
- Keep range handling deferred: emit the complete `200` representation with `Accept-Ranges: none`; do not add `206`, `Content-Range`, or range parsing.
- Handle `ResponseEmissionFailed` only at the visible front controller after terminal response selection. A pre-header failure may receive one generic fallback; a post-header failure cannot receive a replacement response.
- Treat redirects and non-local or callback streams as future explicit response types, not array conventions.

ADR 021's Create proof uses the application-owned `CreateUserOperation` seam and `TransactionalCreateUser` as the concrete operation directly owning that transaction's complete SQL, without adding a framework input, query object, helper, or service API. ADR 026 adds the narrow file-transfer values; Consumer Contract v7 carries Strict Profile v2 forward unchanged.
