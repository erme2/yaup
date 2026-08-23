# Shared Workflows

Yaup publishes reusable GitHub Actions workflows that project repositories can call from their own `.github/workflows` files.

## Stale Branch And PR Cleanup

Use `.github/workflows/cleanup-stale-refs.yml` to close stale pull requests and delete stale branches after a merge to `main`.

Configure one repository secret in every calling project:

- `STALE_REF_MAX_AGE`: shared stale age for PRs and branches, for example `14`, `14d`, `2w`, or `2 weeks`.

Caller workflow:

```yaml
name: Cleanup stale branches and pull requests

on:
  push:
    branches: [main]

permissions:
  contents: write
  issues: write
  pull-requests: write

jobs:
  cleanup:
    uses: erme2/yaup/.github/workflows/cleanup-stale-refs.yml@main
    secrets:
      STALE_REF_MAX_AGE: ${{ secrets.STALE_REF_MAX_AGE }}
```

The shared workflow:

- closes open pull requests not updated for longer than `STALE_REF_MAX_AGE`;
- deletes unprotected non-default branches whose last commit is older than `STALE_REF_MAX_AGE`;
- skips branches with open pull requests;
- skips labels listed in `exclude-pr-labels`, defaulting to `keep,do-not-close`;
- supports manual dry-run execution from the Yaup repository.

Protected branches and the repository default branch are never deleted.
