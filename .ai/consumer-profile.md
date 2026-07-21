# Consumer-profile changes

Read `docs/consumer-profile.md`, ADR 029, and every affected ADR before changing the Alpha 2 rollup. Inspect the checked-in route, `CreateUserHandler`, `TransactionalCreateUser`, `tests/consumer-profile.php`, the supporting capability test, Composer metadata, CI matrix, and package inventory.

Keep these statements separate:

- framework behavior lives only in `src/` and the Consumer Contract;
- the account-scoped Create path, its policies, SQL, durable-job publication, and terminal summary are application patterns;
- `account_memberships` authorizes principals while `account_users` associates users; never infer one identity from the other's numeric ID;
- migration 0007 creates `account_users` without an inferred backfill and keeps the current migration ceiling explicit;
- the sanitized test policies are evidence, not identity or permission implementations;
- a proposal is unsupported until a human accepts its ownership and executable proof.

Any change must retain visible policy order, typed external values, complete finite raw SQL, explicit bindings, distinct principal and user identity tables, one connection and transaction, commit-visible job publication, constant query counts, denial-side zero writes, direct SQL-policy rejection, complete rollback, exact job-envelope verification, private post-policy failure responses, correlation, redaction, and the exact supported PHP matrix. Do not add an ORM, repository, binding helper, model layer, facade, observer, scope mechanism, service container, discovery, or automatic wiring. Run `composer check`; a focused profile test is not the complete gate.
