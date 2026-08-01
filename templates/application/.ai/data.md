# Application data contract

If this application has no database access, replace this file so no template placeholder remains and include the exact standalone line `NOT_APPLICABLE(DATABASE)`. Its first non-heading declaration is the canonical standalone marker:

`NOT_APPLICABLE(DATABASE)`

Follow it with one brief verified explanation of the current database-free state. The installed checker treats that declaration as inconsistent with a direct canonical `PHPThis\Database\Connection::connect` call. If only some data concerns apply, retain their sections and mark the others explicitly not applicable.

## Database systems and namespace model

| Connection name | Engine and supported version | PDO driver and extension | Non-secret configuration reference | Database definition or provisioning source | Database, catalog, schema, or attachment namespace selection and qualification as supported | Namespace and object control or ownership model, or explicit N/A |
| --- | --- | --- | --- | --- | --- | --- |
| `{{CONNECTION_1_NAME}}` | {{CONNECTION_1_ENGINE_AND_VERSION}} | `{{CONNECTION_1_PDO_DRIVER}}`; `{{CONNECTION_1_PDO_EXTENSION}}` | `{{CONNECTION_1_CONFIG_REFERENCE}}` | `{{CONNECTION_1_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE}}` | {{CONNECTION_1_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY}} | {{CONNECTION_1_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}} |
| `{{CONNECTION_2_NAME_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_ENGINE_AND_VERSION_OR_NOT_APPLICABLE}} | `{{CONNECTION_2_PDO_DRIVER_OR_NOT_APPLICABLE}}`; `{{CONNECTION_2_PDO_EXTENSION_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_CONFIG_REFERENCE_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_DATABASE_DEFINITION_OR_PROVISIONING_SOURCE_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_NAMESPACE_SELECTION_AND_QUALIFICATION_POLICY_OR_NOT_APPLICABLE}} | {{CONNECTION_2_NAMESPACE_AND_OBJECT_CONTROL_OR_OWNERSHIP_MODEL_OR_NOT_APPLICABLE}} |

Database definition or provisioning, namespace selection and qualification, the namespace and object control or ownership model, and active authority are separate application facts. Configuration and source presence do not activate database authority. An engine-default namespace, an application-specific namespace, or explicit not applicability where the engine has no namespace or ownership model are all valid recorded decisions; this template prescribes none.

## Per-connection engine policy

