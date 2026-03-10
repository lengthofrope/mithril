# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] - 1.1.0

### Added

- **Inline select component** — New `<x-tl.inline-select-pill>` Blade component with Alpine.js integration for inline priority/status editing on task cards
- **Live UI counters** — Dashboard counters now auto-refresh via central event dispatch and dedicated `CounterController` API endpoint
- **`DashboardStatsService`** — Extracted dashboard statistics into a reusable service

### Changed

- **Rebranding** — Renamed from "TeamDash" to "Mithril" with payoff "Lightweight armor for team leads"
- Updated all logo SVGs with new shield icon and Mithril branding (light, dark, icon-only, auth)
- Updated PWA manifest, app icons, and favicon with shield motif
- Updated page titles, login page heading, and payoff across all Blade templates
- Updated project README.md with new branding

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
