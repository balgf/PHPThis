# File emission

`ResponseEmitter` has one concrete local-file path:

1. reject output whose headers were already sent;
2. open read-only;
3. `fstat` the opened handle;
4. require a regular file and exact expected size before headers;
5. emit status, headers, and cookies;
6. read and echo at most 8,192 bytes per iteration until the expected count reaches zero; and
7. close the handle in `finally`.

The loop's allocation is bounded by the fixed chunk rather than total file size. It does not prove that PHP, a web server, reverse proxy, TLS terminator, or client has disabled its own buffering. Production claims require representative measurement through the deployed path.

Prior output raises `ResponseEmissionFailed(true)`. A pre-header open, type, or length failure raises `ResponseEmissionFailed(false)`. The visible front controller may then emit one generic 500. A read failure after headers also raises `ResponseEmissionFailed(true)`; no valid replacement response can be started, so the front controller records only a fixed application-owned operational signal and lets the incomplete `Content-Length` framing expose truncation.

The terminal request summary is selected before emission. It does not claim successful filesystem read, client delivery, or network completion.
