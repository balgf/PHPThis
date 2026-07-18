# Request handling

PHPThis has one explicit boundary between the PHP runtime and application handlers.

`example/public/index.php` is the only repository file allowed to read `$_SERVER` and `$_GET`. It passes both arrays to the manually constructed `RequestBoundary`. The boundary uses `RequestReader` to read the configured input URI with a hard byte limit, create one immutable `Request`, and delegate it to `Application` through `RequestHandler`.

## Normalization contract

`RequestReader` performs these transformations exactly once:

- uppercase an otherwise unmodified alphabetic method;
- remove the query suffix from `REQUEST_URI` without URL-decoding or rewriting the path;
- preserve top-level query values as external `mixed` values under validated string keys;
- translate `HTTP_*`, `CONTENT_TYPE`, and `CONTENT_LENGTH` runtime entries into lowercase header names;
- read at most the configured body limit plus one byte.

It rejects missing or wrongly typed method and URI values, relative or fragmented paths, invalid or conflicting headers, non-canonical content lengths, length mismatches, and excessive metadata. Some SAPIs expose `CONTENT_TYPE` and `CONTENT_LENGTH` again as identical `HTTP_*` entries; the reader collapses those identical normalized duplicates but rejects different values. The fixed profile bounds are 8,192 request-target bytes, 64 top-level query parameters, 64 headers, and 8,192 bytes per header value. The example configures an 8,192-byte outer body limit; `CreateUserCommand` applies its stricter 2,048-byte endpoint limit before JSON decoding.

Header names in `Request` are lowercase HTTP tokens. Handlers use explicit array access such as `$request->headers['content-type'] ?? null`; PHPThis intentionally provides no generic input or header helper.

## Media types

The generic reader does not guess which representation a route accepts. `CreateUserHandler` explicitly requires `application/json`, allowing parameters such as `charset=utf-8`, before it parses the command or performs database work. Missing or incompatible media types cross the boundary as `UnsupportedMediaType`.

Uploads, streaming bodies, trusted proxy interpretation, cookies, and worker-specific lifecycle behavior require separate evidence and contracts before they enter the request boundary.
