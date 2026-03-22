## ADR-004: Analytics dashboard implementation

**Date:** 2026-03-08
**Phase:** Analytics
**Tags:** frontend, backend, apexcharts, analytics, widgets
**Status:** Accepted

### Context

The team lead dashboard needed configurable analytics charts to visualize task and follow-up distributions. The plan specified ApexCharts, per-user widget configuration, drag-and-drop reordering, and dual placement (analytics page + dashboard).

Key design questions:
1. How to handle two independent sort orders (analytics vs dashboard) when the existing `HasSortOrder` trait only supports one.
2. How to extend the generic `ReorderController` without breaking existing models.

### Decision

1. **Dual sort order**: `AnalyticsWidget` does NOT use the `HasSortOrder` trait. Instead, it has two columns (`sort_order_analytics`, `sort_order_dashboard`) and a static `reorderForContext(array $items, string $context)` method that matches the trait's API but accepts a context parameter.

2. **ReorderController extension**: Added a `sort_field` parameter to `ReorderRequest` (nullable, whitelisted values). When `model_type` is `analytics_widget`, the controller requires `sort_field` and delegates to `reorderForContext()`. All other models continue to work unchanged without `sort_field`.

3. **Data aggregation**: A stateless `AnalyticsDataService` resolves each `DataSource` enum case to a `ChartData` DTO. All queries are automatically user-scoped via `BelongsToUser` global scopes.

4. **Frontend**: Three Alpine.js components (`analyticsChart`, `analyticsBoard`, `widgetConfigurator`) with ApexCharts for rendering. Widget data is fetched on-demand via AJAX, not at page load.

5. **Dark mode**: ApexCharts theme mode is synced via a MutationObserver on the `<html>` element's class attribute.

### Consequences

- New migration: `analytics_widgets` table with FK to users.
- New npm dependency: `apexcharts` (~180KB gzipped in bundle). Vite chunk size warning appears but is acceptable for a PWA.
- ReorderRequest now accepts an optional `sort_field` — backward compatible.
- DashboardController extended to pass `$dashboardWidgets` to the view.
- Navigation sidebar gains an "Analytics" menu item.

### Follow-ups / open questions

- Consider code-splitting ApexCharts via dynamic `import()` to reduce initial bundle size.
- Phase 2 data sources (trends, frequency) will need line/area chart support.
- Server-side caching (5 min TTL) for widget data if widget count grows significantly.
