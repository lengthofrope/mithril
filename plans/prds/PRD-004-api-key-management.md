# PRD-004: API Key Management & External API Access

**Created:** 2026-03-27
**Status:** Approved
**Author:** Bas de Kort
**Source:** direct input

## Problem Statement

Mithril's existing API endpoints are exclusively accessible via session-based authentication, which is designed for browser interactions. External tools, scripts, automation workflows, and integrations cannot authenticate without a browser session. Users who want to access their Mithril data programmatically — for example to query tasks in a terminal script, pull team stats into a spreadsheet, or connect a third-party automation service — have no supported path to do so.

This feature introduces personal API tokens with configurable scopes so that authenticated users can issue, manage, and revoke credentials that grant controlled programmatic access to their own data.

## In Scope

- Generating named personal API tokens for the authenticated user
- Assigning a scope tier (preset) to each token: read-only, read-write, or full-access
- Assigning granular per-resource ability overrides (e.g. `tasks:read`, `tasks:write`, `meetings:read`) in addition to or instead of a tier
- Listing all active tokens belonging to the user, with name, creation date, last-used date, and assigned scope
- Revoking (permanently deleting) any token owned by the user, individually or all at once
- A dedicated "API" settings subpage within the existing Settings section for all token management interactions
- Token-authenticated requests to all existing `/api/v1/` CRUD and utility endpoints using a Bearer token in the `Authorization` header
- All token-authenticated requests remain scoped to the authenticated user's data (the same `BelongsToUser` global scope that applies to session requests)
- Display of the full token value exactly once, immediately after creation, with a clear disclosure that it cannot be retrieved again
- Scope documentation within the settings UI listing all available abilities by resource

## Out of Scope

- OAuth 2.0 flows, authorization codes, or client credentials grants
- Machine-to-machine tokens not tied to a user account
- Token expiry dates or automatic rotation (tokens are permanent until revoked)
- Rate limiting beyond what the existing `throttle:api` middleware already enforces
- Webhook delivery or push-based notifications
- API versioning changes or new endpoints beyond those already existing
- Public developer documentation or API explorer UI
- Team-scoped tokens that cross user boundaries
- IP allowlisting or geographic restrictions on token use
- Audit logging of individual API requests made with a token

## User Roles

| Role | Goals | Key Interactions |
|------|-------|------------------|
| Authenticated User (token owner) | Create tokens so external tools can access personal Mithril data; revoke tokens when no longer needed or if compromised | API settings page: create token, view token list, copy token at creation time, revoke token |
| External Tool / Script | Query or modify the user's Mithril resources without a browser session | HTTP requests to `/api/v1/` with `Authorization: Bearer {token}` header |

## Acceptance Criteria

1. A user can navigate to a dedicated "API" subpage within Settings from the main settings navigation.
2. From the API settings page, a user can create a new token by supplying a human-readable name (required, max 100 characters).
3. During token creation, the user must select either a scope tier (read-only, read-write, or full-access) or one or more granular abilities from a defined list; at least one scope selection is required before the token can be saved.
4. The scope tier definitions are: read-only grants all `*:read` abilities; read-write grants all `*:read` and `*:write` abilities; full-access grants all available abilities including delete and admin-level operations.
5. Granular abilities cover every resource exposed via the existing API, with at minimum `read` and `write` sub-abilities per resource (e.g. `tasks:read`, `tasks:write`, `meetings:read`, `meetings:write`); resources that support deletion expose a `delete` sub-ability.
6. Immediately after a token is created, its full plaintext value is displayed exactly once in the UI with a clearly visible, non-dismissable warning that the value cannot be retrieved again after this point.
7. The token list on the API settings page shows each token's name, assigned scope description, creation date, and last-used date (or "Never used" if unused); the plaintext value is never shown in the list.
8. A user can revoke any individual token from the list; upon revocation the token is permanently deleted and all subsequent requests using it are rejected.
9. A user can revoke all tokens at once via a single action that requires confirmation before execution.
10. A token with a given name can be revoked even if another token has the same name; revocation targets the specific token, not all tokens with that name.
11. An HTTP request to any `/api/v1/` endpoint with a valid Bearer token in the `Authorization` header is authenticated and returns data scoped to the token owner's records.
12. A request with a valid token that lacks the required ability for the endpoint receives a `403 Forbidden` response with the standard `ApiResponse` error format.
13. A request with an invalid, revoked, or missing token receives a `401 Unauthorized` response with the standard `ApiResponse` error format.
14. A token grants access only to the data belonging to the user who created it; it cannot access or modify another user's data.
15. All existing session-authenticated (web guard) requests to `/api/v1/` continue to work without modification after this feature is introduced.
16. The API settings page lists all available granular abilities grouped by resource, so users understand what each ability controls before assigning it to a token.
17. Token names are not unique per user; two tokens may share a name without error.
18. A user can hold multiple active tokens simultaneously with different scopes.

## Constraints

- Token authentication must not break or alter existing session-based (`auth:web`) API authentication.
- All token-authenticated requests must enforce the `BelongsToUser` global scope; a token cannot be used to query resources that do not belong to its owner.
- The full token plaintext must never be stored or retrievable after the initial creation response; only a secure hash is persisted.
- The API settings subpage must conform to the Rivendell UI theme and TailAdmin design language used throughout the application.
- Scope assignment at the time of token creation is final; if a user needs different abilities, they create a new token (no edit-in-place for scopes).
- Token names may not contain sensitive data; no validation is required beyond length, but the UI should not encourage it.

## Dependencies

| Dependency | Impact | Status |
|------------|--------|--------|
| Laravel Sanctum | Provides the token issuance, hashing, storage, and Bearer token authentication mechanism; must be installed | Not installed |
| Existing `/api/v1/` routes | All token-authenticated requests target these routes; the routes must remain compatible with both session and token guards | Exists; needs dual-guard support |
| `ApiResponse` trait | Token auth errors (401, 403) must use the same standardized JSON format as all other API responses | Exists |
| Settings section navigation | The "API" subpage must appear in the existing settings navigation defined in `MenuHelper` | Exists |
| `EnsureAccountIsActive` middleware | Must apply to token-authenticated routes the same way it applies to session routes | Exists |

## Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Token creation succeeds end-to-end | 100% of creation attempts with valid input result in a usable token | Acceptance tests covering the full creation flow |
| Existing session-based API requests unaffected | Zero regression failures in the existing test suite after the feature is introduced | All pre-existing Pest tests continue to pass |
| Scope enforcement accuracy | A token with only `tasks:read` ability is accepted for task reads and rejected for task writes, and for all other resources | Dedicated ability-enforcement test cases for every resource and ability combination |
| Revoked token access | A revoked token returns 401 on the next request immediately after revocation | Acceptance test: create token, make successful request, revoke token, confirm 401 |

## Changelog

| Date | Change | Reason |
|------|--------|--------|
| 2026-03-27 | Initial draft | — |
