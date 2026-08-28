# 0002: Registered Checkouts Only

## Context

Project work must stay visible to the human maintainer. Temporary clones and hidden worktrees make it easy to lose changes, review the wrong tree, or apply Git operations to a repository that Yaup did not discover or configure.

## Decision

Yaup only launches project work for registered repository paths. Discovery records direct children of the configured `repos/` directory that have an origin remote, and `AgentCommand` rejects unregistered project paths.

## Consequences and tradeoffs

This makes the workspace predictable and reviewable. It also means one-off clones are deliberately inconvenient: the repository must be registered before Yaup treats it as a managed project.

## Alternatives considered

- Accept any local path. Rejected because it weakens human review and makes policy application depend on caller discipline.
- Clone repositories on demand into temporary directories. Rejected because changes would be less visible and easier to discard accidentally.
- Trust remote identity alone. Rejected because local path discipline is part of the review model.

## Related files or rules

- `config/yaup.yaml`
- `config/repositories.yaml`
- `policies/core.md`
- `policies/rules.yaml`, rule `workspace.registered-repos-only`
- `src/Command/AgentCommand.php`
- `src/Repository/RepositoryDiscoverer.php`
- `src/Repository/Registry.php`
