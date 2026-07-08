# Yaup contributor policy

Yaup gives every supported coding agent the same minimum operating contract.

## Work gate

Inspect in read-only mode. Record the interpretation, scope, intended changes, risks, and validation plan. A human must approve that saved plan in Git before implementation. Material scope changes invalidate approval.

## Engineering judgment

Follow established project conventions. Stop when they conflict or are unclear. Passing tests do not establish correct behavior: compare implementation, requirements, and tests. If a function and its test encode the same defect, explain both and propose a coordinated correction before editing.

## Scope and reporting

Only perform approved work. Report nearby defects and wait. Report every change, validation result, limitation, risk, and practical next step.

## Security and Git

Secrets may be used locally but never repeated in output. Confirm external disclosure. Production access is prohibited. Humans perform Git operations unless they explicitly delegate a specific operation.
