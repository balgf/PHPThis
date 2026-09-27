# Application-owned RAG with LLPhant and Qdrant

PHPThis can host a bounded retrieval-augmented generation operation through ordinary typed PHP and explicit dependencies. LLPhant and the Qdrant PHP client are optional **application dependencies**; Qdrant is an external service. PHPThis supplies no model client, vector store, agent runtime, discovery, or new framework dependency. The Consumer Contract and Strict Profile remain unchanged.

Before adoption, record the selected recipe in application `.ai/integrations.md`, replacing `NOT_APPLICABLE(RAG)`. Keep configuration in `.ai/configuration.md`, authority in `.ai/request-policy.md`, document lifecycle in `.ai/data.md`, service setup/recovery in `.ai/operations.md`, and evidence in `.ai/testing.md`. This guide owns the adoption requirements; the retained consumer is a bounded example, not a production default.

## Verified slice and its limits

The [issue 79 retained consumer](https://github.com/balgf/PHPThis/tree/main/tools/consumer-lifecycle/issue-79) pins PHP 8.4.19, PHPThis `a6a08c80ecb54eb282b00359e7f476506e12bc0b` (Contract 18 / Profile 4), LLPhant 1.0.3, `hkulekci/qdrant` 1.0.0, Guzzle PSR-7 2.13.1, and Qdrant 1.19.1. Its lock and report retain complete revisions, HTTP transport/runtime identity, server digest, failed attempts, and replay instructions. The framework package excludes this maintainer evidence and its consumer bundle.

The consumer exercises LLPhant's `OllamaEmbeddingGenerator` and `AnthropicChat` against **deterministic protocol fixtures**, with real disposable Qdrant. `phpthis-fixture-embedding-v1` (8 dimensions, embedding version `fixture-8-v1`) and `phpthis-fixture-chat-v1` are synthetic identities, not deployable models. No Ollama model or Anthropic service was contacted. This verifies integration mechanics, not provider compatibility, semantic retrieval, answer grounding, billing, or production readiness. The normal bootstrap denies all question requests; test policies are not credential support.

## Compose the actual execution path

Use a named action-specific HTTP policy adapter followed by narrowly typed operations. A complete question response follows:

```text
authenticate -> resolve tenant -> authorize documents -> parse question
 -> embed question -> one scoped Qdrant query -> validate bounded context
 -> generate once -> validate answer and source IDs -> complete JSON Response
```

Construct the PSR-18 clients, request/stream factories, LLPhant components, Qdrant client, operations and handler explicitly. Keep ingestion, setup and queries separate. Do not adopt LLPhant's autonomous tools or recursive orchestration as an implicit execution pattern.

Version-specific findings matter:

- LLPhant 1.0.3's stock `QdrantVectorStore` still discovers an HTTP client in its constructor, writes a payload without tenant authority, forces exact search, treats lookup failures as creation triggers, and suppresses collection-deletion failures. A later `setClient()` does not fix constructor discovery. The recipe uses a directly constructed Qdrant client with explicit PSR-7 requests instead of this adapter.
- LLPhant's OpenAI path with `openai-php/client` 0.19.2 still reaches factory discovery in `Payload::toRequest()`, even with an injected HTTP client. It is **not verified as discovery-free**. Do not patch vendor files or approve that path based only on constructor injection.
- The selected Ollama and Anthropic paths accept explicit HTTP clients and factories. A throwing discovery strategy tests construction and execution. An application-owned Ollama protocol adapter adds top-level `truncate: false`, which LLPhant 1.0.3's `embedText()` omits; the Anthropic boundary rejects incomplete or tool-use responses before LLPhant can continue. Fixed call budgets remain a second bound. These are small protocol boundaries, not a generic RAG framework.

Recheck these paths on upgrades. A passing application profile does not inspect every transitive vendor execution path.

## Authority, data and lifecycle

The application owns tenant, document and reader identities. Derive collection names and filters from trusted policy, never from question JSON or model output. Intersect tenant, authorized document IDs, reader membership and embedding version in the database query; validate returned metadata again before constructing model context. Denials and malformed input perform zero protected I/O. Cross-tenant and ordinary forbidden responses follow the existing [request-policy contract](request-policy.md).

Treat retrieved text as untrusted content, including prompt-injection attempts. Keep it separate from instructions, enable no tools, bound it, and restrict generated citations to retrieved source IDs. These measures do not prove factual grounding or neutralize every prompt injection. Output remains untrusted text requiring the receiving UI's ordinary encoding policy.

Provision collections and payload indexes in a separate explicit command. Missing collections and service errors fail the request; they never trigger creation. Record vector dimensions, distance, model/digest and embedding version together. Reindex intentionally when that identity changes; do not mix versions or silently change dimensions.

The example accepts at most four nonempty UTF-8 paragraphs, each at most 1,024 bytes and 4,096 bytes total. It embeds them explicitly, deletes four stable chunk IDs in one waited call, then upserts one bounded batch. IDs incorporate tenant, document, embedding version and slot. Repeat ingestion does not duplicate chunks; shrinking and deletion remove stale content. Database calls stay outside loops. This is a **single-writer, non-atomic replacement**: failure after deletion can leave a gap. Tests prove that failure is surfaced and explicit replay repairs it. Concurrent ingestion, authorization revocation, durable ingestion jobs and atomic cutover need their own application policy and evidence before production use.

## Budgets, failures and operations

Record and enforce input bytes, chunk count/size, vector length/finite values, result count, context bytes, output bytes/tokens, connect/total timeouts and maximum calls. The example caps questions at 512 UTF-8 bytes, retrieval at three chunks / 3,072 content bytes, and generation at 256 requested tokens / 2,048 returned JSON bytes. HTTP request and response bodies are capped at 65,536 bytes. The Qdrant loopback transport has a 200 ms connect and 2 s total deadline, disables redirects and proxies, and accepts only the supplied owned endpoint. Model transports are in-process fixtures, not evidence of live-provider deadlines.

Per operation, query costs one embedding, one search and at most one generation call; empty retrieval skips generation. Ingestion makes at most four visible embedding calls and two Qdrant calls; deletion makes one Qdrant call. There are no automatic retries. PHPThis SQL query budgets do not count model or Qdrant requests: use separate bounded accounting. Retain only fixed service/outcome names, attempt counts and timings; omit credentials, prompts, document content and arbitrary provider errors. These external traces do not extend the framework terminal-summary schema.

The example maps malformed structure to generic 400, unacceptable values to 422, policy denials to 401/403, and integration failures to 503, with private, no-store responses. Native JSON decoding does not prove duplicate-key rejection. Deployment still needs explicit credentials, TLS, egress policy, rate/concurrency limits, authorization freshness, durable storage, backup/recovery, retention/deletion and operator ownership. Record those decisions rather than inferring them from a local test.

## Verification before recommending an adoption

Run the complete installed consumer gate with deterministic providers, plus an explicitly authorized isolated-service suite. Prove policy order and replacement, zero protected denial/input work, server-side filtering and payload revalidation, empty retrieval, malformed vectors/results/citations, repeat/shrink/delete, interrupted writes, missing collections, response caps, timeout/service failure, no retries/discovery and redaction. Preserve failed attempts and their repairs.

Select exact versus approximate search explicitly. The retained consumer selects exact cosine search; both exact and approximate-eligible requests were observed on synthetic fixtures, with fixed external call counts at 6 and 134 stored points. Its report records indexing and timing observations. Qdrant may choose a scan or payload-index path for a selective query even with approximate search enabled; these observations are not an ANN benchmark. A live adoption needs a representative, authorized corpus and labeled retrieval/grounding evaluation before quality or performance claims.

Use the retained lock to reproduce the demonstrated slice. Before a live provider run, select the actual provider/model revision, dimensions, credentials/configuration boundary, deadlines, error contract and spending authorization, then verify that exact path. Consult the selected [Ollama embedding protocol](https://docs.ollama.com/api/embed), [Anthropic Messages protocol](https://platform.claude.com/docs/en/api/messages), and [Qdrant indexing/search behavior](https://qdrant.tech/documentation/manage-data/indexing/); protocol documentation alone is not execution evidence.
