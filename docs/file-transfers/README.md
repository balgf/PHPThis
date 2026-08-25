# File transfers

This knowledge set routes an AI through PHPThis's accepted application-owned file-transfer profiles. Load only the page needed for the task and inspect the named application source and tests. Read [ADR 026](../decisions/026-bounded-file-transfers.md) or [ADR 053](../decisions/053-application-owned-amazon-s3-file-transfers.md) only when reviewing or changing the decision itself; ordinary implementation follows this current knowledge set.

Consumer Contract version 17 carries version 16 and version 13's requirement for exactly one deliberate selection: `LOCAL_ADR026` for ADR 026's local move and local response under ADR 060's pending-output preflight, or optional `AMAZON_S3_ADR053` for the accepted [Amazon S3 guidance](amazon-s3.md) and [verification](amazon-s3-verification.md). The S3 profile accepts fixed `application/octet-stream` attachment delivery without a guaranteed `nosniff` header as a narrow direct-S3 exception. It does not weaken the local profile or make either profile an automatic application default.

| Task | Read | Inspect |
| --- | --- | --- |
| Adopt or review file transfers | [Security](security.md), [Deployment](deployment.md), [Testing](testing.md) | the application's single authoritative `.ai/file-transfers.md` adoption record, selected policy path, and complete application gate |
| Accept runtime multipart input | [Request ingestion](request-ingestion.md), [Upload value](upload-value.md) | `RequestReader`, `RequestBoundary`, `Request`, front controller |
| Map upload outcomes | [Upload errors](upload-errors.md), [Failures](failures.md) | `RequestUploadError`, application upload boundary, exact error registry |
| Review client metadata | [Metadata trust](metadata-trust.md), [Security](security.md) | `RequestUpload`, application handler, public outputs and terminal evidence |
| Store an upload locally | [Storage ownership](storage-ownership.md), [Deployment](deployment.md) | one concrete application storage operation and filesystem configuration under `LOCAL_ADR026` |
| Adopt or review the Amazon S3 profile | [Amazon S3 guidance](amazon-s3.md), [verification](amazon-s3-verification.md) | exact `AMAZON_S3_ADR053` application policy, source, behavior, isolated real-AWS, deployment evidence, and complete gate |
| Return a local file | [Local-file response](local-file-response.md), [Emission](emission.md) | `LocalFileBody`, `Response`, `ResponseEmitter`, outer front-controller catch |
| Handle `Range` | [Range policy](range-policy.md) | handler headers and range/full-body integration test |
| Add evidence | [Testing](testing.md), [Deployment](deployment.md) | common boundary/real-SAPI/proxy evidence plus the selected profile's storage, delivery, resource, service, and deployment evidence |
| Check scope | [Exclusions](exclusions.md) | current decision and application-owned policy |

The installed example uses a 2 MiB multipart transport ceiling and separately accepts 0 through 1,048,576 document bytes inclusive. They demonstrate separate bounds and an explicit zero-byte choice; they are not universal application defaults. The example remains a local public non-production reference and contains no AWS dependency, configuration, credential source, or S3 call.

An application adopts file transfer only by replacing `NOT_APPLICABLE(FILE_TRANSFER)` in its `.ai/file-transfers.md` with exactly one selected profile and one complete verified policy. That file is the sole writable owner for file-transfer routes, pre-PHP ingress, request policy, temporary and durable storage, content treatment, quotas, lifecycle, response, and evidence. Tests and other guides reference it rather than restating a competing policy. The executable example is a public non-production `LOCAL_ADR026` transport and filesystem proof, not a protected-upload or S3 recommendation.
