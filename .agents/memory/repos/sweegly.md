# Sweegly

Repository: https://github.com/erme2/sweegly (private). Expo app in `app/`, web build is a static SPA.

Domains / environments (decided 2026-11-06, also in `docs/decisions.md`):

- `sweegly.co.uk`: marketing site (WordPress or similar), not deployed from this repo
- `dev.sweegly.co.uk`: GitHub Environment `dev`
- `stage.sweegly.co.uk`: GitHub Environment `stage`
- `app.sweegly.co.uk`: GitHub Environment `prod`

GitHub Environments config (names only, no values):

- Secret: `HOSTINGER_SSH_PRIVATE_KEY`
- Variables: `HOSTINGER_HOST`, `HOSTINGER_PORT` (65002), `HOSTINGER_USERNAME`, `HOSTINGER_TARGET_DIR`, `APP_ENV`, `SITE_URL`
- Host/port/username are variables (not secrets), so the workflow reads them as `vars.*`.
- Subdomain folders live under `domains/sweegly.co.uk/public_html/<sub>`.

Issues:

- #1 release/deploy GitHub Action (pattern from erme2.com `deploy.yml`, rsync over SSH): PR https://github.com/erme2/sweegly/pull/3
- #2 show app version + build SHA in the UI: PR https://github.com/erme2/sweegly/pull/5 (Version row green/red via deployed version.json, compares commit SHA)
- #4 follow-up: native (iOS/Android) version check should compare store release version, not commit SHA
- PR #7 (merged 2026-11-07): privacy/analytics rules in `docs/privacy.md` and `docs/legal.md`. Open pre-launch items it created: PostHog SDK audit against the PECR statistical exemption, UK GDPR legitimate interests assessment, Children's Code scope assessment, identifiability assessment per new shop aggregate (all recorded in `docs/decisions.md`). Related #6 (Terms/privacy policy), #8 (PostHog), #9 (deletion).

Hosting state (2026-11-06):

- `sweegly.co.uk/public_html/.htaccess` has a temporary 302 redirect from `(www.)sweegly.co.uk` to `https://dev.sweegly.co.uk/`, limited by a host condition so subdomain folders are unaffected. Remove it when the communication site goes live.
- The main domain had no SSL certificate at that time (HTTPS handshake failed); `dev.sweegly.co.uk` had one.

Pane install for Sweegly dev (`sweegly-pane-dev` in erme2/deploy, `PANE_ENV` values as of 2026-11-07):

- `LATTE_REDIRECT_URIS=https://dev.sweegly.co.uk/auth/callback,https://dev.sweegly.co.uk/dashboard`: Pane only accepts login return URLs that match this list exactly.
- `WORKOS_REDIRECT_URI=https://dev.sweegly.co.uk/auth/callback`: must also be listed in the WorkOS application's Redirects tab.
- `WORKOS_RETURN_TO=https://dev.sweegly.co.uk/`: WorkOS logout return URL (if unset, Pane uses the `FRONTEND_URL` root).
- If Sweegly's auth callback, post-login or logout routes change, update the `PANE_ENV` secret (and WorkOS Redirects) and redeploy Pane. Stage/prod need their own values on `stage.` / `app.sweegly.co.uk`.
