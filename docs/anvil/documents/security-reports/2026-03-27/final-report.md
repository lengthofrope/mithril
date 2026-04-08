# Security Audit Report: API Key Management

**Project:** Mithril
**Scope:** Delta since `c87b534` (18 source files, 5 test files)
**Date:** 2026-03-27
**Author:** Bas de Kort

---

## Executive Summary

The API Key Management feature introduces Laravel Sanctum-based personal access tokens with a granular ability system. The implementation is **generally well-architected** with proper enum-based validation, user-scoped token management, and a clean dual-guard design that separates token-accessible and session-only endpoints.

However, the audit identified **one high-severity finding** related to ability enforcement bypass on multiple API routes, and several medium/info-level findings related to token lifecycle management, CSRF bypass design, and missing rate limits on token creation.

### Severity Breakdown

| Severity | Count |
|----------|-------|
| Critical | 0     |
| High     | 1     |
| Medium   | 2     |
| Low      | 1     |
| Info     | 4     |
| **Total** | **8** |

### Overall Risk Assessment

**Moderate.** The high-severity finding (ability bypass on unmapped routes) means a token with read-only scope can perform write and delete operations on certain resources. The remaining findings are hardening recommendations. No critical vulnerabilities (exposed secrets, injection, or authentication bypass) were found.

---

## Findings Summary

| # | Severity | Title | Location | OWASP |
|---|----------|-------|----------|-------|
| 1 | High | Ability enforcement bypass on unmapped/unnamed API routes | `app/Http/Middleware/CheckTokenAbility.php:81` | A01 |
| 2 | Medium | CSRF bypass triggered by any Bearer header, not just valid tokens | `app/Http/Middleware/ValidateCsrfTokenUnlessBearerAuth.php:39` | A01 |
| 3 | Medium | No per-user token creation limit | `app/Http/Controllers/Web/SettingsController.php:403` | A05 |
| 4 | Low | Token expiration not configured | `config/sanctum.php:50` | A07 |
| 5 | Info | Token prefix not configured for secret scanning | `config/sanctum.php:65` | A05 |
| 6 | Info | Wildcard ability (`*`) can be created via Sanctum API | `app/Http/Controllers/Web/SettingsController.php:430` | A01 |
| 7 | Info | Ability information leakage in 403 response | `app/Http/Middleware/CheckTokenAbility.php:92` | A04 |
| 8 | Info | Missing `dismiss` action mapping in CheckTokenAbility | `app/Http/Middleware/CheckTokenAbility.php:24` | A01 |

---

## Detailed Findings

### Finding 1: Ability enforcement bypass on unmapped/unnamed API routes

- **Severity:** High
- **OWASP Category:** A01:2021 Broken Access Control
- **Location:** `app/Http/Middleware/CheckTokenAbility.php:81`, `routes/api.php:60-74`
- **Description:**

  The `CheckTokenAbility` middleware resolves abilities from route names. When `resolveAbility()` returns `null`, the request is allowed through (line 81-82: `if ($ability === null) { return $next($request); }`). This fail-open design means any route that cannot be mapped to an ability is accessible to ALL token-authenticated requests regardless of their assigned abilities.

  The following routes in the token-accessible group (first route group in `api.php`) bypass ability checks entirely:

  | Route | Reason |
  |-------|--------|
  | `POST {type}/{id}/activities` (line 63) | No `->name()` call; route name is null |
  | `PATCH {type}/{id}/activities/{activity}` (line 64) | No `->name()` call |
  | `DELETE {type}/{id}/activities/{activity}` (line 65) | No `->name()` call |
  | `GET search` (line 71) | No `->name()` call |
  | `GET export` (line 73) | No `->name()` call |
  | `POST import` (line 74) | No `->name()` call |
  | `GET counters` (line 70) | Named `api.counters`; last segment `counters` not in ACTION_MAP |
  | `GET speech-service/health` (line 58) | Named `api.speech-service.health`; resource `speech-service` not in RESOURCE_MAP |
  | `PATCH system-notifications/.../dismiss` (line 76-77) | Named `api.system-notifications.dismiss`; action `dismiss` not in ACTION_MAP |

  Additionally, `RESOURCE_MAP` contains entries for `attachments` and `system-notifications`, but `ApiAbility` has no corresponding enum cases, so even if the route name resolved correctly, `tokenCan()` would fail against non-existent abilities.

