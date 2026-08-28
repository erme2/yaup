# Architecture Decision Records

Yaup ADRs explain why core workflow and architecture decisions exist. They are not the enforcement layer: `policies/rules.yaml` defines enforceable policy, while ADRs record the rationale, tradeoffs, and conditions that would justify changing a decision.

## Format

Each ADR should be short and practical:

- Context
- Decision
- Consequences and tradeoffs
- Alternatives considered
- Related files or rules

Use a new numbered file for each accepted decision. Prefer stable links to code, docs, and rule IDs over restating implementation details.

## Index

- [0001: Plan Before Write](0001-plan-before-write.md)
- [0002: Registered Checkouts Only](0002-registered-checkouts-only.md)
- [0003: Human Git Boundary](0003-human-git-boundary.md)
- [0004: Structured Rules and Native Instructions](0004-structured-rules-and-native-instructions.md)
- [0005: Validation Categories](0005-validation-categories.md)
- [0006: Cross-Agent Adapters](0006-cross-agent-adapters.md)
- [0007: Release Artifact Trust](0007-release-artifact-trust.md)
