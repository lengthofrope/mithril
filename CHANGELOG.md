# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.9.0] - Unreleased

### Added

- **Meetings module** — Complete replacement of the Bila (1-on-1) system with a generalized Meeting model supporting team meetings, 1-on-1s, and custom meeting types; multi-attendee support via pivot table; meeting title, type badges, and status lifecycle (scheduled → in progress → completed/cancelled)
- **Meeting CRUD & views** — Meetings index page with filters on team, member, type, and status; create modal with title, type, date, team, and attendee selection; detail page with editable title (auto-save), type/status badges, status transition buttons, attendee list, and previous/next navigation
- **Meeting prep items** — Extended prep items with type selection (agenda item, question, action), time estimates, and attendee assignment; drag-and-drop reordering; total estimated time display; detailed response data per prep item
- **Audio recording** — In-browser audio recording via MediaRecorder API with start/stop/pause controls and live timer; file upload fallback for existing audio files (mp3, wav, webm, m4a, ogg); configurable storage disk and max upload size; audio player for playback; recording deletion with transcription safety hint
- **Transcription service** — Provider-agnostic transcription via `TranscriptionServiceInterface`; OpenAI Whisper implementation; queued `TranscribeMeetingJob` with 3 retries and exponential backoff; auto-dispatch after recording upload (configurable); transcription language selectable per meeting (NL/EN); status polling UI; retry on failure; manual transcription input as fallback; retranscription support; processing timing display
- **WhisperCpp transcription provider** — Self-hosted alternative to OpenAI Whisper via whisper.cpp server; GPU acceleration support (Vulkan/CUDA); Docker-based deployment with model configuration
- **Speaker diarization** — Pyannote-audio service for identifying speakers in meeting recordings; waveform-based speaker segment extraction; speaker labels displayed in transcription view
- **AI extraction & review** — Provider-agnostic insight extraction via `MeetingInsightExtractorInterface`; OpenAI GPT-4o-mini implementation with structured JSON output; queued `ExtractMeetingInsightsJob`; AI-generated meeting summary; extraction review UI with per-item accept/reject/edit, bulk actions, and re-extract; accepted extractions create real Task/FollowUp/Agreement records linked to the source meeting via `meeting_id` FK; edit modal with team-to-user assignment selection; styled confirmation modal for (re-)extraction; dynamic button label ("Extract" / "Re-extract" based on state)
- **Feature flags** — Configurable feature flags for recording, transcription, and AI extraction; UI sections conditionally rendered based on flags
- **Meeting scheduling auto-save** — Meeting scheduling date input uses auto-save with debounced AJAX instead of form submission
- **Meeting member view enhancements** — Team member detail page shows transcription and extraction statistics per meeting (accepted/modified extraction count)
- **Meeting scheduling** — `ScheduleNextMeeting` listener auto-calculates `next_meeting_date` for 1-on-1 meetings based on `meeting_interval_days` on the team member
- **Meetings in global search** — Meetings searchable by title and notes via the existing global search endpoint
- **Meetings in analytics** — New "Meetings by Type" data source for analytics widgets (1-on-1 / Team / Other breakdown)
- **Calendar event linking** — Meeting detail page shows linked calendar events; calendar/email/Jira actions support creating meetings
- **Configuration** — New `config/meetings.php` with recording storage, transcription provider, and extraction provider settings
- **Task show_completed filter** — "Show completed" is now a server-side boolean filter in the filter panel; tasks with status "done" are excluded by default; explicit `status=done` filter overrides this
- **Task view preference** — Last-visited task view (list or kanban) is stored in `localStorage`; the "Back to tasks" link on task detail pages and the sidebar Tasks link both respect the preference
- **Global select arrow styling** — All `<select>` elements now render a consistent custom SVG chevron via a global CSS rule, with dark mode variant and `select[multiple]` exception

### Changed

