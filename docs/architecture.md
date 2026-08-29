# Architecture

The PHP CLI is the policy authority. `ConfigLoader` parses YAML; repository discovery synchronizes only remote-backed projects into the registry; `InstructionsSyncCommand` maintains Yaup-managed `AGENTS.md` bridge files in registered checkouts; `RuleResolver` applies stable-ID overrides while protecting mandatory rules and discovers native instructions without writing them. `PlanVerifier` requires a committed, unchanged human approval before `AgentCommand` enables execution mode. Agent adapters translate this two-phase protocol into each CLI's permission model; their interface and mode mapping are documented in [adapters.md](adapters.md). `ValidationRunner` executes explicit project commands and rejects missing or expired exemptions.

Runtime prompts contain resolved structured rules and paths to applicable native instructions. Prose contradictions cannot be ranked safely and must be escalated to a human. CLI adapters fail if their executable is unavailable; release qualification must smoke-test supported versions because vendor flags can change.

Architecture Decision Records in [adr/](adr/) record the rationale, tradeoffs, and alternatives behind these decisions.
