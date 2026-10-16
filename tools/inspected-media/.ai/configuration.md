# Catalog process configuration

`src/ReferenceEnvironment.php` is the sole application environment reader. HTTP requires `PHPTHIS_PNG_HTTP_ROOT`; the separate console requires `PHPTHIS_PNG_WORKER_ROOT` and `PHPTHIS_PNG_WORKER_IMAGE`. The `PNG` names remain compatibility inputs for the shared PNG/JPEG/MP4 catalog, not separate authorities. There are no fallback aliases, ambient credentials, dotenv loading or runtime reloads. Inputs are read afresh for every process/request composition and never echoed.

`HttpConfiguration` and `WorkerConfiguration` parse bounded absolute root paths and a worker image exactly matching `sha256:` plus 64 lowercase hex characters before operational I/O. `InspectionDefinition` then reads its explicit code/policy inventory. The root must already have the layout and authority recorded in the single [file-transfer policy](file-transfers.md). The supervisor uses the code-owned local Docker Unix socket and a fixed minimal PATH; it does not forward HTTP/worker inputs or caller credentials to the inspector.

Activate the complete new code/policy/image definition before dependent traffic; reinspection is mandatory. Configuration-only startup proves neither permissions nor tool availability. The tests exercise absent/invalid inputs, separation, console redaction and the effective selected tool image. No configuration values belong in Composer aliases.
