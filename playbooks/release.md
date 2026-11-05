# Release

Verify the exact version, changelog, artifacts, checksums, dependency audit, full validation, and rollback instructions. Publishing, tagging, pushing, and deployment require explicit instructions.

## Embedded version

Pass the release tag to the phar build so `yaup --version` reports it:

```sh
php -d phar.readonly=0 tools/build-phar.php v0.1.0
# or
YAUP_VERSION=v0.1.0 php -d phar.readonly=0 tools/build-phar.php
```

The build accepts the tag with or without the leading `v` and embeds it without the `v`, so `php dist/yaup.phar --version` prints `yaup 0.1.0`. A release workflow passes the pushed tag name unchanged, for example `YAUP_VERSION: ${{ github.ref_name }}`. A value that is not a version such as `v0.1.0` or `v1.0.0-rc.1` fails the build. Building without a version still succeeds and reports the development version (`0.1.0-dev`), as does `bin/yaup --version` from a checkout.
