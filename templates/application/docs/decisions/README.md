# Application architecture decisions

Decision records preserve project constraints that code and tests do not fully explain. Keep an index of accepted records in this file.

Each record must contain:

```markdown
# ADR NNN: Decision title

Status: proposed | accepted | superseded

## Context

What concrete problem or constraint requires a decision?

## Decision

What one pattern will the application use?

## Consequences

What becomes easier, harder, or explicitly unsupported?

## Reconsider when

What observable condition justifies reopening the decision?
```

An application decision may add stricter local constraints. It cannot waive the installed PHPThis consumer contract, suppress a `PHT` diagnostic, or permit the complete project check to fail.

AI may investigate options and draft a record with `Status: proposed`. A consequential application decision may use `Status: accepted` only after explicit approval from an accountable human; AI may record that approval and update the file.