- **Bila → Meeting migration** — All existing bila data automatically migrated to the new meetings system: bilas become one_on_one meetings, team_member_id becomes an attendee record, bila_prep_items become meeting_prep_items; all polymorphic references (activities, calendar event links) updated
- **TeamMember scheduling fields** — `bila_interval_days` renamed to `meeting_interval_days`, `next_bila_date` renamed to `next_meeting_date`
- **Dashboard widget** — "Bila's" section replaced with "Meetings" showing upcoming and today's meetings
- **User preference** — `dashboard_upcoming_bilas` renamed to `dashboard_upcoming_meetings`
- **Action controllers** — Email, Jira, and Calendar action controllers updated: 'bila' resource type replaced with 'meeting'; meeting creation attaches attendees via pivot table
- **Weekly review** — Weekly reflection stats and summary text updated from "bilas held" to "meetings held"
- **Storage quota** — Audio recordings now count towards the existing file storage quota alongside attachments
- **Meeting show page layout** — Restructured from 3-equal-column grid to 2/3 + 1/3 layout (prep items and notes in main column, recording/transcription tab in sidebar); activity feed integrated into the recording tab instead of a separate activity tab
- **Meeting team filter** — Filtering by team now shows meetings where any attendee belongs to that team (via `whereHas`), not just meetings with a direct `team_id` match
- **Meeting attendee validation** — Improved attendee attachment logic with proper team selection and validation
- **Meeting breadcrumbs** — Enhanced breadcrumb logic for one-on-one and team meeting types
- **Transcription prompt** — Enhanced with recording availability context and action button for triggering transcription
- **AI extractor** — Updated to latest model version with enhanced response handling
- **Filter bar + toolbar layout** — All index pages (tasks, kanban, follow-ups, notes, meetings) now use a `relative` container with action buttons absolutely positioned top-right; the filter panel expands full-width underneath the buttons instead of being constrained to a flex column
- **Follow-ups AJAX detection** — Changed `$request->wantsJson()` to `$request->ajax()` in `FollowUpPageController::index` to match the filterManager's `X-Requested-With: XMLHttpRequest` header; fixes filter results rendering the full page instead of the partial
- **Filter bar component cleanup** — Removed manual SVG arrow overlays and `relative` wrapper divs from `filter-bar.blade.php`; select arrows now handled by the global CSS rule
- **Navigation restructured** — Sidebar reordered by usage intent: core workflow (Meetings, Tasks, Follow-ups, Notes) → integrations (Calendar, E-mail, Jira) → team management (Teams, Weekly Review) → insights (Analytics); Tasks sub-menu (List View / Kanban) removed in favor of a single direct link that respects the `localStorage` view preference
- **Textarea auto-resize** — Improved logic to account for minimum height, preventing layout jumps
- **Permissions-Policy and CSP headers** — Updated security headers with stricter policies

### Removed

- **Bila system** — Removed `Bila` model, `BilaPrepItem` model, `BilaController`, `BilaPageController`, `BilaRequest`, `BilaScheduled` event, `ScheduleNextBila` listener, and all associated factories, tests, and Blade views
- **Client-side showCompleted toggle** — Removed `Alpine.store('taskList')` and `hideWhenDone` prop from task cards; replaced by the server-side `show_completed` filter
- **Separate activity tab** — Activity feed moved into the recording tab on the meeting detail page; standalone activity tab removed

## [1.8.0] - 2026-03-18

### Added

- **Task search** — Search now works on both the task list and kanban views; previously the `search` parameter was silently dropped by the controller and the `Searchable` trait scope was never called
- **Task overdue filter** — New "Overdue" boolean filter on task list and kanban views; shows only tasks with a deadline before today and status ≠ done; implemented via `scopeOverdue` on the Task model
- **Task recurring filter** — New "Recurring" boolean filter on task list and kanban views; filters tasks by `is_recurring` via the existing Filterable trait
- **Inline task group edit** — Task group can now be changed directly on the task card via an `inline-select-pill`, without opening the task detail page
- **Kanban AJAX partial** — Kanban board markup extracted into `partials/kanban-board.blade.php`; the `kanban()` controller method now returns only the board partial for AJAX requests instead of the full page, fixing the broken layout when filtering
- **Analytics daily deltas** — Analytics snapshots now compute daily deltas for tasks and follow-ups, enabling trend tracking over time
- **Microsoft/Jira user attributes** — Added additional user attributes for Microsoft and Jira integration configuration

