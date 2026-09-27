# RAG integration maintenance

Start with `docs/rag.md`, the single adoption-guide owner. Its verified subset and explicit exclusions define what this repository may claim about LLPhant and Qdrant. Keep the integration application-owned and preserve the existing Consumer Contract, Strict Profile and native framework dependency boundary.

Retain executable consumer evidence under `tools/consumer-lifecycle/issue-79`, excluded from the package. Ordinary checks require no model credentials, paid calls or Qdrant provisioning. A service run requires explicit scope; #79 uses a disposable local container with separate setup/teardown and deferred general migrations.

For guidance changes, reconcile the knowledge/task routes, application integration record, package inventory and existing installed-consumer distribution proof. Add `.ai/application-context.md` only when entering that distribution work. Do not copy the consumer into the starter or add a second validity gate or diagnostic.
