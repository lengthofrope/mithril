# API Key Management & External API Access

**Created:** 2026-03-27
**Status:** Approved
**Author:** Bas de Kort
**PRDs:** PRD-004

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-004](prds/PRD-004-api-key-management.md) | API Key Management & External API Access | Approved |

## Problem Statement

Mithril's API endpoints are only accessible via session-based authentication, blocking programmatic access from external tools and scripts. This plan introduces Laravel Sanctum token authentication with configurable scopes and a settings UI for managing API keys; as specified in PRD-004.

## Acceptance Criteria

_Derived from PRD-004 criteria 1-18._

1. Settings page has an "API" subpage accessible from settings navigation
2. Users can create named tokens (max 100 chars) with scope selection
3. Scope tiers (read-only, read-write, full-access) expand to granular abilities
4. Granular abilities cover all API resources with read/write/delete sub-abilities
5. Token plaintext shown exactly once after creation with non-retrievable warning
6. Token list shows name, scope description, created date, last-used date
7. Individual token revocation permanently deletes the token
8. Bulk "revoke all" with confirmation
9. Bearer token auth works on all `/api/v1/` endpoints
10. Missing/invalid/revoked tokens return 401; insufficient scope returns 403
11. Token requests are scoped to the token owner's data (BelongsToUser)
12. Existing session-based (`auth:web`) requests continue to work unchanged
13. Token names are non-unique; multiple tokens with different scopes allowed
14. UI lists all available abilities grouped by resource

## Technical Design

### Approach

Install Laravel Sanctum for token issuance and Bearer auth. Define an `ApiAbility` enum listing all granular abilities. Create an `ApiScope` enum for tier presets that map to sets of abilities. The existing `auth:web` guard remains; routes accept either `auth:web` OR `auth:sanctum`. A new middleware checks Sanctum token abilities against the route's required ability.

```
┌──────────────┐     ┌────────────────┐     ┌──────────────────┐
│  Browser      │────▷│ auth:web       │────▷│                  │
│  (session)    │     │ (unchanged)    │     │  API Controller   │
└──────────────┘     └────────────────┘     │  (existing)       │
                                             │                  │
┌──────────────┐     ┌────────────────┐     │                  │
│  External     │────▷│ auth:sanctum   │────▷│                  │
│  Tool (token) │     │ + CheckAbility │     └──────────────────┘
└──────────────┘     └────────────────┘
```

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `composer.json` | Modify | Add `laravel/sanctum` dependency |
| `config/sanctum.php` | Create | Sanctum config (published by install) |
| `database/migrations/*_create_personal_access_tokens_table.php` | Create | Sanctum's token table migration |
| `app/Models/User.php` | Modify | Add `HasApiTokens` trait |
| `app/Enums/ApiAbility.php` | Create | String-backed enum listing all granular abilities |
| `app/Enums/ApiScope.php` | Create | String-backed enum for tier presets mapping to abilities |
| `app/Http/Middleware/CheckTokenAbility.php` | Create | Middleware checking Sanctum token abilities per route |
| `bootstrap/app.php` | Modify | Register Sanctum guard, add ability middleware |
| `routes/api.php` | Modify | Change guard from `auth:web` to `auth:web,sanctum`; add ability middleware |
| `app/Http/Controllers/Web/SettingsController.php` | Modify | Add `api()` method for new settings page |
| `app/Http/Controllers/Api/ApiTokenController.php` | Create | Token CRUD (create, list, revoke, revokeAll) |
| `routes/web.php` | Modify | Add settings/api route |
| `resources/views/pages/settings/api.blade.php` | Create | API settings page with Alpine.js token management |
| `resources/js/components/apiTokenManager.ts` | Create | Alpine component for token CRUD interactions |
| `resources/js/app.ts` | Modify | Register apiTokenManager component |
| `app/Helpers/MenuHelper.php` | Modify | Not needed; settings subpages are internal to settings, not sidebar items |

### Ability Mapping

Each API resource maps to abilities:

| Resource | Read | Write | Delete |
|----------|------|-------|--------|
| tasks | `tasks:read` | `tasks:write` | `tasks:delete` |
| teams | `teams:read` | `teams:write` | `teams:delete` |
| team-members | `team-members:read` | `team-members:write` | `team-members:delete` |
| notes | `notes:read` | `notes:write` | `notes:delete` |
| follow-ups | `follow-ups:read` | `follow-ups:write` | `follow-ups:delete` |
| meetings | `meetings:read` | `meetings:write` | `meetings:delete` |
| agreements | `agreements:read` | `agreements:write` | `agreements:delete` |
| activities | `activities:read` | `activities:write` | `activities:delete` |
| search | `search:read` | — | — |
| counters | `counters:read` | — | — |
| export | `export:read` | `export:write` | — |

Tier presets:
- **read-only:** all `*:read` abilities
- **read-write:** all `*:read` + all `*:write` abilities
- **full-access:** all abilities (read + write + delete)

### Route-to-Ability Mapping Strategy

The `CheckTokenAbility` middleware resolves the required ability from the route name convention:
- `api.tasks.index` / `api.tasks.show` → `tasks:read`
- `api.tasks.store` / `api.tasks.update` → `tasks:write`
- `api.tasks.destroy` → `tasks:delete`
- Routes outside the ability system (sync, auto-save, reorder, speech-service) get mapped explicitly

Session-authenticated requests (auth:web) bypass ability checks entirely; abilities only apply to Sanctum token requests.

### Edge Cases & Error Handling

