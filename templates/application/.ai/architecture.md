# Application architecture

## Entry and composition

- Front controller: `{{FRONT_CONTROLLER_PATH}}`
- Composition root: `{{COMPOSITION_ROOT_PATH}}`
- Root route manifest: `{{ROUTE_MANIFEST_PATH}}`
- Application source root: `{{APPLICATION_SOURCE_PATH}}`

## Dependency direction

```text
{{DEPENDENCY_DIRECTION}}
```

Dependencies may point only in the direction shown above. Document every deliberate exception in `docs/decisions/` before implementation.

## Named boundaries

| Boundary | Path | Responsibility |
| --- | --- | --- |
| HTTP runtime | `{{HTTP_BOUNDARY_PATH}}` | {{HTTP_BOUNDARY_RESPONSIBILITY}} |
| Database | `{{DATABASE_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{DATABASE_BOUNDARY_RESPONSIBILITY}} |
| External services | `{{INTEGRATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}` | {{INTEGRATION_BOUNDARY_RESPONSIBILITY}} |

## Identity and authorization

- Identity source and representation: {{IDENTITY_SOURCE_AND_REPRESENTATION}}
- Authentication boundary: `{{AUTHENTICATION_BOUNDARY_PATH}}`
- Authorization owner and check location: `{{AUTHORIZATION_OWNER_AND_PATH}}`
- Tenant boundary: {{TENANT_BOUNDARY_OR_NOT_APPLICABLE}}
- Deny-by-default rule: {{DENY_BY_DEFAULT_RULE}}

## Optional CRUD reference profile

- Adoption or coherent alternate organization: {{CRUD_PROFILE_DECISION}}
- Feature and operation directory rule: `{{CRUD_DIRECTORY_RULE_OR_NOT_APPLICABLE}}`
- Identifier representation and route binding: {{CRUD_IDENTIFIER_AND_ROUTE_BINDING_POLICY_OR_NOT_APPLICABLE}}
- Explicit route-shape and HTTP-method policy: {{CRUD_ROUTE_AND_METHOD_POLICY_OR_NOT_APPLICABLE}}
- Authorization owner and check location by operation: {{CRUD_AUTHORIZATION_POLICY_OR_NOT_APPLICABLE}}
- Audit-event owner and emission boundary: {{CRUD_AUDIT_POLICY_OR_NOT_APPLICABLE}}
- Accepted deviation record: `{{CRUD_PROFILE_DECISION_RECORD_OR_NOT_APPLICABLE}}`

The installed `vendor/phpthis/framework/docs/crud.md` profile recommends structure only. An alternate directory and naming policy cannot weaken the installed consumer contract or Strict Profile, and layout never determines identifiers, routes, authorization, or audit behavior.

## Placement rules

- Routes are grouped by `{{ROUTE_AREA_RULE}}`.
- Handlers are placed at `{{HANDLER_PATH_RULE}}`.
- Commands and projections are placed at `{{BOUNDARY_VALUE_PATH_RULE}}`.
- Cross-cutting application behavior requires an accepted decision record; do not invent providers, middleware, discovery, or helper layers.
