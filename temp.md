## The questions that define the architecture

1. Tenancy: Is one Pane installation shared by multiple unrelated customers or organizations, or does every organization get its own Pane deployment?
2. Connection ownership: Does a database belong to:
    - one user;
    - an organization/workspace;
    - or a user initially, with optional sharing later?

3. Application access: Can every Burro-derived project access every database available to its user, or must Pane grant particular applications access to
   particular connections?

4. Database quota: Is the limit:
    - per user;
    - per organization;
    - or both?

   What happens when an administrator lowers a limit below the number already registered?

5. Invitations: Should Pane manage invitation records and tokens itself, or should invitations be created through WorkOS? Should invitations expire, be
   revocable, and be restricted to one email address?

6. Administrators: Are admins global Pane administrators or administrators of one organization? Can an organization have several admins?
7. Impersonation: When an admin logs in as a user, I strongly recommend:
    - no password or credential sharing;
    - an explicit impersonation session;
    - a persistent “impersonating” banner;
    - a reason and expiry;
    - full audit logging;
    - optionally prohibiting destructive operations during impersonation.

   Should impersonation allow writes, or default to read-only?

8. Connection credentials: For the first version, will users provide host, port, database, username, password, and optional TLS configuration? Do we need
   SSH tunnels, client certificates, or cloud-provider IAM later?

9. Network reachability: Where will Pane run relative to connected databases? Allowing arbitrary hosts introduces SSRF and internal-network risks. Should
   connections initially be limited to an administrator-approved host/domain allowlist?

10. Database permissions: Should Pane merely recommend read-only database accounts, or enforce separate read and write credentials? CRUD means the supplied
    account can modify customer data, so this needs to be explicit.

11. Schema descriptions: Are user-written descriptions attached to databases, schemas, tables, and columns? Can several users edit the same catalog, and do
    we need revision history?

12. Data access model: Will Pane expose generic CRUD endpoints driven by discovered metadata, or will each frontend define a limited API/data contract?
    Fully generic CRUD is faster initially but needs very careful table, column, row, and operation authorization.

## Security baseline I would make non-negotiable

- Encrypt connection secrets at the application layer, not merely through disk/database encryption.
- Keep encryption keys outside Pane’s database and support key rotation.
- Never return stored passwords to any frontend after creation.
- Use least-privilege database accounts.
- Validate hosts, ports, TLS policy, and connection timeouts.
- Prevent connection testing from becoming an SSRF or network-scanning endpoint.
- Audit credential creation, updates, connection tests, schema refreshes, impersonation, and data mutations.
- Separate connection metadata from encrypted secret material.
- Rate-limit authentication, invitations, connection tests, and schema discovery.

## Suggested first vertical slice

1. Invite-only registration.
2. Two roles: administrator and standard user.
3. Per-user database quota.
4. User-owned MySQL/MariaDB connection profiles.
5. Encrypted credentials stored in Pane.
6. Safe “test connection” endpoint.
7. Schema/table/column discovery through information_schema.
8. User-editable descriptions for connections and discovered objects.
9. Tavola UI for connection management.
10. Admin UI for invitations, quotas, and audited impersonation.
11. One generated Burro-based frontend that reads discovered metadata through Pane.

The most important answers to settle first are tenancy, connection ownership, application-to-connection authorization, invitation ownership, and whether
impersonation permits writes. Once those are clear, we can turn issue #17 into the product-vision document and split implementation into focused Pane,
Burro, and Tavola epics.
