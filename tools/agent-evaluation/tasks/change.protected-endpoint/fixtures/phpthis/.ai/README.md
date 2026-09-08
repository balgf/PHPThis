# Current knowledge map

After the required entrypoints in `AGENTS.md`, start with `.ai/request-policy.md` for this protected endpoint task. Also read `.ai/architecture.md` and `.ai/data.md` if not already read. `API.md` fixes behavior; `.ai/testing.md` owns the complete gate and observation transport, `.ai/observability.md` the recorded measurements, and `.ai/operations.md` the local runtime. Other local guides record explicit non-adoption.

## Installed task route

These initial excerpts are verified against the installed `composer.lock` framework reference `094f61ef29636536ccfd706e514e4ffa11511ae6`. Keep the full consumer-contract read and complete Strict Profile. The installed knowledge map permits the smallest relevant row; these sections address this existing handler and its fixed synthetic policy, SQLite driver, schema, and API. They do not waive requirements or introduce a read limit. If the installed reference or headings differ, reroute through the installed knowledge map before relying on the slices.

Read these current sections:

- `request-handling.md`: Query parameters; opening Routing metadata; Protected request policy; Operation input boundaries. `sed -n '24,41p;121,126p;161,170p' "vendor/phpthis/framework/docs/request-handling.md"`
- `request-policy.md`: the complete current guide. `sed -n '1,52p' "vendor/phpthis/framework/docs/request-policy.md"`
- `database.md`: introduction; SQL data and finite structure; Database authority before its activation lifecycle; Query trace policy. `sed -n '1,8p;57,78p;123,156p' "vendor/phpthis/framework/docs/database.md"`
- `type-safety.md`: introduction and Database projections; Validation, normalization, encoding, and authorization through Query parameters; Static generics and Enforcement. `sed -n '1,18p;37,56p;63,71p' "vendor/phpthis/framework/docs/type-safety.md"`
- `errors.md`: the introductory error and failure contract, before Outer HTTP failures. `sed -n '1,25p' "vendor/phpthis/framework/docs/errors.md"`

Follow additional current owners whenever the work enters their concern. Credential profiles, production security or authority, new transport/composition, optional recipes, and contract history are conditional context; consuming the supplied synthetic policy does not adopt those profiles. A PHPThis diagnostic routes to its installed profile/rule documentation and source; PHPStan diagnostics follow the exact offline reference. Inspect concrete source and public tests on every changed execution path. No new policy, recipe implementation, or observation bypass is authorized.
