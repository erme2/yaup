# 0001: Plan Before Write

## Context

Yaup exists to make AI-assisted project work reviewable before it changes a repository. Without a hard planning gate, agents can jump from interpretation to edits too quickly, especially when requirements are ambiguous or project conventions conflict.

## Decision

Agents start in read-only planning mode. Implementation mode requires a saved plan with explicit human approval, and `PlanVerifier` checks that approval is committed and unchanged before `AgentCommand` enables writes.

## Consequences and tradeoffs

This slows down small changes, but it creates an audit point before repository state changes. It also makes scope, risks, and validation expectations visible to the human reviewer before execution. Material scope changes need new approval instead of silently expanding the original plan.

Materiality is currently assessed by the human reviewer, not by mechanical
diffing between the approved plan and later implementation. `PlanVerifier`
checks that the approved plan file itself is committed and unchanged; it does
not infer whether implementation drift stays inside the approved scope.

A scope change is material when it changes what a reasonable approver thought
they authorized, such as adding affected features, files, repositories, data
migrations, external services, permissions, security behavior, production
behavior, or validation obligations. Pure wording fixes, spelling corrections,
or clarifications of already-approved work are not material when they do not
change intended work, risk, or validation.

## Alternatives considered

- Trust the agent's interactive confirmation flow only. Rejected because confirmations are not durable project records.
- Allow writes first and review the diff afterward. Rejected because it does not prevent broad or misinterpreted edits.
- Use planning as advice but not enforcement. Rejected because Yaup's value depends on the gate being mechanical.

## Related files or rules

- `policies/core.md`
- `policies/rules.yaml`, rule `workflow.plan-before-write`
- `src/Command/AgentCommand.php`
- `src/Plan/PlanVerifier.php`
