# Consolidated Summary: API Key Management Security Audit

**Project:** Mithril
**Date:** 2026-03-27
**Scope:** Delta since `c87b534` (18 source files)
**Author:** Bas de Kort

---

## Findings by Severity

### High (1)

**H1. Ability enforcement bypass on unmapped/unnamed API routes**
`app/Http/Middleware/CheckTokenAbility.php:81`

The `CheckTokenAbility` middleware uses a fail-open design: when it cannot resolve a required ability from the route name, it allows the request through. Nine routes in the token-accessible API group lack either a route name or a matching entry in the action/resource maps. A read-only token can access these endpoints (including data export, activity CRUD, and search) without restriction.

**Fix:** Change to fail-closed for token requests. Name all routes. Complete the ACTION_MAP and RESOURCE_MAP.

---

### Medium (2)

**M1. CSRF bypass triggered by any Bearer header**
`app/Http/Middleware/ValidateCsrfTokenUnlessBearerAuth.php:39`

CSRF validation is skipped when any `Authorization: Bearer` header is present, even with an invalid token value. Same-origin JavaScript (e.g., from a hypothetical XSS) could bypass CSRF while the session cookie authenticates the request.

**Fix:** Only bypass CSRF for genuinely stateless (non-session) authentication.

**M2. No per-user token creation limit**
`app/Http/Controllers/Web/SettingsController.php:403`

Users can create unlimited tokens, risking database bloat and audit difficulty.

**Fix:** Enforce a per-user cap (e.g., 25 tokens).

---

### Low (1)

**L1. Token expiration not configured**
`config/sanctum.php:50`

Tokens never expire (`expiration => null`). Leaked tokens remain valid until manually revoked.

**Fix:** Set a default expiration via `SANCTUM_TOKEN_EXPIRATION`.

---

### Info (4)

| ID | Title | Location |
|----|-------|----------|
| I1 | Token prefix not set for secret scanning | `config/sanctum.php:65` |
| I2 | Wildcard `*` ability possible via direct Sanctum API | `SettingsController.php:430` |
| I3 | Required ability name leaked in 403 response | `CheckTokenAbility.php:92` |
| I4 | Missing `dismiss` action in ACTION_MAP | `CheckTokenAbility.php:24` |

---

## Clean Areas

- Token hashing (SHA-256), single-exposure plaintext, immediate revocation
- Enum-based ability/scope validation prevents injection of arbitrary abilities
- Cross-user token access prevented (scoped to `$request->user()->tokens()`)
- BelongsToUser global scope works correctly with Sanctum token auth
- Session-only endpoints (auto-save, reorder, sync, email/jira/calendar actions) properly isolated from token auth
- Frontend uses `x-text` (textContent) exclusively; no innerHTML/XSS vectors
- CSRF tokens correctly sent in all frontend fetch requests
- Blade templates use auto-escaped `{{ }}` and `@js()` throughout
- Disabled account check works for both session and token auth
