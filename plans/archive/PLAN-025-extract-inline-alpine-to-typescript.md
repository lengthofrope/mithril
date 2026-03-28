# Extract Inline Alpine.js to TypeScript Components

**Created:** 2026-03-28
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

31 Blade templates contain inline `x-data="{...}"` JavaScript objects and 5 templates use `<script>` tags. This slows page loading and initialization, bypasses TypeScript type-checking, and creates duplicated logic across templates. The project already has an established pattern of registering Alpine components via `Alpine.data()` in TypeScript; the inline code should follow that same pattern.

## Acceptance Criteria

1. No Blade template contains inline `x-data="{...}"` with logic (methods, computed properties, fetch calls); only `x-data="componentName({...})"` delegation or bare `x-data` for `x-ref` scoping is permitted
2. No Blade template contains `<script>` tags except a minimal 3-line dark-mode prevention snippet in layout `<head>` sections; all other JavaScript lives in TypeScript files bundled by Vite
3. All extracted TypeScript components pass strict-mode compilation (`npx tsc --noEmit`)
4. All existing Pest tests continue to pass without modification (behavioral equivalence)
5. Vite production build succeeds without new errors
6. Simple UI toggle state (open/close, expanded/collapsed) uses a shared generic Alpine component rather than one-off inline objects
7. Blade templates pass PHP data to TypeScript components exclusively via typed config objects using `@js()` or `{{ }}` for scalars

## Technical Design

### Approach

Three extraction strategies based on inline code complexity:

1. **Generic toggle component** ; for `{ open: false }`, `{ expanded: false }`, `{ showX: false }` patterns. One reusable `toggleState` component replaces ~15 instances across the codebase.
2. **Dedicated TypeScript component** ; for medium/complex inline code with methods, fetch calls, or computed properties. Each gets its own file in `resources/js/components/` with a typed config interface.
3. **Alpine store + boot module** ; for `<script>` tag code in layouts (theme store, sidebar store, dark-mode IIFE, SW registration). Extracted to a `resources/js/boot.ts` module loaded before Alpine starts.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/js/components/toggle-state.ts` | Create | Generic reusable toggle for simple open/close/expanded state |
| `resources/js/components/sidebar-nav.ts` | Create | Sidebar navigation with submenu tracking and path matching |
| `resources/js/components/meeting-page.ts` | Create | Meeting show page: type change, status transition, attendees, tabs, prep items |
| `resources/js/components/settings-page.ts` | Create | Settings page: timezone, dashboard counts, speech service, retention |
| `resources/js/components/team-member-filter.ts` | Create | Shared team-member filter pattern (used in tasks/show, notes/show, follow-ups/show, create modals) |
| `resources/js/components/create-modal.ts` | Create | Shared create-modal pattern for task/note/meeting/follow-up |
| `resources/js/components/task-actions.ts` | Create | Task show page: create/convert follow-up, delete task |
| `resources/js/components/follow-up-page.ts` | Create | Follow-up show page: convert-to-task action |
| `resources/js/components/storage-manager.ts` | Create | Settings storage page: attachment delete with confirmation |
| `resources/js/components/bulk-select.ts` | Create | Task index: bulk selection with toggle/clear |
| `resources/js/components/system-health.ts` | Create | Health check fetcher (settings page speech service status) |
| `resources/js/components/theme-toggle.ts` | Create | Dark/light theme toggle with localStorage |
| `resources/js/components/popper-dropdown.ts` | Create | Table dropdown with Popper.js positioning |
| `resources/js/components/jira-dashboard-widget.ts` | Create | Jira widget: fetch dashboard issues on init |
| `resources/js/boot.ts` | Create | Alpine stores (theme, sidebar), dark-mode IIFE, SW registration, view-transition setup |
| `resources/js/app.ts` | Modify | Register all new Alpine.data() components, import boot module |
| 31 Blade templates | Modify | Replace inline x-data with component delegation |
| 5 Blade templates | Modify | Remove `<script>` tags, use boot module |

### Data Flow

Blade templates pass PHP data to TypeScript components via config objects:

```
Blade template                    TypeScript component
x-data="component({              export function component(config: Config) {
  endpoint: @js($route),           return {
  items: @js($items),                ...config,
  id: {{ $model->id }}               init() { ... },
})"                                   method() { ... }
                                   }
                                 }