- **Reproduction Steps:**
  1. Create a token with only `tasks:read` ability.
  2. Use that token to `POST /api/v1/tasks/1/activities` (create activity) or `GET /api/v1/export` (export all data).
  3. The request succeeds despite the token having only `tasks:read`.

- **Recommendation:**
  1. **Fail-closed:** Change the middleware to deny access when ability is `null` for token-authenticated requests, rather than allowing through.
  2. **Name all routes:** Add `->name()` to the activity, search, export, and import routes.
  3. **Add missing actions** to `ACTION_MAP`: `dismiss`.
  4. **Add missing resources** to `RESOURCE_MAP` or remove phantom entries for `attachments` and `system-notifications`.
  5. **Add missing abilities** to `ApiAbility` for `attachments` and `system-notifications` if they should be token-accessible.

---

### Finding 2: CSRF bypass triggered by any Bearer header, not just valid tokens

- **Severity:** Medium
- **OWASP Category:** A01:2021 Broken Access Control
- **Location:** `app/Http/Middleware/ValidateCsrfTokenUnlessBearerAuth.php:39`
- **Description:**

  The middleware bypasses CSRF validation when `$request->bearerToken() !== null`. This check only verifies the presence of an `Authorization: Bearer` header, not whether the token is valid. A request with `Authorization: Bearer garbage` will bypass CSRF validation but still proceed to authentication middleware.

  In practice, browsers enforce CORS preflight for requests with custom `Authorization` headers, which limits cross-origin exploitation. However, same-origin JavaScript (e.g., from an XSS vulnerability elsewhere in the application) could exploit this to send state-changing requests without CSRF tokens.

- **Reproduction Steps:**
  1. Assume an XSS vulnerability exists somewhere in the application (even reflected).
  2. Malicious JS on the same origin sends: `fetch('/api/v1/tasks', { method: 'POST', headers: { 'Authorization': 'Bearer invalid', 'Content-Type': 'application/json' }, body: '{"title":"XSS task"}', credentials: 'include' })`.
  3. CSRF validation is skipped. The session cookie is sent. The `auth:web,sanctum` guard authenticates via the session cookie (Sanctum falls back to web guard for stateful requests).
  4. The request succeeds, authenticated via session, with CSRF bypass.

- **Recommendation:**

  Consider validating that the bearer token is non-empty and that the request is NOT also session-authenticated. A safer pattern:

  ```php
  if ($request->bearerToken() !== null && !$request->hasSession()) {
      return $next($request);
  }
  ```

  Alternatively, verify authentication source after the auth middleware runs, not before.

---

### Finding 3: No per-user token creation limit

- **Severity:** Medium
- **OWASP Category:** A05:2021 Security Misconfiguration
- **Location:** `app/Http/Controllers/Web/SettingsController.php:403-444`
- **Description:**

  The `storeToken()` method has no limit on how many tokens a user can create. An attacker with valid credentials (or an automated script) could create an unbounded number of tokens, leading to:
  - Database bloat in the `personal_access_tokens` table.
  - Difficulty in auditing active tokens.
  - Potential denial of service through excessive token creation.

- **Reproduction Steps:**
  1. Authenticate as any user.
  2. Send `POST /settings/api/tokens` in a loop with valid payloads.
  3. Observe that thousands of tokens can be created without restriction.

- **Recommendation:**
  Add a per-user token limit (e.g., 25 active tokens). Check `$request->user()->tokens()->count()` before creating a new token and return 422 if the limit is exceeded.

---

### Finding 4: Token expiration not configured

- **Severity:** Low
- **OWASP Category:** A07:2021 Identification and Authentication Failures
- **Location:** `config/sanctum.php:50`
- **Description:**

  `'expiration' => null` means tokens never expire. If a token is leaked, it remains valid indefinitely until manually revoked. The migration includes an `expires_at` column (line 21), but no expiration is set on token creation.

