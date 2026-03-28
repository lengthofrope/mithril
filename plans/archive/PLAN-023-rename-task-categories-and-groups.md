# Rename Task Categories and Groups

**Created:** 2026-03-27
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

The task management settings page supports creating, deleting, and reordering categories and groups, but does not allow renaming them. Users must delete and re-create to change a name, which also breaks any existing task associations.

## Acceptance Criteria

1. Users can rename a task category inline on the settings page; the change auto-saves via the existing auto-save API endpoint
2. Users can rename a task group inline on the settings page; the change auto-saves via the existing auto-save API endpoint
3. Users can change a task group's color inline on the settings page; the change auto-saves
4. Validation prevents empty names and enforces the same constraints as creation (max 255 chars; unique-per-user for categories)
5. Save status feedback (saving/saved/error) is visible per item
6. Renaming works correctly within the sortable container (drag-and-drop still functions)
7. All new functionality is covered by Pest tests

## Technical Design

### Approach

Replace the static `<span>` name display in each sortable item with an inline text input bound to a small Alpine component that auto-saves via `POST /api/v1/auto-save`. The backend `AutoSaveController` already maps both `task_category` and `task_group`, and `name` is fillable on both models. **No backend route or controller changes are needed for basic rename.**

However, the `AutoSaveController` currently does no validation beyond checking fillable fields. For category name uniqueness, we need server-side validation on the auto-save path. We will add per-model validation rules to the `AutoSaveController` so that field-specific constraints (like unique name per user) are enforced.

For the color picker on task groups, we replace the static color dot with an `<input type="color">` that auto-saves the `color` field.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/views/pages/settings/tasks.blade.php` | Modify | Replace static name spans with inline editable inputs; add color picker for groups |
| `app/Http/Controllers/Api/AutoSaveController.php` | Modify | Add per-model validation rules for field-level constraints |
| `resources/js/components/inline-auto-save.ts` | Create | Small Alpine component for inline auto-save using the generic endpoint |
| `resources/js/app.ts` | Modify | Register the new Alpine component |
| `tests/Feature/Http/Controllers/Api/AutoSaveControllerTest.php` | Modify | Add tests for validation rules on category/group rename |
| `tests/Feature/Http/Controllers/Web/SettingsControllerTest.php` | Modify | Add tests for the updated settings page rendering |

### Data Flow

1. User clicks a category/group name; it is already an input (no click-to-edit toggle needed)
2. User types a new name
3. After 500ms debounce, Alpine posts `{ model: "task_category", id: 123, field: "name", value: "New Name" }` to `POST /api/v1/auto-save`
4. `AutoSaveController` validates field constraints, updates the model, returns success/error
5. Alpine updates the save status indicator

### Edge Cases & Error Handling

- Empty name: validation rejects; error status shown inline
- Duplicate category name (per user): validation rejects; error status shown inline
- Name exceeding 255 chars: validation rejects
- Concurrent rename during drag: SortableJS uses `data-id`, not name text; no conflict
- Color value invalid: auto-save validates hex format

## Implementation Phases

### Phase 1: Backend Validation + Frontend Inline Editing
- **Goal:** Enable inline rename and color editing with auto-save and validation
- **Specs:**
  - [x] `AutoSaveController` applies per-model validation rules before saving (unique name for categories, max:255 for both, hex regex for group color)
  - [x] New `inlineAutoSave` Alpine component posts to the generic auto-save endpoint with `{ model, id, field, value }` and exposes `status` for feedback
  - [x] Category names render as inline text inputs with auto-save, showing save status
  - [x] Group names render as inline text inputs with auto-save, showing save status
  - [x] Group color dots become `<input type="color">` with auto-save
  - [x] Drag-and-drop reordering continues to function with editable inputs
  - [x] Pest tests: auto-save validates unique category name per user, rejects empty names, rejects invalid color, and successfully renames both models
  - [x] Pest tests: settings page renders with editable inputs
- **Files:** `app/Http/Controllers/Api/AutoSaveController.php`, `resources/views/pages/settings/tasks.blade.php`, `resources/js/components/inline-auto-save.ts`, `resources/js/app.ts`, `tests/`

## Parallelization

**Strategy:** Sequential

Single phase, single session. No parallelization needed.

## Out of Scope

- Renaming via API resource endpoints (only the auto-save endpoint is used)
- Undo/revert functionality
- Bulk rename
- Description editing for task groups (the field exists but is not exposed on this page)

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
