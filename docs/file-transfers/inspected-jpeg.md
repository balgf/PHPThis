# Checked JPEG inspection recipe

Use this optional `LOCAL_ADR026` / `INSPECTED_CONTENT` recipe for small baseline JPEG exports. It extends the [catalog reference](https://github.com/balgf/PHPThis/tree/main/tools/inspected-media) and shares its quarantine, quota, worker, retention and reinspection lifecycle with the [PNG operation](inspected-png.md). It adds no framework API, dependency or starter adoption. The reference's `.ai/file-transfers.md` owns the complete operation policy; `.ai/configuration.md` owns process inputs.

## Accepted profile

`CATALOG_JPEG_BASELINE_V1` accepts one deliberately restricted JFIF image:

| Boundary | Accepted value |
| --- | --- |
| Encoding | Baseline sequential Huffman DCT (`SOF0`), eight-bit YCbCr; component IDs 1, 2, 3 in order |
| Sampling | Y 1×1, 2×1 or 2×2; Cb/Cr 1×1 (4:4:4, 4:2:2 or 4:2:0) |
| Scan | Exactly one interleaved three-component scan; `Ss=0`, `Se=63`, `Ah=Al=0` |
| Header | First and only APP0 is JFIF 1.01/1.02 without a thumbnail; positive densities, unit 0/1/2 |
| Structure | SOI, APP0, DQT(s), SOF0, DHT(s), SOS, entropy, terminal EOI; at most 32 length-bearing segments |
| Tables | Nonzero eight-bit quantization, IDs 0–3; DC/AC Huffman IDs 0–3, 1–256 declared symbols; references exist, no redefinition |
| Size | 1–1,048,576 compressed bytes, 1–1,024 pixels per axis, at most 1,048,576 pixels and 3,145,728 RGB pixel bytes |
| Request | 2 MiB native multipart ceiling; exactly one `image`, no fields or query parameters |

All other APP segments and COM are rejected, including EXIF, ICC, XMP and previews. Progressive, grayscale, RGB/Adobe, CMYK/YCCK, arithmetic, lossless, other precision/sampling, restart markers, marker padding, concatenated images and leading/trailing bytes are outside this profile. Entropy permits `FF00` stuffing; EOI must end the input exactly. Ordinary camera JPEGs with EXIF and progressive web JPEGs therefore fail this operation.

There is no stripping, orientation correction, color-profile conversion, resizing or re-encoding. Successful downloads retain the exact original bytes. Supporting another variant requires an accepted definition and reinspection plan.

## Inspection and resources

`BoundedJpeg` checks this envelope and its dimensions before invoking the mature decoder. It does not implement entropy decoding. The same bytes must then pass [libjpeg-turbo 3.2.0](https://github.com/libjpeg-turbo/libjpeg-turbo/releases/tag/3.2.0):

```text
djpeg -strict -maxscans 1 -maxmemory 16M -rgb -pnm -outfile /dev/null
```

The Docker build pins the official source archive with SHA-256 `6f30092cef9fb839779646608f4ee14ae3cbac989c47fa05e841b0841f09878e`, builds static libjpeg without SIMD, arithmetic coding, TurboJPEG or Java interfaces, and runs its upstream regression suite. Only `djpeg` enters the runtime image. CMake 3.25.1-1 is a build-stage Debian dependency. PHP 8.4.26 and the existing PNG toolchain remain pinned. The image ID and explicit source/policy inventory identify each inspection definition; builds do not silently update running inspectors.

Each inspection uses a fresh network-disabled, read-only container, UID/GID 65534, dropped capabilities, no-new-privileges, no mounts, eight PIDs, 64 MiB memory/swap, one CPU and a two-second **per-process** CPU limit. PHP has 32 MiB; the decoder's `16M` option limits its internal workspace to 16,000,000 bytes, while the container owns total memory enforcement. The supervisor allows ten seconds and 8 KiB per protocol stream. Cleanup calls each allow three seconds; a separate external deadline supervises the whole command. Decoded output and decoder diagnostics go to `/dev/null`. The declared RGB byte limit describes pixel data, not every decoder allocation or PNM header.

Structural rejection returns `rejected`. Strict decoding must exit zero; any nonzero/signal result remains `scanner_error`, including corrupt entropy that the envelope cannot detect. This avoids misclassifying an operational failure as a definitive content rejection. Missing tools, invalid protocol, timeouts, resource termination, changed digests and unresolved cleanup also stay non-serving. Successful decoding can emit whitespace on stderr; diagnostics are discarded rather than interpreted. PNG's existing result mapping is preserved.

Inspection proves only this format policy under this toolchain. It is not antivirus scanning, a guarantee for arbitrary later decoders, or a storage-security guarantee. An application that later processes the file owns that decoder's separate limits and isolation.

## HTTP and shared lifecycle

The trusted routes `POST /catalog-jpeg-images` and `GET /catalog-jpeg-images/{image_id:token}` select JPEG explicitly. Existing `/catalog-images` routes remain PNG-only. Neither client filename nor MIME label selects the parser. The reservation stores the finite `png`/`jpeg` value; the worker uses it, and reads bind both the authenticated owner and expected format. A wrong-format route returns the same private `404` as missing or ineligible content.

Both formats share the existing 32-object/32-MiB capacity, including rejected/error reservations; one inspection start per second; at most two attempts per object/definition; and 24-hour serving eligibility. Promotion requires matching SHA-256 before/after inspection and verified container removal. The same exclusive mutation lock, shared lock through response emission, explicit retry/reconciliation and exact owned-object cleanup apply. A failed promotion, interrupted process or failed deletion cannot release content or capacity prematurely.

JPEG responses use `image/jpeg`, `attachment; filename="catalog-image.jpg"`, exact length, private/no-store, `nosniff` and `Accept-Ranges: none`; a range request receives the full `200`. The public composition denies all access. The HTTP proof alone injects synthetic authorization; it is not deployable authentication.

## Adopting the format column

Existing PNG catalog adopters must drain HTTP and workers before deployment. Their authorized migration path adds this constrained column to the recorded PNG-only baseline:

```sql
ALTER TABLE catalog_images ADD COLUMN format TEXT NOT NULL DEFAULT 'png'
    CHECK (format IN ('png', 'jpeg'));
```

Existing rows are PNG because that was the only operation, not because a filename or signature guesses their history. The new code binds format explicitly on every reservation. `tests/format-migration.php` exercises this statement, rollback, existing-row preservation, invalid values and refusal of a blind rerun against SQLite. It is a schema-transition proof, not a production migration runner or ledger. Adopt it through your recorded [migration history](../migrations.md), with your exact baseline, engine, coordination and recovery checks. The disposable reference creates its schema outside HTTP and Composer; it introduces no automatic migration command.

Deploy the coherent schema, code, policy and image, activate the new definition, then reinspect retained objects before resuming eligible downloads. This shared definition invalidates prior PNG **and** JPEG acceptance. The read path also checks the running source fingerprint. Format compatibility does not promise uninterrupted availability during this migration, and retired definitions cannot restore old acceptance.

Keep the literal compatibility inputs `PHPTHIS_PNG_HTTP_ROOT`, `PHPTHIS_PNG_WORKER_ROOT` and `PHPTHIS_PNG_WORKER_IMAGE` for the shared catalog. HTTP gains no Docker authority or new environment fallback. Rollback first drains JPEG traffic and workers, preserves the format distinction and all ownership/quota records until JPEG objects are resolved, and uses a reviewed new inspection definition. Never drop the column, reinterpret JPEG rows as PNG or revive stale acceptance.

## Reproducing the evidence

Run `composer test:inspected-media`, the reference README's Docker builds and `python3 tools/inspected-media/run.py`, then complete `composer check`. Frozen synthetic JPEG seeds make offline tests independent of host image extensions/tools; `tests/jpeg-fixtures.py` records their reproducible cjpeg 3.2.0 generation. The real proof covers accepted boundaries, unsupported/malformed files, strict-decoder failures, isolated resource faults, native uploads, misleading labels, cross-owner/format reads, reinspection, shared quotas and expiry. Finite diagnostics name failing cases without exposing bytes, native process output or paths. Dated `evidence.json` binds the executed sources and image identities; CI repeats the real proof.

Production adoption still requires authentication and applicable CSRF, ingress/TLS/rate controls, private durable volumes, separate HTTP/supervisor identities, capacity/inode monitoring, deployment resource enforcement, retained-data migration, supervised cleanup and incident recovery. Complete the existing [deployment](deployment.md), [storage](storage-ownership.md) and [testing](testing.md) obligations. Consumer Contract 26 and Strict Profile 4 remain unchanged; existing opaque-byte applications and the starter do not opt in automatically.