- **Recommendation:**
  Set a default expiration (e.g., 90 days) via `SANCTUM_TOKEN_EXPIRATION` env variable or allow users to choose expiration during token creation. Consider periodic cleanup of expired tokens.

---

### Finding 5: Token prefix not configured for secret scanning

- **Severity:** Info
- **OWASP Category:** A05:2021 Security Misconfiguration
- **Location:** `config/sanctum.php:65`
- **Description:**

  `'token_prefix' => env('SANCTUM_TOKEN_PREFIX', '')` defaults to an empty string. Without a recognizable prefix, GitHub/GitLab secret scanning cannot detect accidentally committed Mithril API tokens.

- **Recommendation:**
  Set a project-specific prefix, e.g., `mithril_` or `mtl_`, via the `SANCTUM_TOKEN_PREFIX` environment variable. This enables automatic detection by secret scanning services.

---

### Finding 6: Wildcard ability cannot be created via UI but is possible via Sanctum

- **Severity:** Info
- **OWASP Category:** A01:2021 Broken Access Control
- **Location:** `app/Http/Controllers/Web/SettingsController.php:430`
- **Description:**

  The `storeToken()` endpoint validates abilities against the `ApiAbility` enum, which correctly prevents creation of tokens with a `*` wildcard ability through the web interface. However, if any future code path (artisan command, seeder, migration) uses `$user->createToken('name', ['*'])`, that token would bypass all ability checks since Sanctum's `tokenCan('*')` returns true for any ability.

  The test at `CheckTokenAbilityTest.php:285` explicitly creates a `['*']` token, confirming this pattern works. This is not exploitable via the current UI but is worth documenting as a design consideration.

- **Recommendation:**
  Document that wildcard tokens should never be created in production. Consider overriding Sanctum's `PersonalAccessToken` model to reject wildcard abilities in the `abilities` mutator.

---

### Finding 7: Ability information leakage in 403 response

- **Severity:** Info
- **OWASP Category:** A04:2021 Insecure Design
- **Location:** `app/Http/Middleware/CheckTokenAbility.php:92`
- **Description:**

  The 403 response includes the exact required ability string: `"This token does not have the required ability: tasks:write"`. This reveals the application's internal permission model to API consumers. While not directly exploitable, it aids reconnaissance.

- **Recommendation:**
  Consider a generic message like `"Insufficient permissions."` for production, while keeping the detailed message available in debug/development mode.

---

### Finding 8: Missing `dismiss` action in CheckTokenAbility ACTION_MAP

- **Severity:** Info
- **OWASP Category:** A01:2021 Broken Access Control
- **Location:** `app/Http/Middleware/CheckTokenAbility.php:24-42`
- **Description:**

  The `system-notifications.dismiss` route uses the action suffix `dismiss`, which is not mapped in `ACTION_MAP`. This causes the ability to resolve to `null`, bypassing the check (same root cause as Finding 1). The `dismiss` action should logically map to `write`.

- **Recommendation:**
  Add `'dismiss' => 'write'` to `ACTION_MAP`.

---

## Clean Checks

The following areas were verified as secure:

### Authentication and Token Lifecycle
- **Token hashing:** Sanctum v4.3.1 stores tokens as SHA-256 hashes in the `token` column (64 chars, consistent with migration). Plaintext token is only returned in the `storeToken()` JSON response and never stored.
- **Token revocation:** Immediate via database deletion. The `destroyToken()` method scopes deletion to `$request->user()->tokens()`, preventing cross-user token revocation (confirmed by test at `SettingsApiTokenTest.php:210-219`).
- **Revoke-all isolation:** `destroyAllTokens()` only deletes tokens belonging to the authenticated user (confirmed by test at `SettingsApiTokenTest.php:250-259`).
- **Disabled account handling:** `EnsureAccountIsActive` correctly handles both session and token authentication, returning 403 for disabled users with proper session invalidation for web requests and clean JSON for token requests.

