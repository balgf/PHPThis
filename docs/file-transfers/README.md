# File transfers

This knowledge set routes an AI through PHPThis's one accepted file-transfer path. Load only the page needed for the task and inspect the named source and tests. Read [ADR 026](../decisions/026-bounded-file-transfers.md) only when reviewing or changing its bounded file-transfer decision; ordinary implementation follows this current knowledge set without loading that historical record.

| Task | Read | Inspect |
| --- | --- | --- |
| Adopt or review file transfers | [Security](security.md), [Deployment](deployment.md), [Testing](testing.md) | the application's single authoritative `.ai/file-transfers.md` adoption record, selected policy path, and complete application gate |
| Accept runtime multipart input | [Request ingestion](request-ingestion.md), [Upload value](upload-value.md) | `RequestReader`, `RequestBoundary`, `Request`, front controller |
| Map upload outcomes | [Upload errors](upload-errors.md), [Failures](failures.md) | `RequestUploadError`, application upload boundary, exact error registry |
| Review client metadata | [Metadata trust](metadata-trust.md), [Security](security.md) | `RequestUpload`, application handler, public outputs and terminal evidence |
| Store an upload | [Storage ownership](storage-ownership.md), [Deployment](deployment.md) | one concrete application storage operation and filesystem configuration |
| Return a local file | [Local-file response](local-file-response.md), [Emission](emission.md) | `LocalFileBody`, `Response`, `ResponseEmitter`, outer front-controller catch |
| Handle `Range` | [Range policy](range-policy.md) | handler headers and range/full-body integration test |
| Add evidence | [Testing](testing.md), [Deployment](deployment.md) | boundary, emitter, real-SAPI, memory, filesystem, and proxy evidence |
| Check scope | [Exclusions](exclusions.md) | current decision and application-owned policy |

The installed example uses a 2 MiB multipart transport ceiling and separately accepts 0 through 1,048,576 document bytes inclusive. They demonstrate separate bounds and an explicit zero-byte choice; they are not universal application defaults.

An application adopts this optional path only by replacing `NOT_APPLICABLE(FILE_TRANSFER)` in its `.ai/file-transfers.md` with one complete verified policy. That file is the sole writable owner for file-transfer routes, pre-PHP ingress, request policy, temporary and durable storage, content treatment, quotas, lifecycle, response, and evidence. Tests and other guides reference it rather than restating a competing policy. The executable example is a public non-production transport and filesystem proof, not a protected-upload recommendation.
