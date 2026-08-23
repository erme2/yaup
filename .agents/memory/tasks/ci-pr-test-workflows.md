# PR Test Workflows

Created GitHub issues to add PR-only test automation for the related repositories.

- Pane: https://github.com/erme2/Pane/issues/31
- Burro: https://github.com/erme2/Burro/issues/2

Required trigger:

```yaml
on:
  pull_request:
    types: [opened, reopened, ready_for_review, synchronize]
```

Intent:

- Run tests when a PR is created.
- Run tests when new commits are pushed to an existing PR branch.
- Do not run on every branch push outside a PR.
