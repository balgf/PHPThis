# File transfers

This knowledge set routes an AI through PHPThis's one accepted file-transfer path. Read [ADR 026](../decisions/026-bounded-file-transfers.md), then load only the page needed for the task and inspect the named source and tests.

| Task | Read | Inspect |
| --- | --- | --- |
| Accept runtime multipart input | [Request ingestion](request-ingestion.md), [Upload value](upload-value.md) | `RequestReader`, `RequestBoundary`, `Request`, front controller |
| Map upload outcomes | [Upload errors](upload-errors.md), [Failures](failures.md) | `RequestUploadError`, application upload boundary, exact error registry |
| Review client metadata | [Metadata trust](metadata-trust.md), [Security](security.md) | `RequestUpload`, application handler, public outputs and terminal evidence |
| Store an upload | [Storage ownership](storage-ownership.md), [Deployment](deployment.md) | one concrete application storage operation and filesystem configuration |
| Return a local file | [Local-file response](local-file-response.md), [Emission](emission.md) | `LocalFileBody`, `Response`, `ResponseEmitter`, outer front-controller catch |
| Handle `Range` | [Range policy](range-policy.md) | handler headers and range/full-body integration test |
| Add evidence | [Testing](testing.md), [Deployment](deployment.md) | boundary, emitter, real-SAPI, memory, filesystem, and proxy evidence |
| Check scope | [Exclusions](exclusions.md) | current decision and application-owned policy |

The installed example uses a 2 MiB multipart transport ceiling and a separate 1 MiB document limit. They demonstrate separate bounds; they are not universal application defaults.
