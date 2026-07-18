# Application data contract

If this application has no database, replace this file with one explicit statement saying that it is not applicable and remove its task-router entries.

## Systems and schema authority

- Database engine and supported version: {{DATABASE_ENGINE_AND_VERSION}}
- Schema source of truth: `{{SCHEMA_SOURCE_PATH}}`
- Migration command and policy: `{{MIGRATION_COMMAND_AND_POLICY}}`

## Scale-sensitive data

| Table or dataset | Expected scale | Required access bound | Index or plan requirement | Source and last verified |
| --- | ---: | --- | --- | --- |
| `{{DATASET_1}}` | {{DATASET_1_SCALE}} | {{DATASET_1_BOUND}} | {{DATASET_1_INDEX_REQUIREMENT}} | {{DATASET_1_SOURCE_AND_VERIFIED_DATE}} |
| `{{DATASET_2}}` | {{DATASET_2_SCALE}} | {{DATASET_2_BOUND}} | {{DATASET_2_INDEX_REQUIREMENT}} | {{DATASET_2_SOURCE_AND_VERIFIED_DATE}} |

## Per-operation database limits

| Route or operation | Statement budget | Trace fingerprint bound | Result bound | Rationale |
| --- | ---: | ---: | --- | --- |
| `{{DATABASE_OPERATION_1}}` | {{DATABASE_OPERATION_1_QUERY_BUDGET}} | {{DATABASE_OPERATION_1_TRACE_BOUND}} | {{DATABASE_OPERATION_1_RESULT_BOUND}} | {{DATABASE_OPERATION_1_RATIONALE}} |
| `{{DATABASE_OPERATION_2}}` | {{DATABASE_OPERATION_2_QUERY_BUDGET}} | {{DATABASE_OPERATION_2_TRACE_BOUND}} | {{DATABASE_OPERATION_2_RESULT_BOUND}} | {{DATABASE_OPERATION_2_RATIONALE}} |

Database timeout policy: {{DATABASE_TIMEOUT_POLICY}}.

Every database behavior must choose its own budget deliberately. Test small and materially larger fixtures and assert an equal statement count.

## Transaction and operational constraints

- Transaction isolation assumptions: {{TRANSACTION_ISOLATION_ASSUMPTIONS}}
- Locking or online-change constraints: {{LOCKING_CONSTRAINTS}}
- Retention, deletion, or residency rules: {{DATA_LIFECYCLE_RULES}}
- Sensitive fields and required handling: {{SENSITIVE_FIELD_RULES}}

Do not place credentials, connection strings, production rows, or customer data in this file.
