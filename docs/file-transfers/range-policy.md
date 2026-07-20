# Range policy

Byte ranges are not implemented. A request containing `Range` receives the same complete `200` representation as a request without it. The example advertises `Accept-Ranges: none`.

File responses reject status `206` and `Content-Range`. No code parses range units, suffix ranges, multiple ranges, invalid ranges, conditional ranges, or multipart byte-range output. The request header remains ordinary bounded transport metadata and does not affect storage reads.

This is an explicit deferral, not accidental partial support. Tests submit `Range: bytes=0-1`, require status 200, and compare the complete downloaded hash.

Adopting ranges requires a new decision covering validators, `If-Range`, satisfiable and unsatisfiable forms, exact `206` and `416` framing, overflow, multiple ranges, file mutation races, bounded seek/read work, cache interaction, authorization, and real-client integration. Do not incrementally add `Content-Range` to the current response.
