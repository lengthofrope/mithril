# Todo Plan — Issues Found During Playwright Walkthrough

Tested on 2026-03-07 with seeded data, navigating all pages at 1440x900 viewport.

---

## Critical: Broken Core Functionality

### 1. JS error on every page: `Cannot read properties of null (reading 'classList')`
- **Where:** Every single page (login, dashboard, tasks, follow-ups, teams, notes, bilas, weekly, settings)
- **Error:** `TypeError: Cannot read properties of null (reading 'classList')` at inline `<script>` around line 91
- **Impact:** Likely a TailAdmin dark-mode or sidebar script referencing a missing DOM element

### 2. Quick-add task form (dashboard) does not save
- **Where:** Dashboard, "Quick-add task" section
- **What:** Filling in a title + priority and clicking "Add task" reloads the page but no task is created in the database
- **Expected:** Task should be created via AJAX (auto-save principle) and counter should update

### 3. New task form (tasks page) does not save
- **Where:** Tasks page, "New task" inline form
- **What:** Clicking "Add" redirects to the dashboard instead of creating the task. Task is not persisted.
- **Expected:** Task should be created via AJAX and appear in the list

### 4. Follow-up action buttons navigate to JSON responses instead of using AJAX
- **Where:** Dashboard follow-up cards, Follow-ups page
- **What:** Clicking "Done", "Snooze", or "Convert to task" navigates the browser to the endpoint URL (e.g., `/follow-ups/1/done`) and renders raw JSON `{"success":true}`
- **Expected:** These should be AJAX calls (fetch) that update the UI in-place without navigation

### 5. Task filters replace page content with dashboard HTML
- **Where:** Tasks page, filter dropdowns (Team, Member, Category, Status, Priority, Group)
- **What:** Selecting a filter (e.g., Team = "Team Beta") replaces the task list area with the full dashboard page HTML (including sidebar, header, counters)
- **Expected:** Filter should either reload the tasks page with query params, or fetch a Blade partial and replace only the task list

---

## High: Wrong Links (API URLs instead of web routes)

### 6. Dashboard counter cards link to API endpoints
- **Where:** Dashboard counter cards (Open tasks, Urgent tasks, Overdue follow-ups, Bilas this week)
- **Links:** `/api/v1/tasks`, `/api/v1/tasks?priority=urgent`, `/api/v1/follow-ups`, `/api/v1/bilas`
- **Expected:** `/tasks`, `/tasks?priority=urgent`, `/follow-ups`, `/bilas`

### 7. Task "View task" links point to API endpoints
- **Where:** Tasks list view, Kanban view, Team member profile (tasks tab)
- **Links:** `/api/v1/tasks/{id}`
- **Expected:** There is no task detail web page — either create one, or make task cards inline-editable. At minimum, clicking should not navigate to a JSON API endpoint.

### 8. Kanban "List view" link points to API endpoint
- **Where:** Kanban page, "Switch to list view" link
- **Link:** `/api/v1/tasks`
- **Expected:** `/tasks`

### 9. Team cards link to API endpoints
- **Where:** Teams index page
- **Links:** `/api/v1/teams/1`, `/api/v1/teams/2`
- **Expected:** `/teams/1`, `/teams/2`

### 10. Bila links point to API endpoints
- **Where:** Bilas index page (upcoming and past bilas)
- **Links:** `/api/v1/bilas/{id}`
- **Expected:** `/bilas/{id}`

### 11. Team member profile — team name links to API endpoint
- **Where:** Team member profile page (e.g., `/teams/member/1`)
- **Link:** "Team Alpha" links to `/api/v1/teams/1`
- **Expected:** `/teams/1`

---

## Medium: Data & Display Issues

### 12. Tasks list view: ungrouped tasks are not shown
- **Where:** Tasks list view (`/tasks`)
- **What:** Only tasks assigned to a group (Q1 2026 Sprint, Infrastructure Upgrade) are displayed. 7 ungrouped tasks (e.g., "Fix login page redirect bug", "Write API documentation for v2", "Review and merge open PRs", private task) are completely missing.
- **Expected:** An "Ungrouped" section (or similar) should display tasks without a `task_group_id`

