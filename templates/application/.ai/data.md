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

Every database behavior must choose its own budget deliberately. Test small and materially larger fixtures and assert an equal statement count.

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

Do not place credentials, DSNs, connection strings, production rows, or customer data in this file. Record configuration key names or secret references, never their values.
