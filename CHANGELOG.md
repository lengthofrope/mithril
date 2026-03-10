# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.7] - Unreleased

### Added

- **About page** — Accessible from the user dropdown menu, shows app info, current version, and full changelog with collapsible release sections

### Fixed

- **Dashboard follow-ups widget** — Follow-ups due today were not shown; only overdue items appeared. Now includes both overdue and today's follow-ups
- **Dashboard bilas widget** — Team member name was not displayed (showed "Bila #N" instead) due to incorrect relationship reference
- **Dashboard bilas widget** — Showed "00:00" instead of the scheduled date, since `scheduled_date` is a date-only field

## [1.2.6] - 2026-03-10

### Fixed

- **Deploy script** — Source `.bashrc` and `.nvm/nvm.sh` before running commands so nvm-managed `node`/`npm` are available in the FastCGI environment

## [1.2.5] - 2026-03-10

### Fixed

- **Deploy script** — Install all npm dependencies (including devDependencies) before build so `vite` is available, then prune dev deps after

## [1.2.4] - 2026-03-10

### Fixed

- **Deploy script** — Commands now run in a login shell (`bash -l`) so `.profile` is sourced and tools like `node`/`npm` are available

## [1.2.3] - 2026-03-10

### Fixed

- **Deploy script** — Added `--ignore-platform-reqs` to `composer install` to work around CLI PHP version mismatch

## [1.2.2] - 2026-03-10

### Fixed

- **Deploy script** — Production deploys no longer fail when unstaged changes exist (e.g. from `composer install`); local changes are now stashed before pull and restored after
- **Import date format** — Fixed `Invalid datetime format` error on MariaDB when importing data with ISO-8601 date strings in `date` columns (e.g. `next_bila_date`, `deadline`, `scheduled_date`)

## [1.2.1] - 2026-03-10

### Fixed

- **Datepickr** - There was an issue with date formats

## [1.2.0] - 2026-03-10

### Added

- **Two-factor authentication (TOTP)** — Enable/disable MFA via profile settings using any TOTP authenticator app (Google Authenticator, Authy, etc.)
- **2FA login challenge** — Users with 2FA enabled must enter a 6-digit code or recovery code after password authentication before accessing the app
- **Recovery codes** — Eight single-use recovery codes generated when enabling 2FA, consumed on use
- **EnsureTwoFactorChallengeCompleted middleware** — Enforces 2FA challenge for all authenticated routes when 2FA is enabled
- **Flatpickr date picker** — All date fields across the app now use Flatpickr for consistent date input (tasks, follow-ups, bilas, weekly reflections, agreements, filters)
- **Date picker Alpine component** — Reusable `datePicker` Alpine.js component wrapping Flatpickr with x-model compatibility
- **Auto-save date field type** — `<x-tl.auto-save-field type="date">` now renders a Flatpickr-powered input
- **Task group management from list view** — Create and delete task groups directly from the tasks list toolbar (previously only in Settings)
- **Web export/import** — Export and import application data via the settings page

### Security

- **2FA enforcement** — Two-factor authentication challenge integrated into the login flow with session-based verification

## [1.1.0] - 2026-03-10

### Added

- **Inline select component** — New `<x-tl.inline-select-pill>` Blade component with Alpine.js integration for inline priority/status editing on task cards
- **Live UI counters** — Dashboard counters now auto-refresh via central event dispatch and dedicated `CounterController` API endpoint
- **`DashboardStatsService`** — Extracted dashboard statistics into a reusable service
- **SecurityHeaders middleware** — Adds `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and `X-XSS-Protection` headers to all responses
- **API rate limiting** — All API endpoints now throttled at 60 requests/minute per user

### Changed

- **Rebranding** — Renamed from "TeamDash" to "Mithril" with payoff "Lightweight armor for team leads"
- Updated all logo SVGs with new shield icon and Mithril branding (light, dark, icon-only, auth)
- Updated PWA manifest, app icons, and favicon with shield motif
- Updated page titles, login page heading, and payoff across all Blade templates
- Updated project README.md with new branding
- **Rivendell UI redesign** — Sage green brand palette, warm stone grays, Cormorant Garamond serif headings
- Added decorative background SVGs (trailing vines, clover flowers, arch ornament) on desktop
- Added `elvish-card` utility with corner ornaments on cards
- Added `elvish-divider` and `elvish-divider-leaf` decorative utilities
- Added Tolkien-themed sidebar widget with quote
- Login page redesign with atmospheric styling and decorative arch

### Security

- **XSS prevention** — Markdown preview now sanitized with DOMPurify before DOM injection via `x-html`
- **Mass assignment hardening** — Removed `user_id` from `$fillable` on all 12 models; `BelongsToUser` trait handles ownership automatically
- **AutoSaveController blocklist** — Fields `id`, `user_id`, `created_at`, `updated_at` can no longer be auto-saved
- **Bulk update field whitelist** — `bulkUpdate` endpoint now only accepts explicitly allowed fields (status, priority, team, member, group, category, deadline, privacy)
- **Import sanitization** — Import rows are now filtered against model `$fillable`, with `id` and `user_id` stripped before insertion
- **IDOR prevention** — All `exists:` validation rules scoped to authenticated user via `Rule::exists()->where('user_id', ...)`
- **Password change protection** — Settings page now requires current password when changing password (`required_with:password`)
- **Avatar upload hardening** — Restricted to `jpeg`, `png`, `webp` MIME types (blocks SVG with embedded scripts)
- **Session hardening** — Reduced default session lifetime from 30 days to 1 day, enabled session encryption and secure cookies in `.env.example`
- **Debug mode** — `APP_DEBUG` defaults to `false` in `.env.example`

### Fixed

- Dark mode background color consistency for sidebar and header

## [1.0.0] - 2026-03-09

### Added

- **Dashboard** — Greeting with time-based message, counters for open tasks/follow-ups/bilas, today-section with upcoming items, quick-add inline form
- **Tasks** — Full task management with priorities, categories, task groups, privacy flag, kanban and list views, drag & drop sorting via SortableJS, bulk actions
- **Follow-ups** — Timeline view organized by urgency (overdue > today > this week > later), snooze functionality, auto-populated from tasks with "waiting" status
- **Teams & Members** — Team management with member profiles, avatar uploads, linked tasks, follow-ups, bila history, and agreements per member
- **Bilas** — Recurring 1-on-1 meeting management with prep items checklist, markdown notes, and scheduling
- **Agreements** — Track agreements per team member with automatic follow-up creation
- **Notes** — Markdown editor with live preview, tagging system, pinning, and full-text search
- **Weekly Reflection** — Auto-generated weekly summary with free-form reflection input
- **Analytics Dashboard** — Configurable widget-based analytics with ApexCharts, draggable layout, and multiple chart types
- **Auto-save** — Debounced AJAX auto-save (500 ms) across all editable content, no manual save buttons
- **Drag & drop** — Generic reorder system via SortableJS, works for tasks, task groups, widgets, and more
- **Filtering & search** — Generic filter and search system via reusable model traits
- **PWA support** — Service worker, web app manifest, offline fallback page, push notifications
- **Authentication** — Email/password login with remember-me cookie support
- **Dark mode** — Full dark mode support via TailAdmin theme system
