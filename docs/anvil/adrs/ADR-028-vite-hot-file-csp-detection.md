# ADR-028: Use Vite hot file detection instead of APP_ENV for CSP relaxation

**Date:** 2026-03-30
**Status:** Accepted
**Tags:** security, csp, vite, middleware
**Phase:** PLAN-026 Phase 1

## Context

PLAN-026 originally specified using `app()->environment('local')` to conditionally relax the CSP for Vite dev server origins. During implementation, two issues emerged:

1. Vite on modern Linux systems resolves `localhost` to `[::1]` (IPv6), which is not a valid CSP source expression. Fixed by configuring Vite to bind to `127.0.0.1` and adding both `127.0.0.1:5173` and `localhost:5173` to the CSP.
2. The user requested that the relaxation only apply when Vite is actively running, not merely when `APP_ENV=local`. This prevents accidental CSP weakening if someone deploys with a misconfigured environment.

## Decision

Use `Vite::isRunningHot()` instead of `app()->environment('local')` to gate the CSP relaxation. This method checks for the existence of the `public/hot` file, which Vite creates when its dev server starts and removes when it stops. Additionally, `style-src` was added to the list of relaxed directives (not in the original plan) because Vite loads CSS via `<link>` tags pointing to the dev server.

## Deviation from Plan

PLAN-026 Phase 1 specified `APP_ENV=local` as the detection mechanism. Changed to `Vite::isRunningHot()` for tighter scoping.

## PRD Reference

PRD-005, Constraint: "The environment distinction must rely on the application's own configuration system." `Vite::isRunningHot()` is part of Laravel's Vite integration and does not require manual configuration; this satisfies the spirit of the constraint while being more precise.

## Consequences

### Positive
- CSP relaxation is impossible in production even with misconfigured `APP_ENV`
- Zero false positives: relaxation only active when Vite dev server is actually serving

### Negative
- If a developer runs `php artisan serve` without `npm run dev`, CSP remains strict (no relaxation); this is the desired behavior

### Code/Data Changes
- `app/Http/Middleware/SecurityHeaders.php`: uses `Vite::isRunningHot()` instead of `app()->environment('local')`
- `vite.config.ts`: added `server.host: '127.0.0.1'` to force IPv4 binding
- `style-src` directive now also conditionally includes Vite origins (in addition to `connect-src`, `font-src`, `img-src`)

### Migration / Operational Impact
- None. The hot file is already part of Laravel's Vite workflow.
