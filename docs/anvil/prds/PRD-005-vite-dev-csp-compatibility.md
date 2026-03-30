# PRD-005: Vite Development Mode CSP Compatibility

**Created:** 2026-03-30
**Status:** Approved
**Author:** Bas de Kort
**Source:** GitHub issue #3

## Problem Statement

Mithril ships a Content Security Policy that applies uniformly to every response regardless of the environment. The policy is correctly restrictive for production, but it inadvertently blocks the Vite development server's runtime requirements: the hot module replacement WebSocket connection is rejected because the policy only permits same-origin connections, and any assets (including fonts) served by the Vite dev server are blocked because the policy only trusts same-origin sources.

The result is that the entire Vite development workflow is non-functional when the application's CSP middleware is active. Developers cannot use HMR, browser-visible errors appear on every page load, and the expected live-reload experience is absent. Because the CSP is enforced at the HTTP layer, there is no workaround available in the browser; the policy must itself be made environment-aware.

## In Scope

- The Content Security Policy policy applied in local development environments permitting connections and asset sources required by the Vite development server
- HMR WebSocket connections succeeding in the local environment
- Fonts and other static assets served by the Vite dev server loading without CSP violations in the local environment
- The policy change being confined strictly to the local development environment; production and staging are unaffected

## Out of Scope

- Any change to the production CSP directives
- Adding new CSP directives unrelated to local development tooling (e.g. new third-party script sources, analytics, CDNs)
- Changes to how the nonce system works for scripts
- Changing, loosening, or auditing any other aspect of the existing security headers
- Configuring Vite itself or its dev server settings
- Browser extension compatibility with CSP

## User Roles

| Role | Goals | Key Interactions |
|------|-------|------------------|
| Developer (local) | Run the Vite dev server and use hot module replacement without CSP violations interrupting the workflow | Starting the dev server, editing source files, observing changes in the browser in real time |

## Acceptance Criteria

1. When the application runs in the local development environment with the Vite dev server active, the Vite HMR WebSocket connection establishes successfully and no CSP violation is reported for it in the browser console.
2. When the application runs in the local development environment, fonts and other static assets served by the Vite dev server load without CSP violations.
3. When the application runs in any non-local environment (production, staging, CI), the CSP directives are identical to their current values; no new sources are permitted.
4. The mechanism for determining which environment is active uses the application's existing environment configuration; no separate or manual toggle is required.
5. Switching from the local environment to a production-equivalent environment (e.g. by changing the environment variable) causes the development-mode CSP relaxations to no longer be applied, verifiable by inspecting the `Content-Security-Policy` response header.
6. No new CSP violations are introduced in production as a result of this change.

## Constraints

- The solution must not weaken any CSP directive in production, even marginally.
- The solution must not require developers to manually disable or bypass the CSP middleware during local development.
- The environment distinction must rely on the application's own configuration system; it must not depend on the presence or absence of the Vite dev server process itself.

## Dependencies

- The application's existing environment configuration mechanism (e.g. `APP_ENV`) must reliably distinguish local development from production.
- The existing CSP middleware must support or be extended to support environment-conditional directive values.

## Success Metrics

- Zero CSP-related errors appear in the browser console during a standard local development session with the Vite dev server running.
- The `Content-Security-Policy` response header in production contains no sources beyond those currently defined.
- Developer onboarding time for getting a working local dev environment is not increased by environment-specific CSP configuration steps.

## Changelog

| Date | Author | Change |
|------|--------|--------|
| 2026-03-30 | Bas de Kort | Initial draft |
