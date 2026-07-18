# Security baseline

AI-oriented explicitness does not replace security review.

- Bind all SQL values with named parameters.
- Validate untrusted input before domain or database use.
- Reject unknown fields and coercive values at the boundary; do not cast malformed input into an accepted type.
- Enforce `PHT001` so scalar conversion cannot silently turn unresolved `mixed` input into a trusted value.
- Encode output for its actual context.
- Keep credentials outside committed configuration.
- Map only named client failures to generic public responses; broad built-in exceptions remain unknown failures.
- Log an unknown failure once without its message, SQL, parameters, or stack, then return the generic 500 response.
- Keep superglobal reads in the front controller and enforce body, request-target, query-count, header-count, and header-value bounds in `RequestReader`.
- Query traces contain only SHA-256 SQL fingerprints and aggregate metrics; never add SQL, parameters, credentials, exception messages, or driver details.
- Add CSRF, authentication, authorization, rate limiting, and security headers as explicit application policies when required.
- Do not deserialize untrusted PHP values or execute generated PHP.

Security mechanisms must remain visible in the route-to-handler path or in the one explicitly registered request boundary. Hidden defaults are not considered protection.
