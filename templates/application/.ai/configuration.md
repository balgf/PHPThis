# Application configuration context

This file is the application's single writable authority for configuration. Other `.ai/` guides link here and retain only their concern-specific runtime, topology, behavior, or evidence facts. Record names and contracts only. Never place credentials, tokens, private keys, DSNs containing secrets, customer values, or production payloads in this file.

- Configuration boundary PHP file: `{{CONFIGURATION_BOUNDARY_PATH_OR_NOT_APPLICABLE}}`
- HTTP/runtime factory and final readonly output type: `{{RUNTIME_CONFIGURATION_FACTORY_AND_TYPE_OR_NOT_APPLICABLE}}`
- CLI/worker/WebSocket factories and final readonly output types: `{{OTHER_RUNTIME_CONFIGURATION_FACTORIES_AND_TYPES_OR_NOT_APPLICABLE}}`
- Migration/administrative factories and final readonly output types: `{{ELEVATED_CONFIGURATION_FACTORIES_AND_TYPES_OR_NOT_APPLICABLE}}`
- External input names, source owner, required/optional policy, grammar, and byte bounds: `{{CONFIGURATION_INPUT_NAMES_AND_CONTRACTS_OR_NOT_APPLICABLE}}`
- Runtime versus migration/administrative authority separation and no-fallback evidence: `{{CONFIGURATION_AUTHORITY_SEPARATION_OR_NOT_APPLICABLE}}`
- Composition injection sites: `{{CONFIGURATION_INJECTION_SITES_OR_NOT_APPLICABLE}}`
- Missing, empty, malformed, oversized, and pre-I/O failure behavior: `{{CONFIGURATION_FAILURE_POLICY_OR_NOT_APPLICABLE}}`
- Rotation, reload, and process-restart ownership: `{{CONFIGURATION_ROTATION_POLICY_OR_NOT_APPLICABLE}}`
- Redaction and non-disclosure evidence: `{{CONFIGURATION_REDACTION_EVIDENCE_OR_NOT_APPLICABLE}}`
- Automated configuration tests and real child-process entrypoint evidence: `{{CONFIGURATION_TESTS_OR_NOT_APPLICABLE}}`

When process environment is used, every direct `\getenv('EXACT_LITERAL_KEY')` call belongs in the one recorded PHP file. Entry points call only their matching process factory once during composition. Behavior code receives concrete typed dependencies, never this reader or a string-keyed configuration bag.

No process needs credentials outside its authority. Migration or administrative values never fall back to runtime values. PHPThis provides no configuration service, helper, facade, container binding, automatic dotenv loader, secret-manager abstraction, or hidden reload.
