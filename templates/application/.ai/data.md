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

| Connection name | SQL dialect authority and version assumptions | Separately tracked migration histories or N/A and `.ai/migrations.md` reference | Engine integration-test command | Driver, session, charset, timezone, TLS, and timeout policy source |
| --- | --- | --- | --- | --- |
| `{{CONNECTION_1_NAME}}` | {{CONNECTION_1_SQL_DIALECT_POLICY}} | `{{CONNECTION_1_MIGRATION_HISTORY_REFERENCES}}` | `{{CONNECTION_1_DATABASE_INTEGRATION_TEST_COMMAND}}` | `{{CONNECTION_1_DATABASE_CONNECTION_POLICY_SOURCE}}` |
| `{{CONNECTION_2_NAME_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_SQL_DIALECT_POLICY_OR_NOT_APPLICABLE}} | `{{CONNECTION_2_MIGRATION_HISTORY_REFERENCES_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_DATABASE_INTEGRATION_TEST_COMMAND_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_DATABASE_CONNECTION_POLICY_SOURCE_OR_NOT_APPLICABLE}}` |

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

Record one separate row per adopted migration history and one separate administrative row only when that path is adopted; otherwise mark every field below not applicable. Each history's non-secret identity reference points to its separately named configuration factory and final readonly type in `.ai/configuration.md`, with no inheritance, combined credentials, or fallback.

| Migration history or administrative profile | Non-secret identity/configuration reference | Exact required and prohibited capabilities | Configuration/process-entry separation; capability isolation where supported or exact effective overlap and residual risk | Activation/deactivation accountable owner and authoritative implementation reference | Exact-engine positive and negative evidence source and date |
| --- | --- | --- | --- | --- | --- |
| `{{ELEVATED_PROFILE_1_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}` | {{ELEVATED_PROFILE_1_IDENTITY_AND_CONFIGURATION_REFERENCE_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_1_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_1_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_1_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_1_AUTHORITY_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}} |
| `{{ELEVATED_PROFILE_2_HISTORY_OR_ADMIN_NAME_OR_NOT_APPLICABLE}}` | {{ELEVATED_PROFILE_2_IDENTITY_AND_CONFIGURATION_REFERENCE_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_2_REQUIRED_AND_PROHIBITED_CAPABILITIES_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_2_EFFECTIVE_AUTHORITY_BOUNDARY_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_2_AUTHORITY_TRANSITION_OWNER_AND_IMPLEMENTATION_REFERENCE_OR_NOT_APPLICABLE}} | {{ELEVATED_PROFILE_2_AUTHORITY_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}} |

Record `GRANT` and `REVOKE` in the referenced transition implementation only where the exact engine supports and the application selects them.

Runtime identities receive only the targets and capabilities required by named application paths where the selected engine can express that separation. Keep elevated migration configuration and its process-entry path unavailable to HTTP in every engine. Isolate data-definition changes, namespace or ownership control, migrations, identity or policy administration, authority administration, and other elevated capabilities from runtime credentials where supported; otherwise record the exact effective-authority overlap and residual risk, including SQLite file-level limits. Least privilege limits impact; it does not replace PHT006 or bound parameters. Activate and verify authority against the exact engine and version before dependent code receives traffic. A failed activation or verification stops that rollout stage; drain or remove dependent code before authority deactivation or removal of a namespace or object it needs.

`.ai/configuration.md` is authoritative for exact external input names, final readonly database-configuration types, process-specific factories, identities, and injection sites. This file records non-secret identity references and database authority only. A parsed configuration value, successful connection, existing object, or completed migration is not proof that runtime authority is active. Each migration history's credentials never inherit, combine with, or fall back to runtime, another history, or administrative credentials.

`.ai/migrations.md` is authoritative per history for the exact initial baseline, source and namespace, identifiers, manifest and checksum-covered DDL/data/authority effects, bounded exact-engine ledger metadata, exact transaction/implicit-commit/DDL atomicity transitions, stable coordination namespace and reachable-topology exclusion, lost-owner safety, immutable forward recovery, authority-transition implementation, and dependency, serialization, compatibility, handoff, and per-history recovery constraints. `.ai/operations.md` owns release sequencing, cross-history partial-deployment operations, application-wide recovery runbooks, traffic enablement, later authority deactivation, and namespace or object removal. This file records the effective authority facts and accountable transition ownership shared with application data work.

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

## UUID representation and generation policy

If this application uses no UUID identifier, replace this section body with the exact standalone declaration `NOT_APPLICABLE(UUID_POLICY)`. Otherwise begin the section body with the exact standalone declaration `UUID_POLICY(ADOPTED)` and complete one row for each coherent policy group before implementation.

| Policy scope and concrete identifiers | Accepted canonical versions | Generated version and purpose | Generation owner and exact source | Generated-value metadata/time-disclosure decision | Accepted metadata-bearing UUID exposure and handling | Same-timestamp ordering scope and clock-regression behavior | Failure behavior and fallback policy | Narrower domain rules | Persistence representation and ordering assumptions | Evidence source |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `{{UUID_POLICY_1_SCOPE_AND_CONCRETE_IDENTIFIERS}}` | {{UUID_POLICY_1_ACCEPTED_CANONICAL_VERSIONS}} | {{UUID_POLICY_1_GENERATED_VERSION_AND_PURPOSE_OR_NOT_APPLICABLE}} | {{UUID_POLICY_1_GENERATION_OWNER_AND_EXACT_APPLICATION_SOURCE_PACKAGE_DATABASE_OR_EXTERNAL_SOURCE_OR_NOT_APPLICABLE}} | {{UUID_POLICY_1_GENERATED_VALUE_METADATA_AND_TIME_DISCLOSURE_DECISION}} | {{UUID_POLICY_1_ACCEPTED_METADATA_BEARING_UUID_EXPOSURE_AND_HANDLING}} | {{UUID_POLICY_1_SAME_TIMESTAMP_ORDERING_SCOPE_AND_CLOCK_REGRESSION_BEHAVIOR_OR_NOT_APPLICABLE}} | {{UUID_POLICY_1_FAILURE_AND_NO_FALLBACK_POLICY_OR_NOT_APPLICABLE}} | {{UUID_POLICY_1_NARROWER_DOMAIN_RULES_OR_NONE}} | {{UUID_POLICY_1_PERSISTENCE_REPRESENTATION_AND_ORDERING_ASSUMPTIONS}} | {{UUID_POLICY_1_EVIDENCE_SOURCE}} |

Keep accepted versions separate from the version generated for new values. The reference acceptance policy is canonical lowercase RFC-variant UUID versions 1 through 8. PHPThis recommends version 7 for newly generated database row identifiers when embedded approximate creation-time disclosure is accepted; record same-timestamp, clock-regression, process or node, and failure behavior before claiming ordering beyond the canonical version and variant bits. Choose version 4 when embedded time disclosure from newly generated values is unacceptable or random-only identifiers are required. That choice does not prevent metadata disclosure if accepted or persisted time-bearing UUID versions such as 1, 6, or 7 are exposed. Choose version 5 only for a deterministic policy with the namespace UUID, exact name bytes, canonicalization, and change behavior recorded. Record the generation owner as an application source path, selected package and version, database facility and engine version, or explicit external owner. A generated-version choice does not by itself reject or convert other accepted versions. PHPThis selects no generator, package, database facility, schema rule, or persistence representation.

## CRUD operation semantics

If there is no CRUD-shaped resource behavior, replace this section with an explicit not-applicable statement. Otherwise record defaults and resource-specific exceptions before implementation, and mark decisions for operations the application does not implement as not applicable.

| Decision | Recorded policy and authority |
| --- | --- |
| Resource identifiers and route lookup | {{CRUD_IDENTIFIER_ROUTE_LOOKUP_WRAPPER_NARROWER_RULE_AND_RECORDED_UUID_POLICY_REFERENCE}} |
| List pagination | {{CRUD_PAGINATION_MODEL_MAXIMUM_PAGE_SIZE_STABLE_ORDER_AND_CURSOR_OR_OFFSET_POLICY}} |
| Create identity and conflicts | {{CRUD_CREATE_GENERATION_POLICY_REFERENCE_DUPLICATE_CONFLICT_AND_IDEMPOTENCY_POLICY}} |
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
