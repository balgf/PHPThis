# File emission

`ResponseEmitter` has one concrete local-file path:

1. reject when headers were already sent or any active PHP-managed output-buffer level reports pending bytes;
2. open read-only;
3. `fstat` the opened handle;
4. require a regular file and exact expected size before headers;
5. emit status, headers, and cookies;
6. read and echo at most 8,192 bytes per iteration until the expected count reaches zero; and
7. close the handle in `finally`.

The first check inspects every active PHP output-buffer level without flushing, cleaning, rewriting, or incorporating prior bytes into the selected response. No active buffer, one empty active buffer, and a nested stack whose every level is empty are valid. Pending bytes at any level fail as `ResponseEmissionFailed(true)` before file access, status, headers, cookies, or body output. This is one entry-time snapshot of PHP-reported state; private state retained by a custom output handler and output produced later are outside the guarantee.

The loop's allocation is bounded by the fixed chunk rather than total file size. It does not prove that PHP, a web server, reverse proxy, TLS terminator, or client has disabled its own buffering. Production claims require representative measurement through the deployed path.

The exact framework guarantee is narrow: for the already opened handle, the emitter verifies regular-file type and expected length before headers, then makes one sequential read pass with at most 8,192 requested bytes per iteration. It either echoes exactly the declared count or raises `ResponseEmissionFailed`; it never intentionally echoes a byte beyond the declared count, retries the transfer, or emits a replacement after response start. The pre-header `fstat` is not a lock or content snapshot. Concurrent mutation or replacement, byte identity, integrity hashing, client receipt, network completion, durable delivery, and buffering outside this loop are not guaranteed.

Headers already sent or PHP-reported pending buffer bytes raise `ResponseEmissionFailed(true)`. A pre-header open, type, or length failure raises `ResponseEmissionFailed(false)`. The visible front controller may then emit one generic 500. A read failure after headers also raises `ResponseEmissionFailed(true)`; no valid replacement response can be started, so the front controller records only a fixed application-owned operational signal and lets the incomplete `Content-Length` framing expose truncation.

The terminal request summary is selected before emission. It does not claim successful filesystem read, client delivery, or network completion.