### Input Validation and Injection Prevention
- **Token name:** Validated as `required|string|max:100`. Rendered in Blade template using `x-text` (Alpine.js `textContent` binding), which is XSS-safe.
- **Ability values:** Strictly validated against `ApiAbility` enum values via `Rule::in()`. No user-supplied strings can become abilities.
- **Scope values:** Validated against `ApiScope` enum via `Rule::in()`. Server-side scope expansion uses the enum's `abilityValues()` method, not user input.
- **No SQL injection vectors:** All queries use Eloquent ORM with parameterized queries. Token deletion uses `->where('id', $tokenId)->delete()`.

### CSRF Protection
- **Session-authenticated API requests:** CSRF validation is correctly enforced for session-based API calls. The `ValidateCsrfTokenUnlessBearerAuth` middleware only bypasses CSRF when a Bearer header is present.
- **Token management endpoints:** All token CRUD endpoints (`storeToken`, `destroyToken`, `destroyAllTokens`) are web routes under the `auth` middleware group, which includes standard CSRF protection.
- **Frontend CSRF handling:** The TypeScript component correctly reads the CSRF meta tag and includes `X-CSRF-TOKEN` header in all fetch requests.

### Access Control
- **BelongsToUser compatibility:** The global scope uses `auth()->id()`, which resolves correctly for both session and Sanctum token authentication via the dual-guard `auth:web,sanctum`.
- **Session-only endpoints:** Sensitive endpoints (`auto-save`, `reorder`, `sync`, `emails`, `jira-issues`, `calendar-events`) are correctly placed in the second route group with `auth:web` only, preventing token access.
- **Route-to-guard separation:** Clean architectural split between token-accessible (first group) and session-only (second group) in `routes/api.php`.

### Frontend Security
- **No innerHTML usage:** The Alpine.js `api-token-manager.ts` uses only `x-text` bindings (textContent), which are inherently XSS-safe.
- **No eval/Function:** No dynamic code execution in the TypeScript component.
- **Clipboard API:** `copyToken()` uses the standard `navigator.clipboard.writeText()` API with no XSS vectors.
- **Error display:** Error messages from the server are displayed via `x-text`, preventing HTML injection.
- **Same-origin credentials:** Fetch requests use `credentials: 'same-origin'`, preventing cookie leakage to cross-origin requests.

### Blade Template Security
- **Output encoding:** All Blade output uses `{{ }}` (auto-escaped) or `@js()` (JSON-encoded for Alpine.js data). No `{!! !!}` unescaped output.
- **ARIA/Accessibility:** Copy button has `aria-label`, form inputs have associated labels, modal supports `Escape` key and click-outside dismissal.

### Cryptographic Practices
- **Token storage:** SHA-256 hash in database (Sanctum default). Plaintext returned once at creation.
- **User model:** Sensitive tokens (`microsoft_access_token`, `jira_access_token`, `speech_service_token`) remain encrypted via cast.

### Middleware Ordering
- **Bootstrap configuration:** Middleware order is correct: session/cookie handling first, then CSRF, then authentication, then `EnsureAccountIsActive`, then `CheckTokenAbility`.

---

## Recommendations

### Priority 1 (Address Before Production)

1. **Fix ability enforcement bypass (Finding 1):** Change `CheckTokenAbility` to fail-closed when ability resolution returns `null` for token-authenticated requests. Name all unnamed routes. Add missing ACTION_MAP entries.

### Priority 2 (Address Within Sprint)

2. **Tighten CSRF bypass (Finding 2):** Ensure CSRF is only bypassed for genuinely stateless token requests, not for session-authenticated requests that happen to include a Bearer header.
3. **Add token creation limit (Finding 3):** Cap per-user tokens to prevent abuse.

### Priority 3 (Next Iteration)

4. **Configure token expiration (Finding 4):** Set a reasonable default TTL.
5. **Set token prefix (Finding 5):** Enable secret scanning integration.
6. **Reduce 403 verbosity (Finding 7):** Use generic error messages in production.
