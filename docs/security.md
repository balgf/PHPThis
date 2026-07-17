# Security baseline

AI-oriented explicitness does not replace security review.

- Bind all SQL values with named parameters.
- Validate untrusted input before domain or database use.
- Reject unknown fields and coercive values at the boundary; do not cast malformed input into an accepted type.
- Enforce `PHT001` so scalar conversion cannot silently turn unresolved `mixed` input into a trusted value.
- Encode output for its actual context.
- Keep credentials outside committed configuration.
- Return generic public failures and log internal details once.
- Query traces contain only SHA-256 SQL fingerprints and aggregate metrics; never add SQL, parameters, credentials, exception messages, or driver details.
- Add CSRF, authentication, authorization, rate limiting, and security headers as explicit application policies when required.
- Do not deserialize untrusted PHP values or execute generated PHP.

Security mechanisms must remain visible in the route-to-handler path or in one explicitly registered boundary. Hidden defaults are not considered protection.