| Connection name | SQL dialect authority and version assumptions | Migration command and policy | Engine integration-test command | Driver, session, charset, timezone, TLS, and timeout policy source |
| --- | --- | --- | --- | --- |
| `{{CONNECTION_1_NAME}}` | {{CONNECTION_1_SQL_DIALECT_POLICY}} | `{{CONNECTION_1_MIGRATION_COMMAND_AND_POLICY}}` | `{{CONNECTION_1_DATABASE_INTEGRATION_TEST_COMMAND}}` | `{{CONNECTION_1_DATABASE_CONNECTION_POLICY_SOURCE}}` |
| `{{CONNECTION_2_NAME_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_SQL_DIALECT_POLICY_OR_NOT_APPLICABLE}} | `{{CONNECTION_2_MIGRATION_COMMAND_AND_POLICY_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_DATABASE_INTEGRATION_TEST_COMMAND_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_DATABASE_CONNECTION_POLICY_SOURCE_OR_NOT_APPLICABLE}}` |

## SQL structure and bounded-input policy

Every data value is bound with a distinct named placeholder for each occurrence. SQL executes only through direct `Connection` calls, and the final SQL must resolve natively in PHPStan to a finite set of non-blank compile-time constants. Do not record a sanitizer: structural input is a selector for reviewed code-owned SQL, never SQL text.

| Connection and operation | Structural choice | Accepted selectors and code-owned mapping source | Bounded-list shapes and maximum cardinality | Final complete-statement or finite-fragment source | Verification source and date |
| --- | --- | --- | --- | --- | --- |
| `{{SQL_STRUCTURE_1_CONNECTION_AND_OPERATION}}` | {{SQL_STRUCTURE_1_CHOICE}} | {{SQL_STRUCTURE_1_SELECTORS_AND_MAPPING_SOURCE}} | {{SQL_STRUCTURE_1_LIST_BOUND_AND_SHAPES}} | {{SQL_STRUCTURE_1_FINAL_SQL_SOURCE}} | {{SQL_STRUCTURE_1_VERIFICATION_SOURCE_AND_DATE}} |
| `{{SQL_STRUCTURE_2_CONNECTION_AND_OPERATION_OR_NOT_APPLICABLE}}` | {{SQL_STRUCTURE_2_CHOICE_OR_NOT_APPLICABLE}} | {{SQL_STRUCTURE_2_SELECTORS_AND_MAPPING_SOURCE_OR_NOT_APPLICABLE}} | {{SQL_STRUCTURE_2_LIST_BOUND_AND_SHAPES_OR_NOT_APPLICABLE}} | {{SQL_STRUCTURE_2_FINAL_SQL_SOURCE_OR_NOT_APPLICABLE}} | {{SQL_STRUCTURE_2_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}} |

Unknown selectors and unsupported or oversized list shapes fail before database work. Prefer a finite mapping to complete statements; if finite code-owned fragments are necessary, identify the final assembly whose inferred type remains a constant-string union. For every bounded list or cursor, record omitted and empty-input behavior, each accepted cardinality, stable tie-break ordering, cursor version and compatibility, and whether traversal is a snapshot.

## Runtime and migration authority by operation

Record runtime authority per named application operation, not as one broad connection-level capability set. Include every intended statement source, exact target and capability required, selected prohibited capabilities, and the effective authority resolution source observed on the exact engine and version. Record only mechanisms that apply: examples may include direct privileges, roles or inheritance, public or default access, database or global privileges, ownership chains, IAM, or filesystem and process authority.

| Connection and named operation | Complete statement source | Runtime identity or non-secret reference | Exact required targets and capabilities | Selected prohibited capabilities | Effective authority resolution source | Exact-engine verification source and date |
| --- | --- | --- | --- | --- | --- | --- |
| `{{DATABASE_AUTHORITY_1_CONNECTION_AND_OPERATION}}` | `{{DATABASE_AUTHORITY_1_STATEMENT_SOURCE}}` | {{DATABASE_AUTHORITY_1_RUNTIME_IDENTITY_REFERENCE}} | {{DATABASE_AUTHORITY_1_REQUIRED_TARGETS_AND_CAPABILITIES}} | {{DATABASE_AUTHORITY_1_PROHIBITED_CAPABILITIES}} | {{DATABASE_AUTHORITY_1_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE}} | {{DATABASE_AUTHORITY_1_VERIFICATION_SOURCE_AND_DATE}} |
| `{{DATABASE_AUTHORITY_2_CONNECTION_AND_OPERATION_OR_NOT_APPLICABLE}}` | `{{DATABASE_AUTHORITY_2_STATEMENT_SOURCE_OR_NOT_APPLICABLE}}` | {{DATABASE_AUTHORITY_2_RUNTIME_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}} | {{DATABASE_AUTHORITY_2_REQUIRED_TARGETS_AND_CAPABILITIES_OR_NOT_APPLICABLE}} | {{DATABASE_AUTHORITY_2_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}} | {{DATABASE_AUTHORITY_2_EFFECTIVE_AUTHORITY_RESOLUTION_SOURCE_OR_NOT_APPLICABLE}} | {{DATABASE_AUTHORITY_2_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}} |

Record a separate migration or administrative identity only when that path is adopted; otherwise mark every field below not applicable.

- Elevated identity or non-secret reference: {{ELEVATED_DATABASE_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}}
- Exact required and prohibited elevated capabilities: {{ELEVATED_DATABASE_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}}
- Isolation from HTTP runtime: {{ELEVATED_DATABASE_AUTHORITY_ISOLATION_OR_NOT_APPLICABLE}}
- Authority activation and deactivation owner, complete non-HTTP path, and transition source; `GRANT` and `REVOKE` only where supported: {{DATABASE_AUTHORITY_ACTIVATION_AND_DEACTIVATION_PATH_OR_NOT_APPLICABLE}}
- Exact-engine positive and negative evidence source and date: {{ELEVATED_DATABASE_AUTHORITY_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}}

Runtime identities receive only the targets and capabilities required by named application paths. Keep data-definition changes, namespace or ownership control, migrations, identity or policy administration, authority administration, and other elevated capabilities unavailable to runtime credentials. Least privilege limits impact; it does not replace PHT006 or bound parameters. Activate and verify authority against the exact engine and version before dependent code receives traffic. A failed activation or verification stops that rollout stage; drain or remove dependent code before authority deactivation or removal of a namespace or object it needs.

`.ai/configuration.md` is authoritative for exact external input names, final readonly database-configuration types, process-specific factories, and injection sites. This file records non-secret identity references and database authority only. A parsed configuration value, successful connection, existing object, or completed migration is not proof that runtime authority is active. Migration or administrative credentials never fall back to runtime credentials.

`.ai/migrations.md` is authoritative for the actual adopted migration source directory and application namespace, identifiers, manifest and ledger bounds, checksums, transactions, locks, immutable forward recovery, and authority-transition implementation. `.ai/operations.md` owns release sequencing, traffic enablement, later authority deactivation, and namespace or object removal. This file records the authority facts shared with application data work.

## Scale-sensitive data

| Table or dataset | Expected scale | Required access bound | Index or plan requirement | Source and last verified |
| --- | ---: | --- | --- | --- |
| `{{DATASET_1}}` | {{DATASET_1_SCALE}} | {{DATASET_1_BOUND}} | {{DATASET_1_INDEX_REQUIREMENT}} | {{DATASET_1_SOURCE_AND_VERIFIED_DATE}} |
| `{{DATASET_2}}` | {{DATASET_2_SCALE}} | {{DATASET_2_BOUND}} | {{DATASET_2_INDEX_REQUIREMENT}} | {{DATASET_2_SOURCE_AND_VERIFIED_DATE}} |

## Per-operation database limits

| Route or operation | Connection | Statement budget | Trace fingerprint bound | Result bound | Rationale |
| --- | --- | ---: | ---: | --- | --- |
| `{{DATABASE_OPERATION_1}}` | `{{DATABASE_OPERATION_1_CONNECTION}}` | {{DATABASE_OPERATION_1_QUERY_BUDGET}} | {{DATABASE_OPERATION_1_TRACE_BOUND}} | {{DATABASE_OPERATION_1_RESULT_BOUND}} | {{DATABASE_OPERATION_1_RATIONALE}} |
| `{{DATABASE_OPERATION_2}}` | `{{DATABASE_OPERATION_2_CONNECTION}}` | {{DATABASE_OPERATION_2_QUERY_BUDGET}} | {{DATABASE_OPERATION_2_TRACE_BOUND}} | {{DATABASE_OPERATION_2_RESULT_BOUND}} | {{DATABASE_OPERATION_2_RATIONALE}} |

Database timeout policy: {{DATABASE_TIMEOUT_POLICY}}.

Every database behavior must choose its own budget deliberately. Test small and materially larger fixtures and assert an equal statement count. Submit adversarial strings as bound data, and test unknown selectors and unsupported list shapes as pre-database failures.

## Request-policy data work

If no route is protected, record `NOT_APPLICABLE(REQUEST_POLICY)` for this section. Otherwise list policy reads separately from protected handler work.

| Protected route and action | Authentication connection and budget | Tenant-resolution connection and budget | Authorization connection and budget | Protected connection and budget | Tenant/resource SQL scope and authorization-to-write race policy |
| --- | --- | --- | --- | --- | --- |
| `{{PROTECTED_OPERATION_1}}` | {{PROTECTED_OPERATION_1_AUTHENTICATION_QUERY_POLICY}} | {{PROTECTED_OPERATION_1_TENANT_QUERY_POLICY}} | {{PROTECTED_OPERATION_1_AUTHORIZATION_QUERY_POLICY}} | {{PROTECTED_OPERATION_1_DATA_QUERY_POLICY}} | {{PROTECTED_OPERATION_1_TENANT_SCOPE_AND_RACE_POLICY}} |

Every connection has a distinct trace. A denial may consume only its recorded policy-read budget and must leave the protected budget and database state unchanged. Successful authorization does not replace the tenant and resource predicates in protected SQL.

## Optional server-side cache data

If the application has no server-side cache, record `NOT_APPLICABLE(CACHE)` for this section. Otherwise complete every field before implementation.

| Typed cache service and owned projection | Authoritative rebuild source | Versioned key schema | Environment and tenant isolation | Parsed payload schema and bounds | TTL and staleness bound |
| --- | --- | --- | --- | --- | --- |
| `{{CACHE_SERVICE_1_AND_PROJECTION}}` | {{CACHE_SERVICE_1_AUTHORITATIVE_SOURCE}} | `{{CACHE_SERVICE_1_KEY_SCHEMA_AND_VERSION}}` | {{CACHE_SERVICE_1_ENVIRONMENT_AND_TENANT_ISOLATION}} | {{CACHE_SERVICE_1_PAYLOAD_SCHEMA_AND_BOUNDS}} | {{CACHE_SERVICE_1_TTL_AND_STALENESS_BOUND}} |
| `{{CACHE_SERVICE_2_AND_PROJECTION_OR_NOT_APPLICABLE}}` | {{CACHE_SERVICE_2_AUTHORITATIVE_SOURCE_OR_NOT_APPLICABLE}} | `{{CACHE_SERVICE_2_KEY_SCHEMA_AND_VERSION_OR_NOT_APPLICABLE}}` | {{CACHE_SERVICE_2_ENVIRONMENT_AND_TENANT_ISOLATION_OR_NOT_APPLICABLE}} | {{CACHE_SERVICE_2_PAYLOAD_SCHEMA_AND_BOUNDS_OR_NOT_APPLICABLE}} | {{CACHE_SERVICE_2_TTL_AND_STALENESS_BOUND_OR_NOT_APPLICABLE}} |

- Invalidation trigger, ordering after authoritative commit, failure policy, and stale-refill race mitigation or accepted bound: {{CACHE_INVALIDATION_AND_STALE_REFILL_POLICY_OR_NOT_APPLICABLE}}
- Eviction, corruption, and missing-value behavior: {{CACHE_EVICTION_CORRUPTION_AND_MISS_POLICY_OR_NOT_APPLICABLE}}
- Serialization and parser boundary: {{CACHE_SERIALIZATION_AND_PARSER_POLICY_OR_NOT_APPLICABLE}}

Every key includes a reviewed schema version and the applicable environment and tenant ownership. Every payload is parsed as untrusted external input into a bounded typed projection. A cache entry is never authoritative, and a TTL is a maximum staleness policy rather than a promise that an entry remains available.

## CRUD operation semantics

If there is no CRUD-shaped resource behavior, replace this section with an explicit not-applicable statement. Otherwise record defaults and resource-specific exceptions before implementation, and mark decisions for operations the application does not implement as not applicable.

| Decision | Recorded policy and authority |
| --- | --- |
| Resource identifiers and route lookup | {{CRUD_IDENTIFIER_TYPE_GENERATION_PUBLIC_REPRESENTATION_AND_ROUTE_POLICY}} |
| List pagination | {{CRUD_PAGINATION_MODEL_MAXIMUM_PAGE_SIZE_STABLE_ORDER_AND_CURSOR_OR_OFFSET_POLICY}} |
| Create identity and conflicts | {{CRUD_CREATE_IDENTITY_GENERATION_DUPLICATE_CONFLICT_AND_IDEMPOTENCY_POLICY}} |
| Update method and concurrency | {{CRUD_PUT_PATCH_OMITTED_NULL_AND_CONCURRENT_WRITE_POLICY}} |
| Missing-resource behavior | {{CRUD_MISSING_BEHAVIOR_BY_GET_LIST_UPDATE_AND_DELETE_OPERATION}} |
| Delete and retention | {{CRUD_HARD_OR_SOFT_DELETE_RETENTION_RESTORE_AND_DEPENDENT_RECORD_POLICY}} |
| Authorization and audit | {{CRUD_AUTHORIZATION_CHECK_AND_AUDIT_EVENT_POLICY}} |

For each resource route, the identifier policy records the narrowest fixed declaration among `positive-int`, `uuid`, `ulid`, and genuinely opaque `token`, its matching `PathParameters` accessor, the application-owned route-specific identifier wrapper, and any narrower validation performed before database work. Route matching returns unchanged routing metadata; it does not normalize, bind or look up a record, generate an identifier, choose persistence, or fall back between types.

The optional profile does not choose these semantics. Cite verified product, schema, or accepted-decision authority rather than copying an example.

## Transaction and operational constraints

- Transaction isolation assumptions: {{TRANSACTION_ISOLATION_ASSUMPTIONS}}
- Cross-connection atomicity policy: {{CROSS_CONNECTION_ATOMICITY_POLICY}}
- Locking or online-change constraints: {{LOCKING_CONSTRAINTS}}
- Retention, deletion, or residency rules: {{DATA_LIFECYCLE_RULES}}
- Sensitive fields and required handling: {{SENSITIVE_FIELD_RULES}}

Do not place credentials, DSNs, connection strings, production rows, or customer data in this file. Record identity names and evidence references here; record configuration key names without values in `.ai/configuration.md`.
