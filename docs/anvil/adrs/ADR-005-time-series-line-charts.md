## ADR-005: Time-series line charts via daily analytics snapshots

**Date:** 2026-03-08
**Phase:** Analytics
**Tags:** backend, frontend, analytics, snapshots, line-charts
**Status:** Accepted

### Context

The analytics dashboard (ADR-004) initially only supported point-in-time aggregations (bar, donut, horizontal bar charts). The user requested the ability to see trends over time — how tasks and follow-ups are opened, closed, and progressed — using line charts with configurable time ranges (7d, 30d, 90d).

Live queries cannot produce historical data since the current database only stores current state, not state changes over time. Alternatives considered:

1. **Event sourcing / activity log** — would require retroactively logging all state changes and complex replay logic. Over-engineered for this use case.
2. **Daily snapshots** — simple, predictable, and sufficient for trend charts at day-level granularity. Chosen approach.
3. **Derived from `created_at`/`updated_at` timestamps** — fragile, cannot accurately reconstruct status counts at arbitrary past dates.

### Decision

A scheduled Artisan command (`analytics:snapshot`) runs daily at 00:15 and records 9 metrics per user into a new `analytics_snapshots` table:

- `tasks_status_open`, `tasks_status_in_progress`, `tasks_status_waiting`, `tasks_status_done`, `tasks_total`
- `follow_ups_status_open`, `follow_ups_status_snoozed`, `follow_ups_status_done`, `follow_ups_total`

The command uses `upsert` with a unique index on `(user_id, metric, snapshot_date)` for idempotency — safe to re-run multiple times per day.

A new `AnalyticsSnapshotService` queries these snapshots and returns `TimeSeriesChartData` DTOs (distinct from the existing `ChartData` DTO) with named series suitable for multi-line charts. Three data source methods are supported:

- **Tasks Over Time** — 4 series (Open, In Progress, Waiting, Done)
- **Task Activity** — 2 series (Created, Completed) derived as daily deltas from absolute totals
- **Follow-ups Over Time** — 3 series (Open, Snoozed, Done)

The `DataSource` enum gains three new time-series cases. Each returns `[ChartType::Line]` as its only allowed chart type. An `isTimeSeries()` method on the enum distinguishes routing: `AnalyticsDataService` handles point-in-time sources; `AnalyticsSnapshotService` handles time-series sources.

The `AnalyticsWidget` model gains a nullable `time_range` column (`'7d'`, `'30d'`, `'90d'`) persisted per widget so each widget remembers its selected range.

On the frontend, `ChartType` adds `'line'`, and a `TimeSeriesChartData` TypeScript interface carries named series. A type guard (`isTimeSeriesData()`) distinguishes the two data shapes in the chart rendering component. Time range is selectable via pill-style buttons in the widget configurator.

### Consequences

- **New migration:** `analytics_snapshots` table. No changes to existing tables beyond adding `time_range` to `analytics_widgets`.
- **Scheduler dependency:** The `analytics:snapshot` command must be scheduled. Historical data only accumulates from the first run — no backfill mechanism yet.
- **Data growth:** 9 rows per user per day. For 10 users over a year: ~33k rows — negligible.
- **Global scope bypass:** The snapshot command and service use `withoutGlobalScopes()` since they operate outside the HTTP request lifecycle.
- **AnalyticsDataService guard:** Calling `resolve()` with a time-series source now throws `InvalidArgumentException` instead of silently failing.

### Follow-ups / open questions

- Consider a backfill command that generates approximate historical snapshots from `created_at`/`updated_at` timestamps for users who enable analytics after data already exists.
- The `data_sources` (JSON, plural) column was added to `analytics_widgets` for potential future multi-source widgets but is not yet utilized. Currently each widget has a single `data_source`.
- Evaluate whether 90-day charts need date label grouping (weekly buckets) for readability on smaller screens.
