## ADR-007: Live UI refresh via central event dispatch

**Date:** 2026-03-10
**Phase:** UI
**Tags:** frontend, backend, alpine, api-client, real-time
**Status:** Accepted

### Context

When a user changes a task status, priority, or performs any mutating action via XHR, only the component that triggered the change updates. Other elements on the same page — counter cards, analytics charts, section counts — remain stale until a full page reload.

Two approaches were considered:

1. **Central event from `apiClient`** — Every successful mutation (POST/PATCH/DELETE) dispatches a `data-changed` custom DOM event on `window`. Any component that needs to stay current listens to this event and refreshes itself. Simple, automatic, catches all mutations including future ones.

2. **Manual dispatch per component** — Each component explicitly dispatches an event after a successful save. More precise but requires touching every component and is easy to forget when adding new ones.

### Decision

Option 1 was chosen: the `apiClient` dispatches a `data-changed` CustomEvent on `window` after every successful POST, PATCH, or DELETE response. Components opt in to live updates by listening to this event.

Implementation details:

- **`apiClient`** (`resources/js/utils/api-client.ts`): After `executeRequest` resolves successfully for POST/PATCH/DELETE, dispatch `window.dispatchEvent(new CustomEvent('data-changed'))`.
- **New API endpoint** `GET /api/v1/counters`: Returns the same counter stats as `DashboardController::buildStats()` but as JSON. Extracted to a reusable service or shared method.
- **New `liveCounter` Alpine component**: Fetches its count from the counters endpoint on `data-changed` (debounced to avoid flooding). Replaces the static `<x-tl.counter-card>` rendering on the dashboard.
- **`analyticsChart`**: Listens to `data-changed` and re-fetches chart data (debounced), then updates the existing ApexCharts instance.
- **Debouncing**: All listeners debounce by 1000ms to coalesce rapid successive mutations into a single refresh.

### Consequences

- New `DashboardCounterController` (or method on existing controller) exposing `/api/v1/counters`.
- `DashboardController::buildStats()` logic extracted so both the page render and the API endpoint share it.
- All existing mutation-triggering components automatically participate without any changes to their code.
- Minor overhead: one extra GET per mutation batch (debounced). Acceptable for a single-user dashboard.
- Future components that display aggregate data only need to listen to `data-changed` — no coordination required.

### Follow-ups / open questions

- Consider adding a payload to the `data-changed` event (e.g. `{ model: 'task' }`) if selective refresh becomes necessary for performance.
- Section counts in the "today" panels (e.g. "Tasks due today: 3") could also be wired to live-update in a future iteration.
