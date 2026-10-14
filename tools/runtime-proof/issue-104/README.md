# Native multipart Nginx/PHP-FPM proof

Optional local replay for issue #104 and ADR 067. It runs the canonical framework front controller with a synthetic inspection handler and no durable storage. The normal `composer check` suite separately exercises PHP's built-in web SAPI.

```sh
docker build -t phpthis-multipart-proof:issue104 tools/runtime-proof/issue-104
python3 tools/runtime-proof/issue-104/run.py
```

The runner only uses an existing local image (`--pull never`), binds source read-only, publishes no ports, disables external networking, uses a non-root identity and bounded resources, and removes its owned container in `finally`. All server traffic is container loopback. `--image` selects a previously built local image; retained evidence identifies the exact image and PHP/Nginx versions used.

The probe covers native fields/multiple files, exact counts and limit rejection (including repeated names), aggregate file limits, metadata collisions/nesting, retained normalization counterexamples, file-size/partial errors, incompatible PHP settings, handler exclusion, correlation, redacted private logs and temporary cleanup. Lower global automatic-parser limits prove explicit native options own parsing. A short HTTP body is closed by buffered Nginx before PHP dispatch.

`evidence.json` binds the relevant sources and records observed results. PHP still accepts some malformed raw parts and missing closing boundaries; the tests preserve those limitations rather than claiming strict MIME validation. This is no production deployment, upstream validator, multi-file storage or AWS certification. Native faults that require extension/kernel injection remain separate from exhaustive typed-error tests.
