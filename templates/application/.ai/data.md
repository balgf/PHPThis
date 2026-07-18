# Application data contract

If this application has neither database access nor CRUD-shaped data behavior, replace this file with one explicit statement saying that it is not applicable and remove its task-router entries. If CRUD-shaped behavior uses another persistence mechanism, keep the CRUD semantics section and mark only the database sections not applicable.

## Systems and schema authority

| Connection name | Engine and supported version | PDO driver | Required Composer extension | Non-secret configuration source | Schema authority |
| --- | --- | --- | --- | --- | --- |
| `{{CONNECTION_1_NAME}}` | {{CONNECTION_1_ENGINE_AND_VERSION}} | `{{CONNECTION_1_PDO_DRIVER}}` | `{{CONNECTION_1_PDO_EXTENSION}}` | `{{CONNECTION_1_CONFIG_REFERENCE}}` | `{{CONNECTION_1_SCHEMA_SOURCE}}` |
| `{{CONNECTION_2_NAME_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_ENGINE_AND_VERSION_OR_NOT_APPLICABLE}} | `{{CONNECTION_2_PDO_DRIVER_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_PDO_EXTENSION_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_CONFIG_REFERENCE_OR_NOT_APPLICABLE}}` | `{{CONNECTION_2_SCHEMA_SOURCE_OR_NOT_APPLICABLE}}` |

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

Unknown selectors and unsupported or oversized list shapes fail before database work. Prefer a finite mapping to complete statements; if finite code-owned fragments are necessary, identify the final assembly whose inferred type remains a constant-string union.

## Runtime and migration authority

| Connection | Runtime identity or non-secret reference | Required runtime capabilities | Explicitly prohibited runtime capabilities | Migration or administrative identity/reference | Isolation mechanism | Verification source and date |
| --- | --- | --- | --- | --- | --- | --- |
| `{{CONNECTION_1_NAME}}` | {{CONNECTION_1_RUNTIME_IDENTITY_REFERENCE}} | {{CONNECTION_1_REQUIRED_RUNTIME_CAPABILITIES}} | {{CONNECTION_1_PROHIBITED_RUNTIME_CAPABILITIES}} | {{CONNECTION_1_MIGRATION_IDENTITY_REFERENCE}} | {{CONNECTION_1_AUTHORITY_ISOLATION_MECHANISM}} | {{CONNECTION_1_AUTHORITY_VERIFICATION_SOURCE_AND_DATE}} |
| `{{CONNECTION_2_NAME_OR_NOT_APPLICABLE}}` | {{CONNECTION_2_RUNTIME_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}} | {{CONNECTION_2_REQUIRED_RUNTIME_CAPABILITIES_OR_NOT_APPLICABLE}} | {{CONNECTION_2_PROHIBITED_RUNTIME_CAPABILITIES_OR_NOT_APPLICABLE}} | {{CONNECTION_2_MIGRATION_IDENTITY_REFERENCE_OR_NOT_APPLICABLE}} | {{CONNECTION_2_AUTHORITY_ISOLATION_MECHANISM_OR_NOT_APPLICABLE}} | {{CONNECTION_2_AUTHORITY_VERIFICATION_SOURCE_AND_DATE_OR_NOT_APPLICABLE}} |

Runtime identities receive only the operations required by named application paths. Keep schema changes, migrations, role or user management, and other administrative capabilities isolated from runtime credentials. Least privilege limits impact; it does not replace PHT006 or bound parameters.

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

The optional profile does not choose these semantics. Cite verified product, schema, or accepted-decision authority rather than copying an example.

## Transaction and operational constraints

- Transaction isolation assumptions: {{TRANSACTION_ISOLATION_ASSUMPTIONS}}
- Cross-connection atomicity policy: {{CROSS_CONNECTION_ATOMICITY_POLICY}}
- Locking or online-change constraints: {{LOCKING_CONSTRAINTS}}
- Retention, deletion, or residency rules: {{DATA_LIFECYCLE_RULES}}
- Sensitive fields and required handling: {{SENSITIVE_FIELD_RULES}}

Do not place credentials, DSNs, connection strings, production rows, or customer data in this file. Record configuration key names, identity names, evidence references, or secret references, never their values.