- **Token shown once:** the plaintext token from `createToken()` is returned in the create response. It is never stored and cannot be retrieved again. The UI must display it prominently with a copy button and warning.
- **Revoked token:** Sanctum hard-deletes the token row. Subsequent requests with that token get 401.
- **Dual guard ordering:** `auth:web,sanctum` means Laravel tries session first, then token. Session users pass through without ability checks.
- **CSRF:** Sanctum token requests do not need CSRF tokens. The existing CSRF middleware in the API group must be bypassed for token-authenticated requests. Sanctum handles this via its `EnsureFrontendRequestsAreStateful` middleware.
- **EnsureAccountIsActive:** Already applies to API group; works for both guards since it checks the resolved user.
- **Last-used tracking:** Sanctum updates `last_used_at` on the token automatically.

## Implementation Phases

### Phase 1: Sanctum Installation & Dual-Guard Setup
- **Goal:** Install Sanctum, configure dual-guard authentication, verify zero regression on existing session auth
- **PRD criteria:** 9, 11, 12
- **Specs:**
  - [ ] Laravel Sanctum is installed via Composer and configured
  - [ ] `personal_access_tokens` migration exists and runs on both MariaDB and SQLite
  - [ ] User model has `HasApiTokens` trait
  - [ ] API routes accept both `auth:web` and `auth:sanctum` guards
  - [ ] CSRF middleware is properly configured to not block token-based requests
  - [ ] All existing tests continue to pass with the new dual-guard setup
  - [ ] A Bearer token request with a valid Sanctum token returns authenticated data
  - [ ] A request with an invalid/missing token returns 401 in ApiResponse format
  - [ ] `EnsureAccountIsActive` middleware works for both session and token users
- **Files:** `composer.json`, `config/sanctum.php`, `database/migrations/`, `app/Models/User.php`, `bootstrap/app.php`, `routes/api.php`

### Phase 2: Ability System & Scope Enforcement
- **Goal:** Define all API abilities, tier presets, and route-level enforcement middleware
- **PRD criteria:** 3, 4, 5, 10, 14
- **Specs:**
  - [ ] `ApiAbility` enum lists all granular abilities (resource:action format)
  - [ ] `ApiAbility` provides helper methods: `readAbilities()`, `writeAbilities()`, `deleteAbilities()`, `forResource(string)`, `groupedByResource()`
  - [ ] `ApiScope` enum defines read-only, read-write, full-access tiers with mappings to ApiAbility sets
  - [ ] `CheckTokenAbility` middleware resolves required ability from route name and checks token abilities
  - [ ] `CheckTokenAbility` is a no-op for session-authenticated (non-token) requests
  - [ ] Token with `tasks:read` can GET `/api/v1/tasks` but gets 403 on POST/PATCH/DELETE
  - [ ] Token with read-only scope can read all resources but gets 403 on writes
  - [ ] Token with full-access scope can perform all operations
  - [ ] 403 response uses ApiResponse format with descriptive message
- **Files:** `app/Enums/ApiAbility.php`, `app/Enums/ApiScope.php`, `app/Http/Middleware/CheckTokenAbility.php`, `bootstrap/app.php`, `routes/api.php`

### Phase 3: Token CRUD API & Settings UI
- **Goal:** Token management API endpoints and the Settings > API page with full create/list/revoke UI
- **PRD criteria:** 1, 2, 3, 5, 6, 7, 8, 10, 13, 14, 16, 17, 18
- **Specs:**
  - [ ] `POST /settings/api/tokens` creates a token with name and scope/abilities; returns plaintext token once
  - [ ] `GET /settings/api` renders the API settings page with token list and creation form
  - [ ] Token list shows name, scope description, creation date, last-used date
  - [ ] "Never used" displayed when `last_used_at` is null
  - [ ] Token creation form requires name (max 100 chars) and at least one scope selection
  - [ ] Scope UI shows tier presets and expandable per-resource abilities grouped by resource
  - [ ] Selecting a tier auto-checks the corresponding granular abilities
  - [ ] After creation, plaintext token displayed with copy-to-clipboard and non-dismissable warning
  - [ ] Individual token revocation via DELETE with confirmation dialog
  - [ ] "Revoke all" button with confirmation dialog
  - [ ] Settings subpage navigation includes "API" link alongside existing tabs
  - [ ] Alpine.js component handles all token CRUD interactions
  - [ ] Multiple tokens with same name can coexist; revocation targets specific token by ID
- **Files:** `app/Http/Controllers/Api/ApiTokenController.php`, `app/Http/Controllers/Web/SettingsController.php`, `routes/web.php`, `resources/views/pages/settings/api.blade.php`, `resources/js/components/apiTokenManager.ts`, `resources/js/app.ts`

## Parallelization

**Strategy:** Sequential

All three phases have dependencies: Phase 2 depends on Sanctum being installed (Phase 1), and Phase 3 depends on the ability enums and middleware from Phase 2. The phases are small enough for single-agent sequential execution.

## Out of Scope

- Token expiry / automatic rotation
- OAuth 2.0 flows
- Webhook delivery
- API documentation / explorer UI
- Team-scoped or admin tokens
- IP allowlisting
- Per-request audit logging
- New API endpoints beyond what exists

## Open Questions

1. ~~Should sync endpoints (Jira, Calendar, Email) be accessible via API tokens?~~ **Resolved:** Excluded; sync endpoints remain session-only.
2. ~~Should auto-save and reorder endpoints be accessible via tokens?~~ **Resolved:** Excluded; internal UI operations stay session-only.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
