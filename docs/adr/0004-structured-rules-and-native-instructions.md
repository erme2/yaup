# 0004: Structured Rules and Native Instructions

## Context

Different coding agents use different instruction formats. Projects also contain native instruction files such as `AGENTS.md`, `CLAUDE.md`, `.cursor/rules`, and Copilot instructions. Yaup needs shared enforceable policy without overwriting those native files.

## Decision

Yaup keeps enforceable policy in structured rules with stable IDs, resolves project overrides through `RuleResolver`, and passes native instruction file paths to the agent as read-and-obey context. Mandatory rules cannot be disabled by project overrides.

## Consequences and tradeoffs

Structured rules are easier to validate and override safely than prose. Native files remain useful for agent-specific conventions, but contradictions between prose instructions cannot be ranked mechanically and must be escalated.

## Alternatives considered

- Store all policy as prose. Rejected because mandatory rules and overrides need machine-readable behavior.
- Rewrite all native instruction files. Rejected because unmarked project-owned files must remain under project control.
- Ignore native instructions. Rejected because local conventions often live there and still matter.

## Related files or rules

- `config/yaup.yaml`
- `policies/core.md`
- `policies/rules.yaml`, rules `workflow.ask-on-ambiguity` and `instructions.protected`
- `src/Rules/RuleResolver.php`
- `src/Rules/ResolvedRules.php`
