# PLAN-026: Vite Development Mode CSP Compatibility

**Created:** 2026-03-30
**Status:** Approved
**Author:** Bas de Kort
**PRDs:** PRD-005

## PRD References

| PRD | Title | Status |
|-----|-------|--------|
| [PRD-005](../prds/PRD-005-vite-dev-csp-compatibility.md) | Vite Development Mode CSP Compatibility | Approved |

## Problem Statement

The `SecurityHeaders` middleware applies the same restrictive CSP to all environments. In local development, this blocks Vite's HMR WebSocket connection (`connect-src 'self'`) and assets served by the Vite dev server (`font-src 'self'`), making the entire Vite dev workflow non-functional.

## Acceptance Criteria

1. Vite HMR WebSocket connection succeeds in local dev; no CSP violation in browser console.
2. Fonts and static assets served by the Vite dev server load without CSP violations.
3. Production/staging CSP directives remain identical to current values.
4. Environment detection uses `APP_ENV`; no manual toggle required.
5. Switching `APP_ENV` away from `local` removes the dev-mode CSP relaxations.
6. No new CSP violations in production.

## Technical Design

### Approach

Modify `SecurityHeaders::handle()` to conditionally add Vite dev server origins to `connect-src`, `font-src`, and `img-src` when `app()->environment('local')`. This follows the exact same pattern already used for the `custom_url_enabled` conditional on lines 36-38.

The Vite dev server runs on `http://localhost:5173` by default and uses `ws://localhost:5173` for HMR. Both origins need to be allowed.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `app/Http/Middleware/SecurityHeaders.php` | Modify | Add environment-conditional Vite dev server origins to `connect-src`, `font-src`, `img-src` |
| `tests/Feature/Http/Middleware/SecurityHeadersTest.php` | Create | Test CSP directives per environment |

### Edge Cases & Error Handling

- Custom speech service URL (`custom_url_enabled`) must still be added alongside Vite origins when both conditions are true.
- If `APP_ENV` is not set, Laravel defaults to `production`; the dev relaxations should not apply.

## Implementation Phases

### Phase 1: Environment-Aware CSP
- **Goal:** CSP permits Vite dev server in local environment only
- **PRD criteria:** 1, 2, 3, 4, 5, 6
- **Specs:**
  - [ ] When `APP_ENV=local`, `connect-src` includes `http://localhost:5173 ws://localhost:5173`
  - [ ] When `APP_ENV=local`, `font-src` includes `http://localhost:5173`
  - [ ] When `APP_ENV=local`, `img-src` includes `http://localhost:5173`
  - [ ] When `APP_ENV=production`, CSP directives are identical to the current hardcoded values
  - [ ] When `APP_ENV` is unset, dev-mode origins are not present in CSP
  - [ ] When both `APP_ENV=local` and `custom_url_enabled=true`, both sets of additional origins are present in `connect-src`
  - [ ] The nonce mechanism is unaffected in all environments
- **Files:** `app/Http/Middleware/SecurityHeaders.php`, `tests/Feature/Http/Middleware/SecurityHeadersTest.php`

## Parallelization

**Strategy:** Sequential — single phase, single file change.

## Out of Scope

- Changing production CSP directives
- Vite server configuration changes
- CSP report-uri or report-to directives
- Nonce system changes

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
