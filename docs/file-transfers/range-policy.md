# Range policy

Under `LOCAL_ADR026`, byte ranges are not implemented. A request containing `Range` receives the same complete `200` representation as a request without it. The example advertises `Accept-Ranges: none`. `AMAZON_S3_ADR053` has a separate accepted residual range policy: S3 may honor a client's request, so that profile makes no range-free or full-response claim.

Under `LOCAL_ADR026`, file responses reject status `206` and `Content-Range`. No framework code parses range units, suffix ranges, multiple ranges, invalid ranges, conditional ranges, or multipart byte-range output. The request header remains ordinary bounded transport metadata and does not affect local storage reads. The accepted S3 profile instead owns the residual range behavior named above.

The local behavior is an explicit deferral, not accidental partial support. `LOCAL_ADR026` tests submit `Range: bytes=0-1`, require status 200, and compare the complete downloaded hash.

Adding framework-owned range support requires a new decision covering validators, `If-Range`, satisfiable and unsatisfiable forms, exact `206` and `416` framing, overflow, multiple ranges, file mutation races, bounded seek/read work, cache interaction, authorization, and real-client integration. Do not incrementally add `Content-Range` to the local response.
