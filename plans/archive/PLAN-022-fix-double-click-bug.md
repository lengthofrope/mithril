# Fix: Double-Click Registration Bug

**Created:** 2026-03-27
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

Users must click many interactive elements twice before the click registers. This is caused by two independent issues: (1) CSS View Transitions API creating overlay pseudo-elements that intercept clicks during navigation animations, and (2) `innerHTML` replacement in `refreshable` and `filterManager` components destroying Alpine.js state and event listeners after every mutating API call.

## Acceptance Criteria

1. Single clicks register immediately on all interactive elements (dropdowns, toggles, inline-selects, buttons) on fresh page loads
2. Single clicks continue to work after performing a mutating action (e.g., changing a task status, priority, or any auto-save field)
3. Refreshable sections (dashboard widgets, task lists, follow-up lists, activity feeds) update their content without breaking Alpine.js interactivity inside them
4. Filter result updates preserve Alpine.js component state in the results container
5. No visual regressions in page layout or component behavior
6. All existing tests pass; TypeScript compiles clean; Vite build succeeds

## Technical Design

### Approach

**Fix 1: Remove CSS View Transitions.** Delete the `@view-transition { navigation: auto; }` CSS rule, the `::view-transition-old/new` animation styles, the `pagereveal` listener, and the global click-coordinate tracker. This removes the overlay pseudo-elements that steal clicks during transitions.

**Fix 2: Replace `innerHTML` with Alpine morph.** Install `@alpinejs/morph` and register it as an Alpine plugin. Replace `target.innerHTML = html` in `refreshable.ts` with `Alpine.morph(target, html)`, which diffs the DOM and patches only what changed, preserving Alpine component state, reactive bindings, and event listeners. Apply the same change to `filter-manager.ts`.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `resources/css/app.css` | Modify | Remove `@view-transition` block, transition animations, and keyframes |
| `resources/views/layouts/app.blade.php` | Modify | Remove `pagereveal` listener (L102-110), global click-coordinate tracker (L165-178), `link rel="expect"` (L21), font preload comments mentioning view transitions (L15) |
| `resources/js/app.ts` | Modify | Import and register `@alpinejs/morph` plugin |
| `resources/js/components/refreshable.ts` | Modify | Replace `target.innerHTML = html` with `Alpine.morph()` |
| `resources/js/components/filter-manager.ts` | Modify | Replace `resultsContainer.innerHTML = html` with `Alpine.morph()` |
| `package.json` | Modify | Add `@alpinejs/morph` dependency |

### Edge Cases & Error Handling

- **Morph with empty response:** If the server returns empty HTML, `Alpine.morph()` should clear the container the same way `innerHTML = ''` would. Verify this works.
- **Morph with completely different structure:** When filter results change dramatically (different number of items), morph should handle full replacement gracefully.
- **ETag 304 responses:** `refreshable.ts` already skips updates on 304; this remains unchanged.

## Implementation Phases

### Phase 1: Remove View Transitions
- **Goal:** Eliminate the click-stealing overlay pseudo-elements
- **Specs:**
  - [x] Remove `@view-transition { navigation: auto; }` from `resources/css/app.css`
  - [x] Remove `::view-transition-old(root)` and `::view-transition-new(root)` animation styles
  - [x] Remove `circle-collapse` and `blur-out` keyframes
  - [x] Remove the `pagereveal` event listener script block from `app.blade.php`
  - [x] Remove the global click-coordinate `sessionStorage` script block from `app.blade.php`
  - [x] Remove the `<link rel="expect" blocking="render">` tag
  - [x] Clean up the font preload comment referencing view transitions
- **Files:** `resources/css/app.css`, `resources/views/layouts/app.blade.php`

### Phase 2: Alpine Morph Integration
- **Goal:** Preserve Alpine component state during content refreshes
- **Specs:**
  - [x] Install `@alpinejs/morph` npm package
  - [x] Register morph plugin in `resources/js/app.ts` before `Alpine.start()`
  - [x] Replace `target.innerHTML = html` in `refreshable.ts` with Alpine morph, wrapping the new HTML in a temporary element to extract child content for morphing
  - [x] Replace `resultsContainer.innerHTML = html` in `filter-manager.ts` with the same Alpine morph approach
  - [x] Verify TypeScript types compile clean (`npx tsc --noEmit`)
  - [x] Verify Vite build succeeds (`npm run build`)
  - [x] Run full test suite to confirm no regressions
- **Files:** `package.json`, `resources/js/app.ts`, `resources/js/components/refreshable.ts`, `resources/js/components/filter-manager.ts`

## Parallelization

**Strategy:** Sequential

Phase 1 and 2 are independent fixes but share `app.blade.php` context. Both are small enough to complete in a single session sequentially. No parallelization needed.

## Out of Scope

- Fixing the backdrop `:class` vs `x-show` issue (minor, separate concern)
- Adding topic filtering to the generic `data-changed` dispatch on `api-client.ts:56` (optimization, not a bug fix)
- Re-implementing view transitions with an opt-in approach (can be done later if desired)

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