### Changed

- **Filter bar redesign** — Completely redesigned filter bar component used on all list pages (tasks, kanban, follow-ups, notes, bila's): search is always visible at the top; dropdown filters and boolean toggles collapse behind a "Filters" button; active filter count shown as a badge; "Clear all" link appears when filters are active; boolean filters rendered as toggleable pills instead of checkboxes; select dropdowns use `appearance-none` with custom SVG chevrons for consistent cross-browser styling; responsive 2-column grid on mobile, flex-wrap on desktop
- **Dashboard priority sorting** — Dashboard task widgets (today + upcoming) now sort by priority within each day (urgent → high → normal → low) using a `CASE WHEN` expression, in addition to the existing deadline sort
- **Kanban drag-and-drop** — Removed `forceFallback`, `scroll`, `scrollSensitivity`, and `scrollSpeed` options that caused a "locked in place" visual glitch; added `emptyInsertThreshold: 80` so cards can be dropped into sparse columns without needing to hover directly over an existing item; column containers now use `flex-1` to fill available height as drop targets
- **Kanban status sync on drag** — Moving a card between kanban columns now correctly updates the status `inline-select-pill` Alpine component on the card; previously targeted a non-existent `[data-status-badge]` element
- **Follow-up snooze logic** — Snooze now adds days to the existing `follow_up_date` instead of today's date, preserving the original schedule offset
- **Email sync pagination** — Microsoft Graph email sync now follows `@odata.nextLink` pagination to fetch all inbox messages instead of only the first page
- **Email group collapsing** — Older email date groups only collapse when cumulative email count exceeds 15; small inboxes show all groups expanded
- **Dashboard responsive columns** — When both Jira and Outlook integrations are disconnected, the dashboard uses a 2-column layout instead of showing an empty third column

### Security

- **Nonce-based CSP** — Added Content Security Policy header with per-request nonce; all inline scripts use nonce attributes instead of `'unsafe-inline'`
- **File upload restrictions** — MIME type whitelist on attachment uploads; hardened file handling and validation across controllers
- **Sensitive field mass-assignment** — Removed OAuth tokens and 2FA secrets from `User::$fillable`

### Fixed

- **Task search broken in list view** — Search parameter was not included in `$request->only()` whitelist; `->search()` scope was never called on the query
- **Task search breaks kanban layout** — Kanban controller had no `$request->ajax()` check, returning the full page HTML instead of a partial for filtered AJAX requests
- **Kanban `is_private` filter ignored** — The `is_private` filter was defined in the kanban filter bar but not included in the controller's `$request->only()` whitelist
- **Filter bar checkbox labels not clickable** — Boolean filter labels were `<span>` elements; changed to `<label>` with `for` attribute for accessibility

## [1.7.0] - 2026-03-15

### Added

- **Textarea auto-resize** — All textareas across the app now automatically grow to fit their content, eliminating internal scrollbars; works on existing and dynamically added textareas via global event delegation and MutationObserver
- **Note date field** — Notes now have an editable date field (Flatpickr date picker on the detail page); defaults to today when creating a new note; notes list sorted by date instead of `updated_at`; date displayed on note cards in the overview
- **Note tag editor** — Interactive tag management on the note detail page replacing the read-only tag display; add tags via Enter/comma, remove by clicking ×, with autocomplete suggestions from all existing tags (arrow key navigation, click or Enter to select); auto-syncs to new `PUT /api/v1/notes/{note}/tags` endpoint
- **Activity feed** — Chronological activity feed on all resource detail pages (tasks, follow-ups, notes, bilas) with support for markdown comments, URL links with optional title/description, and file attachments (max 10 MB each, max 5 per activity); displayed in a responsive sidebar (1/3 width on desktop, full width on mobile)
- **File attachments** — Upload files via drag & drop or file picker; images show inline preview thumbnails; all files served via signed download URLs with 30-minute expiry; private storage in `storage/app/private/attachments/`
- **Inline file preview** — Separate `/attachments/{id}/preview` route serving files with `Content-Disposition: inline` so image thumbnails render in the browser; download route remains for forced downloads
- **Storage quota** — Per-user attachment storage limit (default 1 GB, configurable via `ATTACHMENT_MAX_STORAGE_MB` in `.env`); upload rejected with 422 when quota exceeded
- **Storage management page** — New settings sub-page (`/settings/storage`) showing storage usage with progress bar (color-coded: green/orange/red), file count, and a complete list of all uploaded files with filename, size, upload date, and linked parent resource (clickable link to task/follow-up/note/bila)
- **Orphaned file cleanup** — Storage page detects files whose parent resource has been deleted and shows how much space can be freed; one-click "Remove orphaned files" button purges all orphaned attachments and their physical files
- **Individual file deletion** — Delete individual attachments from the storage management page via `DELETE /api/v1/attachments/{id}`; automatically cleans up the parent activity when the last attachment is removed
- **Attachment deletion system logs** — Removing attachments (from activity feed or storage page) logs a system event on the parent resource (e.g. "Attachment removed: filename.pdf") so the deletion is traceable in the activity feed
- **Delete task** — Task detail page now has a "Delete task" button with styled confirmation modal; deletes the task and all associated activity via the existing API endpoint
- **System events** — Automatic activity feed entries when tracked fields change: status, priority, is_done, snoozed_until; human-readable descriptions (e.g. "Status changed: open → done") with old/new values in metadata
- **Refreshable polling component** — Generic `refreshable` Alpine.js component for ETag-based HTML partial polling; pauses when browser tab is inactive, resumes with immediate refresh on focus; debounces rapid `data-changed` triggers (300ms)
- **Dashboard section polling** — Dashboard tasks, follow-ups, bilas, calendar, and email sections now poll for background updates (30s/60s intervals) via the refreshable component with ETag-based 304 responses
- **List page polling** — Tasks list and follow-ups timeline pages wrapped in refreshable components (30s polling) for background sync updates
- **Topic-scoped data-changed events** — `apiClient.dispatchDataChanged(topic?)` method for targeted UI refresh; refreshable component filters by topic when configured
- **Skeleton loading placeholders** — Dashboard section skeletons shown during initial lazy-load with pulse animation
- **Orphaned attachment cleanup command** — `attachments:clean-orphaned` artisan command scheduled weekly to find and delete attachments without a parent activity, including physical file removal
- **Activity & Attachment factories** — Full factory support with states for all four activity types (comment, link, attachment, system); database seeder includes sample activities
- **Task → Follow-up conversion** — Convert a task to a follow-up from the task detail page; marks the task as done, creates a linked follow-up with deadline carried over as follow-up date, and transfers all metadata (comments, links, files, calendar event links, email links); styled confirmation modal
- **Create follow-up from task** — Generate a linked follow-up from a task without closing the task; the follow-up inherits the task's team member and deadline, and links back to the originating task
- **Linked follow-ups on task detail** — Task detail page shows all linked follow-ups with status badges and click-through navigation
- **Linked task on follow-up detail** — Follow-up detail page shows the originating task (when linked) with click-through navigation
- **`MetadataTransferService`** — Reusable service for transferring polymorphic metadata (activities, calendar event links, email links) between models during entity conversion
- **Custom error pages** — Branded error pages (401, 403, 404, 419, 429, 500, 503) with Rivendell theme, Mithril logo, dark/light mode support, and LOTR-themed messages (e.g. "You Shall Not Pass!" for 401, "Not All Who Wander Are Lost" for 404)

### Changed

- **Email sync pagination** — Email sync now follows Microsoft Graph `@odata.nextLink` pagination to fetch all inbox messages instead of only the first 50
- **Dashboard responsive columns** — When both Jira and Outlook integrations are disconnected, the dashboard switches from a 3-column to a 2-column layout instead of showing an empty third column
- **Email date group collapsing** — Older email groups on the mail page now only collapse when the cumulative email count exceeds 15; small inboxes show all groups expanded
- **Sidebar logo size** — Increased logo from 210 to 250 width for better visibility
- **Navigation** — Moved Bila's menu item below Teams for better logical grouping (previously between Notes and the separator)
- **Robots.txt** — Blocked all crawlers from indexing the site (`Disallow: /`)
- **Default theme** — New users default to dark mode instead of following OS preference; existing users who explicitly chose light mode keep their preference
- **Confirmation modals** — Activity feed delete buttons and follow-up delete button now use styled confirmation modals (backdrop blur, escape key, fade/scale transitions) instead of browser `confirm()` dialogs
- **Detail page layouts** — Task, follow-up, note, and bila detail pages now use a 2-column grid layout (2/3 content + 1/3 activity feed) on desktop
- **PartialController** — New controller serving ETag-cached HTML fragments for activity feeds and all dashboard sections
- **Follow-up card AJAX actions** — Dashboard follow-up card buttons (Done, Snooze) now use AJAX requests instead of form submissions, preventing full page reloads; the `refreshable` component picks up changes immediately via topic-scoped `data-changed` events
- **Refreshable topic filtering** — Topic check moved before the debounce in the `refreshable` component, fixing an issue where rapid back-to-back events with different topics (e.g. `follow_ups` then `tasks`) would only process the last one
- **Refreshable wildcard events** — `data-changed` events without a topic now trigger all `refreshable` components (previously ignored by topic-filtered components), so inline status changes on task cards immediately refresh the dashboard
- **Follow-up → Task conversion** — Now transfers all metadata (comments, links, files, calendar/email links) to the new task; confirmation modal added on detail page; copies `follow_up_date` as task deadline; redirects to new task instead of back
- **Convert to task removed from dashboard cards** — Conversion only accessible from detail pages where the full confirmation modal and metadata transfer are available
- **Data pruning always active** — Data pruning can no longer be disabled; defaults to 90 days; settings input is required (30–365 days); "Prune now" button always visible; `data:prune` command runs for all users
- **Dashboard widget defaults** — Dashboard upcoming items (tasks, follow-ups, bilas) default to 5 when not configured, instead of being disabled; can still be set to 0 to disable

### Security

- **Nonce-based Content Security Policy** — Added CSP header with per-request nonce via `Vite::useCspNonce()`; all inline scripts use nonce attributes instead of `'unsafe-inline'`; blocks unauthorized script injection while allowing Alpine.js (`'unsafe-eval'`); includes `worker-src`, `frame-src`, and `frame-ancestors` directives
- **Import data isolation** — Import no longer uses `truncate()` (which destroyed ALL users' data); replaced with user-scoped `delete()` so only the importing user's records are affected
- **Password change verification** — Fixed bypass where submitting `current_password=""` skipped the `Hash::check` verification; now uses Laravel's built-in `current_password` validation rule
- **2FA remember-me bypass** — Users authenticated via remember-me cookie no longer skip the two-factor challenge; 2FA is always required for new sessions
- **File upload restrictions** — Added MIME type whitelist to attachment uploads (images, documents, archives only); previously accepted any file type including executable files and SVGs with embedded scripts
- **SVG preview XSS prevention** — SVG attachments now served with `Content-Disposition: attachment` instead of inline, preventing embedded JavaScript execution in the browser
- **Sensitive fields mass-assignment hardening** — Removed OAuth tokens (`microsoft_access_token`, `jira_refresh_token`, etc.), 2FA secrets, and password from `User::$fillable`; these fields are now only writable via `forceFill()` in their dedicated controllers
- **Color input validation** — All color fields (teams, task groups) now validated with hex regex (`/^#[0-9A-Fa-f]{3,6}$/`) instead of permissive `max:20` string rule
- **Task category unique scope** — `unique:task_categories,name` validation now scoped to the authenticated user, so different users can have categories with the same name
- **AutoSave value constraint** — Added `nullable|max:10000` validation to the auto-save `value` field to prevent oversized payloads
- **Attachment filename sanitization** — Stored filenames now use only the guessed extension instead of the original filename, preventing path traversal via crafted filenames
- **ETag hash upgrade** — Replaced MD5 with xxh128 for ETag generation in `PartialController`

### Fixed

- **Email sync crash on special characters** — Email sync failed with `SQLSTATE[22007]: Incorrect string value` when Microsoft Graph returned body previews containing invalid UTF-8 byte sequences (e.g. lone `\xE2` bytes); all text fields are now sanitized via `mb_convert_encoding()` before database upsert
- **Email body preview truncation** — `substr()` could split multi-byte UTF-8 characters (em-dashes, curly quotes) at the 500-character boundary, producing invalid bytes that MariaDB rejected; replaced with `mb_substr()`
- **Broken attachment thumbnails** — Image thumbnails in the activity feed showed a broken image icon because the download URL was unsigned (403) and served with `Content-Disposition: attachment`; now uses signed inline preview URLs
- **Attachment download 403** — Clicking attachment links returned "Invalid signature" because the Blade template generated unsigned URLs instead of calling the model's `downloadUrl()` method

## [1.6.2] - 2026-03-13

### Changed

- **Heading font** — Replaced Cormorant Garamond with Philosopher for headings; more readable while retaining the elvish/fantasy character
- **Inline SVG logos** — Logo components now render inline SVG instead of `<img>` tags, ensuring custom fonts display correctly; extracted to reusable `<x-tl.logo>` Blade component
- **Jira privacy compliance** — Removed personal data storage (assignee/reporter names and emails) from the Jira integration to comply with the Atlassian User Privacy Developer Guide; only Atlassian account IDs are stored, with display names resolved on demand via the Jira bulk user API and cached for 1 hour
- **Jira URL construction** — Site URL now correctly extracted from the OAuth accessible-resources response instead of using the cloud ID as subdomain
- **Jira team member resolution** — Team members are now matched to Jira users by `jira_account_id` instead of email; auto-populated on first resource creation, or linkable manually in member settings

## [1.6.1] - 2026-03-13

### Fixed

- **Dashboard missing overdue tasks** — Tasks with past deadlines were invisible on the dashboard; now shown alongside today's tasks, ordered by deadline ascending (oldest overdue first)
- **Task card team member not displayed** — Task cards never showed the assigned team member due to referencing a non-existent `member` relationship instead of `teamMember`; now displays member name and team name as plain text below the status pills
- **Task card deadline styling** — Deadlines now color-coded: red for overdue (with "Overdue" prefix), orange for today, default gray for future; includes "Today"/"Tomorrow" labels for nearby dates

### Added

- **Overdue tasks counter** — New "Overdue tasks" counter card on the dashboard replacing the "Urgent tasks" card, showing the count of non-done tasks with past deadlines

### Changed

- **Dashboard task section header** — Renamed from "Tasks due today" to "Tasks needing attention" to reflect the broader scope including overdue items

## [1.6.0] - 2026-03-13

### Added

- **System broadcast notifications** — Admins can send dismissable notifications to all users via `notification:send` Artisan command; supports info/warning/success/error variants, optional links, and auto-expiry; displayed as alert banners at the top of every page with smooth dismiss animation
- **Jira Cloud integration** — Connect your Atlassian account via OAuth 2.0 (3LO) to sync Jira issues; `SyncJiraIssuesJob` runs on schedule; `jira:sync-issues` Artisan command for manual sync
- **Jira browse page** — Dedicated `/jira` page showing all synced issues grouped by project, with source tabs (assigned/mentioned/watched), status category filters, and project dropdown
- **Jira dashboard widget** — Open issues assigned to you displayed on the dashboard, ordered by priority
- **Jira resource linking** — Create tasks, follow-ups, notes, or bilas directly from Jira issues with prefilled data (priority mapping, team member matching by account ID); linked resources shown as colored pills on issue cards
- **Jira dismiss/undismiss** — Dismiss irrelevant issues from the browse page; toggle visibility with "Show dismissed" link
- **Jira data pruning** — Dismissed issues pruned after user's retention period; stale unsynced issues (>30 days) cleaned up automatically
- **Jira disconnect cleanup** — Disconnecting Jira removes all cached issues and links for the user
- **Jira team member linking** — Link team members to their Jira account via `jira_account_id` field on the member profile; auto-populated on first resource creation, or set manually in settings
- **Manual sync buttons** — Refresh buttons on Jira, calendar, and email pages (both browse pages and dashboard widgets) that trigger a sync job and automatically reload once complete; polls `synced_at` timestamp to detect job completion
- **Sync status API** — `GET /api/v1/sync/{type}/status` endpoint returns the latest `synced_at` timestamp for polling sync completion
- **Calendar empty days** — Calendar page now shows all 7 days including weekends and days without events, instead of hiding empty days

### Changed

- **Navigation** — Jira menu item conditionally shown when a Jira account is connected
- **Data pruning** — Extended `DataPruningService` and `PruneResult` to include Jira issue cleanup and orphaned link removal
- **Jira search API** — Migrated from deprecated `GET /rest/api/3/search` to `POST /rest/api/3/search/jql` endpoint
- **Jira default filters** — Browse page defaults to "assigned" source tab and hides done issues unless the Done filter is explicitly selected
- **Jira project dropdown** — Only shows projects matching the active source and status filters; selected project persists in dropdown even when other filters produce no results; switching projects preserves all active filters
- **Jira header layout** — Single-line non-wrapping header with the project dropdown shrinking to fit available space; filter tabs and count badge stay fixed
- **Jira dismiss/undismiss** — Migrated from Alpine v2 `__x.$data` pattern to `$dispatch` events for Alpine v3 compatibility
- **Jira dashboard widget** — Stretches to match the height of adjacent dashboard columns
- **Jira privacy compliance** — No personal data (names, emails) stored from Jira; only Atlassian account IDs are persisted, with display names resolved on demand via the Jira bulk user API and cached for 1 hour; compliant with the Atlassian User Privacy Developer Guide for Marketplace listing
- **Jira URL construction** — Site URL now correctly extracted from the OAuth accessible-resources response instead of using the cloud ID as subdomain
- **Layout flex constraint** — Added `min-w-0` to the main content flex wrapper in the app layout, enabling `truncate` to work correctly throughout the app for long text
- **Jira issue card** — Long issue summaries now truncate properly with ellipsis instead of causing horizontal scrollbars

## [1.5.0] - 2026-03-12

### Added

- **E-mail integration** — Sync inbox emails from Microsoft 365 via Graph API; dedicated `/mail` page with email cards showing sender, subject, importance, and timestamp; `SyncEmailsJob` runs on schedule; `sync:emails` Artisan command for manual sync
- **E-mail dashboard widget** — Flagged emails with deadlines displayed in a dashboard widget for quick triage
- **E-mail resource linking** — Link emails to existing tasks, follow-ups, notes, or bilas via resource pills; reusable `<x-tl.email-pills>` and `<x-tl.email-actions>` Blade components with Alpine.js
- **E-mail sender avatars** — Color-coded avatar circles for email senders based on sender name
- **`HasResourceLinks` trait** — Reusable Eloquent trait for automatic cleanup of polymorphic resource links on model deletion; applied to Task, FollowUp, Note, and Bila
- **Sidebar collapsed preference** — Sidebar collapsed/expanded state is persisted per user and restored on page load

### Changed

- **Office 365 connector** — Updated Microsoft Graph API integration with additional mail permissions for email sync; **reconnecting your Office 365 account is required** for the new e-mail features to work
- **Dashboard task cards** — Drag & drop disabled on dashboard task cards to prevent accidental reordering outside the tasks page
- **Sidebar** — Simplified hover and collapsed state logic for cleaner interaction

## [1.4.0] - 2026-03-12

### Added

- **Recurring tasks** — Tasks can be configured with recurrence intervals (daily, weekly, biweekly, monthly, quarterly, yearly) with optional end dates; completed recurring tasks automatically generate the next occurrence via an observer and `RecurrenceService`
- **Recurrence settings component** — Reusable `<x-tl.recurrence-settings>` Blade component with Alpine.js for configuring task recurrence on the task detail page
- **User account management** — Activate and deactivate user accounts via `user:enable` and `user:disable` Artisan commands; disabled users are blocked at login and by middleware
- **`user:list` command** — Artisan command to list all user accounts with their active/disabled status
- **Configurable dashboard upcoming items** — Dashboard widgets (tasks, follow-ups, bilas) can optionally show upcoming items beyond today; configurable per-widget in Settings with an elvish-leaf divider separating today from future items, and dynamic widget titles
- **Data pruning** — Configurable per-user retention period for completed tasks, past follow-ups, and old bilas; runs via `data:prune` Artisan command and daily schedule
- **Prune settings UI** — New "Data Retention" section on the Settings page to configure pruning retention days (auto-saves)
- **MIT license** — Added LICENSE file and updated `composer.json` license field
- **Calendar "now" divider** — Decorative leaf divider on the calendar page separates past events from upcoming ones in the Today section
- **Calendar past events** — Events that have already ended are greyed out on the calendar page while keeping actions fully interactive

### Security

- **`is_active` mass assignment hardening** — Removed `is_active` from `User::$fillable` to prevent potential privilege escalation; field is now managed exclusively via explicit assignment
- **Disabled user API bypass** — Added `EnsureAccountIsActive` middleware to the API middleware group so disabled users are blocked on both web and API routes; returns JSON 403 for API requests
- **AutoSave field restriction** — Blocked `recurrence_parent_id` and `recurrence_series_id` from being writable via `AutoSaveController`; these fields are now only set by `RecurrenceService`
- **Data pruning cross-user leak** — Scoped orphaned `CalendarEventLink` cleanup in `DataPruningService` to the user being pruned, preventing unintended deletion of other users' calendar links

### Fixed

- **View transition FOUC** — Added `<link rel="expect" blocking="render">` to prevent flash of unstyled content during cross-document view transitions; moved `pagereveal` handler to `<head>` as parser-blocking script per spec requirements
- **Font flash during transitions** — Added `<link rel="preload">` for Outfit and Cormorant Garamond fonts to eliminate font-swap flash during page transitions
- **Layout shift between pages** — Forced persistent vertical scrollbar to prevent width jump when navigating between short and long pages

### Changed

- **Quick-create redirects to detail page** — Creating a task, follow-up, note, or bila from the dashboard now redirects to the resource's detail page so additional fields can be set immediately
- **Self-hosted fonts** — Cormorant Garamond and Outfit fonts are now self-hosted (woff2) instead of loaded from Google Fonts, improving privacy and offline support
- **Base font size** — Increased root font size to 18px for improved readability
- **About page** — Enhanced with development stack info, technology badges, and credits section
- **Profile & settings layout** — Improved responsiveness of profile and settings pages
- **Vite build config** — Added manual chunk splitting and chunk size warning configuration
- **Seeded user** — Default seeded user is now disabled by default

## [1.3.1] - 2026-03-11

### Fixed

- **Dashboard upcoming widget shows past events** — Widget now filters by `end_at` instead of `start_at >= startOfDay`, so events that have already ended are excluded and the list dynamically advances throughout the day

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