```

### Edge Cases & Error Handling

- **Composite components** (`Object.assign(markdownEditor(), autoSaveField())`) in weekly/index must be preserved; extract only the wrapping config, not the composed components
- **`@foreach` loops with x-data** (sidebar menu items, past reflections, storage attachments) must use parameterized component calls, not static inline state
- **Dark-mode IIFE** must execute before Alpine starts to prevent flash of wrong theme; `boot.ts` must be loaded synchronously in `<head>`
- **Alpine stores** must be registered before `Alpine.start()`; `boot.ts` handles this
- **Profile page auto-submit** (`x-data x-on:change`) is a bare x-data pattern that should remain as-is (no logic to extract)

## Implementation Phases

### Phase 1: Generic toggle component and simple UI state
- **Goal:** Eliminate all simple toggle/open/close/expanded inline x-data instances with one reusable component
- **Specs:**
  - [x] `toggleState` component accepts a config object with named boolean/string fields and their defaults
  - [x] `toggleState` returns config as reactive Alpine state (direct property access)
  - [x] All simple toggle instances across 10 files replaced with `x-data="toggleState({...})"`
  - [x] Files with bare `x-data` (no logic) remain unchanged
  - [x] TypeScript compiles clean; all tests pass
- **Files:**
  - `resources/js/components/toggle-state.ts` (create)
  - `resources/js/app.ts` (modify: register)
  - `resources/views/pages/teams/member.blade.php` (showAvatarMenu, editOpen/deleteOpen, activeTab, expanded)
  - `resources/views/pages/teams/show.blade.php` (editOpen/deleteOpen, addMemberOpen)
  - `resources/views/pages/teams/index.blade.php` (addOpen)
  - `resources/views/pages/weekly/index.blade.php` (open, expanded/confirmDelete)
  - `resources/views/pages/mail.blade.php` (open in group templates)
  - `resources/views/pages/tasks/index.blade.php` (showAddGroup)
  - `resources/views/pages/profile/index.blade.php` (flash message auto-hide)
  - `resources/views/components/common/dropdown-menu.blade.php`
  - `resources/views/components/header/user-dropdown.blade.php`
  - `resources/views/components/tl/calendar-events.blade.php` (day group open/close)
  - `resources/views/partials/tasks-list.blade.php` (collapsed)
  - `resources/views/layouts/app-header.blade.php` (isApplicationMenuOpen)

### Phase 2: Shared patterns ; team-member filter and create modals
- **Goal:** Extract the duplicated team-member filter and create-modal patterns into shared components
- **Specs:**
  - [x] `teamMemberFilter` component accepts `{ memberOptions, initialTeamId? }` and exposes `filteredMemberOptions` computed getter
  - [x] `createModal` component accepts `{ memberOptions, teamOptions?, type }` config and handles team-filtered member selection
  - [x] Tasks/show, notes/show, and follow-ups/show use `teamMemberFilter` for their filter sections
  - [x] All four create modals (task, note, meeting, follow-up) use `createModal`
  - [x] Meeting create modal's extra logic (team selection for team meetings, type-dependent clearing) is handled by `createModal` config
  - [x] TypeScript compiles clean; all tests pass
- **Files:**
  - `resources/js/components/team-member-filter.ts` (create)
  - `resources/js/components/create-modal.ts` (create)
  - `resources/js/app.ts` (modify: register)
  - `resources/views/pages/tasks/show.blade.php` (team filter section)
  - `resources/views/pages/notes/show.blade.php` (team filter section)
  - `resources/views/pages/follow-ups/show.blade.php` (team filter section)
  - `resources/views/partials/task-create-modal.blade.php`
  - `resources/views/partials/note-create-modal.blade.php`
  - `resources/views/partials/meeting-create-modal.blade.php`
  - `resources/views/partials/follow-up-create-modal.blade.php`

### Phase 3: Component extractions ; common components and standalone pages
- **Goal:** Extract remaining inline code from common components, standalone page sections, and the modal component
- **Specs:**
  - [x] `themeToggle` component handles dark/light mode with localStorage persistence
  - [x] `popperDropdown` component wraps Floating UI positioning for table dropdowns
  - [x] `modalComponent` component handles open state, body overflow toggle, and escape key (parameterized by initial open state)
  - [x] `bulkSelect` component handles task index bulk selection with toggle/clear methods
  - [x] `storageManager` component handles attachment deletion with confirmation modal and fetch DELETE
  - [x] `jiraDashboardWidget` component fetches and displays Jira dashboard issues
  - [x] `taskActions` component handles create-follow-up, convert-to-follow-up, and delete-task actions
  - [x] `followUpPage` component handles convert-to-task action
  - [x] TypeScript compiles clean; all tests pass
- **Files:**
  - `resources/js/components/theme-toggle.ts` (create)
  - `resources/js/components/popper-dropdown.ts` (create)
  - `resources/js/components/modal-component.ts` (create)
  - `resources/js/components/bulk-select.ts` (create)
  - `resources/js/components/storage-manager.ts` (create)
  - `resources/js/components/jira-dashboard-widget.ts` (create)
  - `resources/js/components/task-actions.ts` (create)
  - `resources/js/components/follow-up-page.ts` (create)
  - `resources/js/app.ts` (modify: register)
  - `resources/views/components/common/theme-toggle.blade.php`
  - `resources/views/components/common/table-dropdown.blade.php`
  - `resources/views/components/ui/modal.blade.php`
  - `resources/views/components/tl/jira-widget.blade.php`
  - `resources/views/pages/tasks/index.blade.php`
  - `resources/views/pages/tasks/show.blade.php`
  - `resources/views/pages/follow-ups/show.blade.php`
  - `resources/views/pages/settings/storage.blade.php`

### Phase 4: Complex pages ; meetings and settings
- **Goal:** Extract the two heaviest inline blocks into dedicated TypeScript components
- **Specs:**
  - [x] Meeting page components: `meetingTypeChanger` (type change + warning modal), `meetingStatusTransition` (PATCH), `meetingAttendees` (add/remove members and teams, sync), `meetingTabs` (URL params), `meetingPrepItems` (CRUD)
  - [x] Settings page components: `settingsTimezone`, `settingsDashboardWidgets`, `settingsSpeechService` (with connection test), `settingsSystemHealth`, `settingsDataPruning`
  - [x] All `@js()` and `{{ }}` Blade data passed via typed config objects
  - [x] Event dispatch (`meeting-type-changed`, `meeting-clear-attendees`) preserved between composed components
  - [x] TypeScript compiles clean; all tests pass
- **Files:**
  - `resources/js/components/meeting-type-changer.ts` (create)
  - `resources/js/components/meeting-status-transition.ts` (create)
  - `resources/js/components/meeting-attendees.ts` (create)
  - `resources/js/components/meeting-tabs.ts` (create)
  - `resources/js/components/meeting-prep-items.ts` (create)
  - `resources/js/components/settings-timezone.ts` (create)
  - `resources/js/components/settings-dashboard-widgets.ts` (create)
  - `resources/js/components/settings-speech-service.ts` (create)
  - `resources/js/components/settings-system-health.ts` (create)
  - `resources/js/components/settings-data-pruning.ts` (create)
  - `resources/js/app.ts` (modify: register 10 components)
  - `resources/views/pages/meetings/show.blade.php`
  - `resources/views/pages/settings/index.blade.php`

### Phase 5: Layout scripts and Alpine stores
- **Goal:** Remove all `<script>` tags from layout and auth templates
- **Specs:**
  - [x] `boot.ts` module registers `Alpine.store('theme')` and `Alpine.store('sidebar')` before Alpine starts
  - [x] Dark-mode IIFE reduced to a minimal 3-line inline `<script>` in `<head>` (sole permitted exception); all other script content moved to TypeScript
  - [x] Service worker registration moved to boot module
  - [x] View-transition and click-origin capture moved to boot module
  - [x] `sidebarNav` component replaces the sidebar's inline x-data with path-matching and submenu state
  - [x] Two-factor challenge page uses `toggleState` for mode switching
  - [x] No `<script>` tags remain in any Blade template except the minimal dark-mode prevention snippet in layout `<head>` sections
  - [x] TypeScript compiles clean; all tests pass; Vite build succeeds
- **Files:**
  - `resources/js/boot.ts` (create)
  - `resources/js/components/sidebar-nav.ts` (create)
  - `resources/js/app.ts` (modify: import boot, register sidebarNav)
  - `resources/views/layouts/app.blade.php`
  - `resources/views/layouts/fullscreen-layout.blade.php`
  - `resources/views/layouts/sidebar.blade.php`
  - `resources/views/auth/login.blade.php`
  - `resources/views/auth/two-factor-challenge.blade.php`
  - `resources/views/errors/layout.blade.php`

### Phase 6: Verification and cleanup
- **Goal:** Final verification that no inline JS remains and everything works end-to-end
- **Specs:**
  - [x] `grep -r 'x-data="{' resources/views/` returns only `mail.blade.php` (dynamic `group.defaultOpen` exception)
  - [x] `grep -r '<script' resources/views/` returns only the 5 dark-mode IIFE scripts in layout/auth/error `<head>` sections
  - [x] Full Pest test suite passes (`php artisan test --parallel`) ; 2270 tests, 5060 assertions
  - [x] TypeScript strict-mode compilation passes (`npx tsc --noEmit`)
  - [x] Vite production build passes (`npm run build`)
  - [ ] Manual smoke test: sidebar navigation, theme toggle, meeting page attendees, settings page, task CRUD, create modals
- **Files:**
  - `resources/js/components/user-dropdown.ts` (create)
  - `resources/js/components/app-header-menu.ts` (create)
  - `resources/js/components/recording-delete.ts` (create)
  - `resources/js/components/flash-message.ts` (create)
  - `resources/js/app.ts` (modify: register 4 new components)
  - `resources/js/components/filter-manager.ts` (modify: add filtersOpen)
  - `resources/views/components/tl/analytics-widget.blade.php` (modify: use toggleState)
  - `resources/views/pages/about/index.blade.php` (modify: use toggleState)
  - `resources/views/components/header/user-dropdown.blade.php` (modify: use userDropdown)
  - `resources/views/layouts/app-header.blade.php` (modify: use appHeaderMenu)
  - `resources/views/components/tl/filter-bar.blade.php` (modify: remove spread composition)
  - `resources/views/pages/meetings/show.blade.php` (modify: use recordingDelete)
  - `resources/views/pages/profile/index.blade.php` (modify: use flashMessage)
  - `resources/views/auth/login.blade.php` (modify: remove duplicate script tag)

## Parallelization

**Strategy:** Sequential

All phases execute sequentially (1 → 2 → 3 → 4 → 5 → 6) to minimize merge conflicts and allow each phase to build on the previous one's patterns.

## Out of Scope

- Refactoring existing TypeScript components already registered in app.ts
- Changing the visual appearance or behavior of any component
- Adding new features or functionality
- Migrating from Alpine.js to another framework
- Performance optimization beyond removing inline JS (e.g., lazy loading, code splitting)
- Updating tests for the extraction itself (behavioral equivalence means existing tests validate correctness)

## Resolved Decisions

1. **Dark-mode IIFE:** Keep a minimal 3-line inline `<script>` in `<head>` for theme class toggling. This is the sole exception to the no-script-tags rule. All other JS moves to TypeScript.
2. **Sidebar path matching:** Pass menu structure from `MenuHelper` as JSON via `@json()` in the Blade template. No API endpoint needed.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
