# 0007: Release Artifact Trust

## Context

Private project workflows need a dependable way to consume Yaup policy and automation without giving broad repository access to every caller. Repository renames should not break private consumers unnecessarily.

## Decision

Yaup release artifacts are downloaded through an explicitly configured GitHub App identity. Repository identity is configured in `config/yaup.yaml` so private workflows can pin a known distribution repository instead of inferring it from local paths.

## Consequences and tradeoffs

This creates a narrower trust path for private artifacts and avoids tying consumers to incidental checkout names. It adds release and credential setup work, and supported agent versions still need explicit smoke testing before release qualification.

## Alternatives considered

- Rely on personal access tokens. Rejected because they are usually broader than a purpose-specific app credential.
- Infer distribution identity from the current Git remote. Rejected because local remotes and repository names can change.
- Treat CI tests as complete release qualification. Rejected because external agent CLI behavior can change outside Yaup's PHP test suite.

## Related files or rules

- `README.md`
- `config/yaup.yaml`
- `docs/architecture.md`
- `policies/rules.yaml`, rules `security.secrets` and `security.external-disclosure`
