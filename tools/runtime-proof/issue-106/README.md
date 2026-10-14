# Named-header boundary proof

This optional Docker check exercises the canonical front controller behind Nginx/njs and PHP-FPM using the synthetic application recipe. It complements `composer test -- --group request-policy`, which covers grammar, denial, outage, policy ordering, protected SQL and summary redaction. Neither command supplies a production verifier.

From the repository root:

```sh
docker build --tag phpthis-machine-proof:issue106 tools/runtime-proof/issue-106
python3 tools/runtime-proof/issue-106/run.py
```

The build downloads Debian's Nginx, njs module and Python packages on the pinned PHP 8.4.19 image. The runner records actual versions and source hashes. Execution uses only container loopback, no external network or published host ports, a read-only checkout, an unprivileged user, bounded processes/memory/output and disposable temporary storage. It removes its owned container on success or failure; the reusable image remains.

`headers.js` checks raw field multiplicity before CGI normalization, including identical and mixed-case duplicates, empty competing fields and dot/underscore aliases. The selected deployment preserves fields for inspection, accepts only ASCII letters/digits/hyphens in names, and rejects more than 64 raw fields. FastCGI explicitly forwards `QUERY_STRING` so PHP can reject competing query input. PHP validates the sole remaining credential's exact grammar. Missing/malformed input and competing query/body data exercise the application's generic denial. The proof also checks accepted forwarding, HTTP field-line optional whitespace, non-caching responses, correlation only after PHP dispatch, terminal summaries and private-log redaction. Nginx access logging is disabled and request-parse diagnostics are not enabled; an adopting deployment must prove its own log policy rather than assume a default scrubber recognizes this header.

The committed `evidence.json` records the executed cases and versions. All credentials and identity results are fixed synthetic test values. TLS, external intermediaries, HTTP/2 or HTTP/3, real credential issuance/storage/lifecycle, verifier services and production authority are outside this isolated HTTP/1.1 evidence. The application guide owns adoption: [named-header machine profile](../../../docs/stateless-authentication.md#named-header-machine-profile).
