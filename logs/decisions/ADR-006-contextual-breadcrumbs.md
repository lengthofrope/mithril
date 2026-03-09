## ADR-006: Contextual hierarchical breadcrumbs

**Date:** 2026-03-09
**Phase:** UI
**Tags:** frontend, backend, navigation, breadcrumbs
**Status:** Accepted

### Context

The existing breadcrumb component only rendered a flat two-level trail: `Home > Page Title`. This gave no indication of where in the application hierarchy the user was, especially for nested entities like tasks assigned to team members, bilas linked to members, or notes associated with teams.

The user requested contextual breadcrumbs that follow entity relationships, e.g.:
- `Home > Tasks > Task Name` (unassigned task)
- `Home > Teams > Team Name > Member Name > Task Name` (assigned task)
- `Home > Teams > Team Name > Member Name > Bila — Member Name` (bila)

Alternatives considered:
1. **Route-based breadcrumbs** (e.g. Spatie/laravel-breadcrumbs package) — rejected because routes are flat, not nested. The hierarchy lives in entity relationships.
2. **View Composer approach** — rejected because breadcrumbs need entity-specific context that's only available in controllers.
3. **Service class called from controllers** — chosen for explicit control and testability.

### Decision

A new `App\Services\BreadcrumbBuilder` service class builds contextual breadcrumb arrays. Each crumb is `['label' => string, 'url' => string|null]` where the last crumb always has `null` url.

Builder methods:
- `forPage(label, url)` — simple index pages
- `addCrumb(label, url)` — append to chain (for sub-pages like Settings > Task Settings)
- `forTask(Task)` — follows team/member relationships
- `forTeam(Team)` — Teams > Team Name
- `forTeamMember(TeamMember)` — Teams > Team > Member
- `forBila(Bila)` — Teams > Team > Member > Bila
- `forNote(Note)` — follows team/member relationships

The `PageBreadcrumb` Blade component accepts an optional `items` array. When not provided, it falls back to the old `pageTitle` prop for backward compatibility — so simple index pages need no changes.

Controllers that show entity detail pages pass `breadcrumbs` to their views. The eager-loading in these controllers was extended to include `.team` on nested relationships (e.g. `teamMember.team`).

### Consequences

- **New file:** `app/Services/BreadcrumbBuilder.php`
- **New test file:** `tests/Unit/Services/BreadcrumbBuilderTest.php` (14 tests)
- **Modified:** `PageBreadcrumb` component (PHP + Blade) now supports `items` array
- **Modified controllers:** `TaskPageController`, `TeamPageController`, `BilaPageController`, `NotePageController`, `SettingsController`
- **Modified views:** 7 Blade views updated from `pageTitle` to `:items="$breadcrumbs"`
- **Backward compatible:** Simple pages (Dashboard, index pages, etc.) still use `pageTitle` prop unchanged
- **No migrations needed**

### Follow-ups / open questions

- Follow-up pages could potentially also show team member context when filtered by member
- If new entity types are added, corresponding `forX()` methods should be added to `BreadcrumbBuilder`
