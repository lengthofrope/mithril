# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-03-11

### Added

- **Office 365 integration** — Calendar sync and team member availability sync from Microsoft Graph API
- **O365 auto-detection** — When a team member's email is saved or the member is created, the system probes the Graph API to determine if it's a valid O365 mailbox and automatically sets the status source
- **`microsoft:detect-members` command** — Artisan command that checks all manual team members for O365 mailbox compatibility and upgrades matching members to automatic availability sync
- **Calendar page** — Dedicated `/calendar` page showing all synced events for the next 7 days, grouped by day, with collapsible day sections
- **Calendar event resource linking** — Link calendar events to existing bilas, notes, or follow-ups via a polymorphic pivot table; create new bilas directly from 1-on-1 meetings with a matched team member
- **Calendar event pills** — Linked resources displayed as colored pills on calendar events (dashboard widget and calendar page)
- **Calendar attendee sync** — Attendees from Microsoft Graph calendar events are synced and stored for resource linking
- **Dashboard calendar widget** — Upcoming events widget on the dashboard showing the next 3 events until end of week, with "View all" link to calendar page
- **Dashboard quick-create modals** — Quick-create buttons for tasks, follow-ups, notes, and bilas with team/member/category/group selectors
- **Edit member modal** — Inline editing of team member details via auto-save fields
- **User timezone setting** — New timezone selector on the Settings page (auto-saves, defaults to Europe/Amsterdam) used for calendar display, day grouping, greeting, and "today" boundaries
- **New member statuses** — `In a meeting` (red), `Working elsewhere` (blue), `Partially available` (yellow) join `Available` (green) and `Absent` (gray)

### Changed

- **Sync intervals** — Calendar and availability sync both run every 5 minutes (previously 15 and 5)
- **Status color scheme** — `Absent` changed from red to gray; `In a meeting` (busy) is now red; `Working elsewhere` is blue
- **Dashboard layout** — Three-column "today" section (tasks, follow-ups, upcoming/bilas) now stretches to equal height; counter card spacing unified
- **Calendar page window** — Shows a rolling 7-day window instead of until end-of-week
- **About page changelog** — Improved readability with colored section badges (Added, Changed, Fixed, Security), removed bullet points and em dashes, added spacing between item title and description

### Fixed

- **O365 detection failure** — `isKnownMicrosoftUser()` always returned false due to a 1-minute time window with a 60-minute `availabilityViewInterval` (Graph API requires interval ≤ window)
- **Availability sync failure** — `SyncMemberAvailabilityJob` used a 30-minute window with a 60-minute interval, causing the same Graph API validation error
- **Calendar times off by one hour** — Events were displayed in UTC instead of the user's timezone
- **Status mapping** — O365 `busy` now correctly maps to "In a meeting" (was "Partially available"); `tentative` maps to "Partially available" (was "Available")
- **Analytics timezone bug** — Charts and deadline/urgency buckets used UTC instead of the user's timezone, causing "today" to lag behind after midnight in non-UTC timezones

## [1.2.7] - 2026-03-10

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
