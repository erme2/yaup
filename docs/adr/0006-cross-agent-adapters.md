# 0006: Cross-Agent Adapters

## Context

Yaup supports multiple coding CLIs, but each exposes different flags for planning, execution, approvals, and permissions. The policy should be shared even when the vendor interfaces differ.

## Decision

Yaup uses a small adapter interface per agent. `AgentCommand` resolves the shared Yaup context once, then each adapter translates planning and execution into that CLI's permission model.

## Consequences and tradeoffs

Adapters keep vendor-specific flags isolated and make unsupported or missing executables fail closed. They also require ongoing maintenance because CLI flags and permission behavior can change.

## Alternatives considered

- Shell out through one generic command template. Rejected because each CLI uses different arguments and safety modes.
- Support only one agent. Rejected because Yaup's purpose is consistent policy across agent tools.
- Assume compatible vendor defaults. Rejected because default permission models are not stable enough for Yaup's workflow gate.

## Related files or rules

- `docs/architecture.md`
- `docs/adapters.md`
- `src/Agent/AgentAdapter.php`
- `src/Agent/AdapterRegistry.php`
- `src/Agent/CodexAdapter.php`
- `src/Agent/ClaudeAdapter.php`
- `src/Agent/CopilotAdapter.php`
- `src/Agent/CursorAdapter.php`
- `src/Agent/GeminiAdapter.php`
- `src/Command/AgentCommand.php`
