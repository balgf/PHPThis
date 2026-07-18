# ADR 011: AI-first authoring with human accountability

Status: accepted

## Context

PHPThis assumes that an AI will author most application code and answer most questions about how that code should be written. A traditional framework manual is optimized for a person to browse, memorize APIs, and translate examples into code. Making that the primary interface would preserve a human-first workflow while adding AI on top.

An ungrounded conversational answer is not a safe replacement. A model can remember a different framework, a different PHPThis version, or a plausible feature that PHPThis does not provide. AI-first authoring also does not remove human responsibility for product intent or consequential technical choices.

## Decision

AI is the primary code author and knowledge interface for PHPThis applications. Humans direct the work, decide consequential product, architecture, security, data, and operational tradeoffs, approve accepted application decisions, and remain accountable for outcomes.

PHPThis does not maintain a traditional tutorial-style framework manual as its canonical interface. The short public README remains an installation, status, and evaluation entrypoint. The installed package instead supplies versioned knowledge artifacts:

- the consumer contract and Strict Profile define the validity floor;
- `docs/knowledge-map.md` routes questions to the smallest relevant contract, decision, source, and test;
- framework source and tests establish current executable behavior;
- application-owned `AGENTS.md`, `.ai/`, and decision records supply local facts and stronger constraints;
- stable diagnostics and the complete check provide repair instructions and evidence.

An AI answering a framework question must inspect the installed version and application rather than rely on model memory. It distinguishes current behavior, application policy, and proposals, and names the files or checks supporting its answer. It may draft and update a decision record, but acceptance of a consequential application decision requires explicit approval from an accountable human. The AI may record that approval.

## Consequences

Developers can ask the project AI how PHPThis works and request changes without first learning a separate framework API manual. Framework Markdown remains essential, but it is structured as routed, machine-readable authority and human-auditable evidence rather than a linear course.

The quality of answers depends on current application context. Stale or invented `.ai/` facts are project defects. The AI must report missing authority instead of silently choosing policy. Humans can audit every claim through ordinary files, source, tests, diagnostics, and check output.

PHPThis remains tool-neutral: no particular model or agent product is part of the runtime or required by the framework package.

## Reconsider when

Real consumer projects show that the installed knowledge map cannot reliably route common questions, an accessibility requirement needs another representation, or model-independent discovery converges on a stronger standard. Any replacement must preserve version grounding, human auditability, and explicit human ownership of consequential decisions.
