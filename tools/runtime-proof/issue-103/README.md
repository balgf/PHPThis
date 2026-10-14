# Server-reported peer proof

Issue #103 / Contract 20 adds a typed server-reported address. This optional Docker proof exercises the unchanged example front controller with `tests/fixtures/peer-bootstrap.php.fixture` as its disposable application composition. It is separate from `composer check`, which runs the native reader, request-copy, PHP HTTP and installed-package tests.

From the repository root, with Docker and Python 3:

```sh
docker build --tag phpthis-peer-proof:issue103 tools/runtime-proof/issue-103
python3 tools/runtime-proof/issue-103/run.py
```

The build downloads Nginx/Python on the pinned PHP 8.4.19 base. The runner uses an existing local image, mounts this checkout read-only, publishes no host port and disables external networking. Only container-local loopback clients reach Nginx/FPM. Processes have bounded startup/request/shutdown times and use private disposable logs. The owned container is removed on success or failure; the reusable image remains.

Seven requests cover direct IPv4/IPv6 with spoofed headers, real-IP rewriting, explicit original-peer preservation, missing required metadata, malformed metadata and an empty value. Responses retain correlation and cache policy; terminal summaries omit addresses. Missing required metadata uses the fixture's explicit 403 policy, while malformed input uses its existing `InvalidRequest` 400 mapping. The address-disclosure handler exists only in this isolated proof.

The synthetic rewrite profile trusts loopback solely to demonstrate Nginx behavior; it is not a production trust configuration. Other servers, proxy topologies, PROXY protocol, TLS and Swoole are outside this evidence. Production must verify its actual source and FastCGI restrictions.

The retained `evidence.json` records the executed versions, source hashes and responses. It used the already available `phpthis-swoole-proof:issue100` image via `--image`; that image contains the same PHP/Nginx tools, but this proof never starts Swoole. Replays with another image report their own versions and identity.
