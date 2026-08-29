# Agent Adapters

Yaup runs supported coding CLIs through small adapter classes. The shared `agent`
command resolves Yaup policy once, checks that the target is a registered
checkout, verifies plan approval for execution mode, and then asks the selected
adapter to build the native command line for that CLI.

## Contract

Every adapter implements `Yaup\Agent\AgentAdapter`:

```php
public function name(): string;

/** @return list<string> */
public function planCommand(string $project, string $prompt): array;

/** @return list<string> */
public function executeCommand(string $project, string $prompt): array;
```

`name()` is the stable CLI selector used by `bin/yaup agent <name> ...` and by
`AdapterRegistry`. It should be short, lowercase, and vendor-neutral where
possible.

`planCommand()` returns the exact argv vector used for mechanically constrained
planning. The command must run in or target the registered checkout passed as
`$project`, receive the full prompt passed by `AgentCommand`, and use the
strongest available native read-only or planning mode.

`executeCommand()` returns the exact argv vector used after Yaup has verified a
committed, unchanged, approved plan. The command must run in or target the same
registered checkout and use the vendor's normal write-capable mode without
weakening Yaup's policy prompt.

Return argv arrays rather than shell strings. `AgentCommand` passes them to
Symfony Process directly so adapter arguments are not re-parsed by a shell.

## Mode Enforcement

`AgentCommand` owns the shared gate:

- Reject unregistered project paths before resolving the adapter.
- Resolve Yaup rules and native instruction file paths once.
- Append `Do not modify files or external state.` in planning mode.
- Require `--plan` in execution mode.
- Verify the approved plan with `PlanVerifier` before calling
  `executeCommand()`.

Adapters own only the vendor-specific flags:

| Adapter | Executable | Planning mode | Execution mode |
| --- | --- | --- | --- |
| `codex` | `codex` | `--sandbox read-only --ask-for-approval never` | `--sandbox workspace-write --ask-for-approval on-request` |
| `claude` | `claude` | `--permission-mode plan` | `--permission-mode default` |
| `cursor` | `cursor-agent` | `--mode=plan` | `--mode=agent` |
| `copilot` | `copilot` | `--available-tools=read,search` plus a plan-mode prompt prefix | default prompt mode |
| `gemini` | `gemini` | `--approval-mode=plan` | `--approval-mode=default` |

When a CLI has a native working-directory flag, the adapter should use it.
Otherwise `AgentCommand` still starts the process with `$project` as the working
directory.

## Fail-Closed Executable Lookup

Concrete adapters extend `AbstractAdapter` and pass the executable name to its
constructor. The shared `executable()` helper uses Symfony's
`ExecutableFinder`. If the binary is unavailable, it throws a `RuntimeException`
with `Agent CLI is not installed: <executable>` before any vendor command is
started.

This is intentionally fail-closed: Yaup must not silently skip the selected
agent, fall back to a different agent, or run an unconstrained shell command when
the requested executable is missing.

Current automated coverage verifies that every supported adapter is registered.
Dedicated per-adapter regression tests for missing executables are tracked
separately by issue #17; until those land, changes to executable names or lookup
behavior should be reviewed manually against this contract.

## Adding An Adapter

1. Add a concrete class under `src/Agent/` that extends `AbstractAdapter`.
2. Implement `name()`, `planCommand()`, and `executeCommand()`.
3. Use the vendor's strongest available planning/read-only flags and explicit
   execution flags. Do not rely on vendor defaults when a safer explicit mode is
   available.
4. Register the adapter in `AdapterRegistry`.
5. Update `AdapterRegistryTest` so the supported adapter list remains explicit.
6. Update this document and ADR 0006 when the new adapter changes the supported
   CLI set or introduces a materially different permission model.
7. Run `composer check`.

If the vendor CLI cannot provide a meaningful planning or restricted mode,
document the gap and do not register the adapter until the safety boundary is
resolved.