### 13. Teams index shows "0 open task(s)" for all teams
- **Where:** Teams index page (`/teams`)
- **What:** Both Team Alpha and Team Beta show "0 open task(s)" despite having multiple open tasks in the database
- **Expected:** Should show the correct count of open (non-done) tasks per team

### 14. Team member links use wrong URL format
- **Where:** Team show page (`/teams/1`)
- **Links:** `/teams/member/1?1`, `/teams/member/1?2`, `/teams/member/1?3`, `/teams/member/1?4`
- **Expected:** `/teams/member/1`, `/teams/member/2`, `/teams/member/3`, `/teams/member/4`
- **Effect:** All member links go to the first member (ID from the URL path), query param is ignored

### 15. Weekly reflection: dates show raw timestamps
- **Where:** Weekly reflection page (`/weekly`)
- **What:** Heading shows "This week's summary 2026-03-02 00:00:00 – 2026-03-08 00:00:00"
- **Expected:** Formatted dates like "2 Mar 2026 – 8 Mar 2026" or "Week 10, 2026"

### 16. Notes page: markdown content shown as raw text in previews
- **Where:** Notes index page (`/notes`)
- **What:** Note preview snippets show raw markdown syntax (e.g., `# Working Agreements - Daily standups at 09:15`)
- **Expected:** Either strip markdown for plain-text preview, or render as HTML

---

## Low: UI/UX Polish Issues

### 17. Header shows "Musharof" instead of logged-in user name
- **Where:** Top-right header area on all pages (desktop view)
- **What:** User dropdown shows "Musharof" with TailAdmin default avatar
- **Expected:** Should show the authenticated user's name ("Team Lead") and initials/avatar

### 18. Light mode is the default instead of dark mode
- **Where:** Entire app
- **What:** App loads in light mode
- **Expected:** Per plan: "Donker thema als standaard" — dark mode should be the default

### 19. Header search bar ("Search or type command...") appears non-functional
- **Where:** Top header bar (desktop view)
- **What:** TailAdmin's command palette search bar is present but likely not wired to the app's search functionality
- **Expected:** Either wire it to the global search API (`/api/v1/search`) or remove it to avoid confusion

### 20. Sidebar navigation labels not visible on mobile/collapsed state
- **Where:** Sidebar on narrow viewports
- **What:** Sidebar collapses to icon-only with no tooltips — navigation items are unlabelled icons
- **Expected:** Either show tooltips on hover, or keep labels visible

### 21. No task detail/edit page exists
- **Where:** N/A (missing route)
- **What:** There is no web route for viewing/editing a single task. Task cards have "view" links but they point to the API.
- **Expected:** Either create a task detail page, or make tasks inline-editable in the list/kanban views

### 22. Keyboard shortcuts not implemented
- **Where:** Entire app
- **What:** Plan specifies `N` (new task), `Ctrl+K` or `/` (global search), `1-6` (section navigation)
- **Status:** Not verified as working — no evidence of keyboard event listeners in the tested pages

---

## Missing Features

### 23. No interface for managing task groups
- **Where:** Settings page / Tasks section
- **What:** The `TaskGroup` model exists (name, description, color, sort_order) and tasks can be assigned to groups, but there is no UI to create, edit, rename, recolor, reorder, or delete task groups
- **Expected:** A settings sub-page (`/settings/tasks`) with full CRUD for task groups (similar to how categories are managed: inline add form, drag-to-reorder, delete with confirmation)

### 24. Move task category management to settings sub-page
- **Where:** Settings page (`/settings`)
- **What:** Task category management currently lives on the main settings page alongside appearance, push notifications, and data export/import. It should be moved to a dedicated settings sub-page together with task group management.
- **Expected:** A `/settings/tasks` sub-page containing both "Task categories" (existing, moved from main settings) and "Task groups" (new). The main settings page gets a link/card pointing to this sub-page.

---

## Summary

| Priority | Count | Categories |
|----------|-------|------------|
| Critical | 5 | Forms don't save, actions navigate to JSON, filters broken, JS error on every page |
| High | 6 | All internal links point to API endpoints instead of web routes |
| Medium | 5 | Missing ungrouped tasks, wrong counts, broken member links, raw dates/markdown |
| Low | 6 | Theming, user name, search bar, tooltips, missing detail page, keyboard shortcuts |
| Feature  | 2 | Task group CRUD, settings sub-page for task configuration |
