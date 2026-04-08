# Plan: Keyboard Navigation & Shortcut Help

**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Context

Mithril already has basic keyboard navigation (numbers 1-7 for page nav, `n` for tasks) in `resources/js/components/keyboard-shortcuts.ts`. The user wants GitHub-style chord shortcuts (`c` then `t/f/n/m`) to quickly create entities, plus a floating `?` help button that shows all available shortcuts.

## Design

### 1. Chord-based create shortcuts

Extend `keyboard-shortcuts.ts` with a chord state machine:
- Press `c` to enter "create mode" (500ms timeout)
- Then press `t` (task), `f` (follow-up), `n` (note), or `m` (meeting)
- If the matching create modal is present on the current page, open it via a custom DOM event
- If the modal is not on the page, navigate to `/{entity-index}?create=1`

**Additional shortcuts:**
- `?` (Shift+/) opens the help overlay
- `/` focuses the global search input (standard convention)

### 2. Create modal event bridge

Each create modal partial gets a `data-create-modal="{type}"` attribute and listens for a window-level `create-entity` custom event to set `addOpen = true`.

On pages that receive `?create=1` query param, auto-open the modal on mount.

### 3. Help overlay component

New Alpine component `shortcutHelp()` in `resources/js/components/shortcut-help.ts`:
- Renders a centered overlay listing all shortcuts grouped by category (Navigation, Create, General)
- Opens on `?` key or click of the floating button
- Closes on `Escape` or click outside

New Blade component `resources/views/components/tl/shortcut-help.blade.php` for the overlay markup + floating button.

### 4. Floating help button

Small circular `?` button fixed bottom-right, placed in `resources/views/layouts/app.blade.php`. Uses the Rivendell brand colors. Hidden on mobile (keyboard shortcuts aren't useful there).

## Files to modify

| File | Change |
|------|--------|
| `resources/js/components/keyboard-shortcuts.ts` | Add chord logic, create dispatch, `/` for search, `?` for help |
| `resources/js/components/shortcut-help.ts` | **New** ; Alpine component for help overlay state |
| `resources/js/app.ts` | Register `shortcutHelp` component |
| `resources/views/components/tl/shortcut-help.blade.php` | **New** ; overlay markup + floating button |
| `resources/views/layouts/app.blade.php` | Include `<x-tl.shortcut-help />` |
| `resources/views/partials/task-create-modal.blade.php` | Add `data-create-modal="task"`, `@create-entity.window` listener, `?create=1` auto-open |
| `resources/views/partials/note-create-modal.blade.php` | Same pattern |
| `resources/views/partials/follow-up-create-modal.blade.php` | Same pattern |
| `resources/views/partials/meeting-create-modal.blade.php` | Same pattern |
| `resources/js/components/global-search.ts` | Expose a method or ref for focus from keyboard shortcuts |
| `tests/` | Pest feature tests for `?create=1` auto-open; TypeScript type-checks |

## Shortcut reference (complete)

| Key | Action |
|-----|--------|
| `1`-`7` | Navigate to pages (existing) |
| `/` | Focus global search |
| `c` then `t` | Create task |
| `c` then `f` | Create follow-up |
| `c` then `n` | Create note |
| `c` then `m` | Create meeting |
| `?` | Open shortcut help |
| `Escape` | Close any open overlay |

## Implementation approach

### Phase 1: Keyboard shortcuts engine + help overlay
1. Write tests for chord logic (TypeScript compile check) and `?create=1` behavior (Pest feature test)
2. Refactor `keyboard-shortcuts.ts` to support chord sequences and new shortcuts
3. Create `shortcut-help.ts` Alpine component
4. Create `shortcut-help.blade.php` with overlay + FAB
5. Register in `app.ts`, include in layout

### Phase 2: Create modal integration
1. Write tests for create modal auto-open via query param
2. Add `data-create-modal` + event listener to all four create modal partials
3. Add `?create=1` auto-open logic (read URL param on Alpine `init`)

## Verification

1. `npx tsc --noEmit` ; TypeScript must compile clean
2. `php artisan test --parallel` ; all tests pass
3. `npm run build` ; Vite build succeeds
4. Manual: press `c` then `t` on dashboard ; task modal opens
5. Manual: press `c` then `t` on settings page ; navigates to `/tasks?create=1` and modal auto-opens
6. Manual: press `?` ; help overlay appears
7. Manual: click floating `?` button ; help overlay appears
8. Manual: press `/` ; search input focuses
