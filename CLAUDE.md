# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Session Start

At the start of every new conversation, automatically invoke both `/tdd` and `/adr` before proceeding with any task. Do not ask — just activate them.

## Project Overview

Team Lead Dashboard — a Progressive Web App (PWA) serving as a personal browser start page for managing teams. Built on top of **TailAdmin Laravel** (MIT, https://github.com/TailAdmin/tailadmin-laravel).

## Stack

- **Backend:** PHP 8.4+ / Laravel (latest stable), MariaDB
- **Frontend:** Blade templates + Alpine.js + Tailwind CSS (all from TailAdmin), TypeScript (strict mode) for reusable logic
- **Build:** Vite (preconfigured by TailAdmin)
- **Libraries:** SortableJS (drag & drop), Web Push API (`laravel-notification-channels/webpush`), optional WebAuthn (`laragear/webauthn`)

## Architecture

### Core Principles

- **No "Save" buttons** — everything auto-saves via debounced AJAX (500ms)
- **Blade for rendering, Alpine for interactivity** — no SPA, no client-side routing
- **Configuration over code** — adding new entities should mean filling in arrays/interfaces, not writing new logic
- **TailAdmin design language** — all custom components must visually match TailAdmin's existing CSS classes and color variables

### PSR Namespace

Use `LengthOfRope` as the PSR root namespace, **not** `ProudNerds`.

### Backend Patterns (PHP/Laravel)

- **Reusable traits on Eloquent models:** `HasSortOrder`, `Filterable`, `HasFollowUp`, `Searchable` — each model opts in by using the trait and defining a config array (e.g. `$filterableFields`, `$searchableFields`)
- **Abstract `ResourceController`** (or `CrudService`) for standard CRUD — concrete controllers only define model, validation rules, and overrides
- **Generic endpoints:** one `ReorderController` and one `AutoSaveController` that work for any model type, not per-entity endpoints
- **`AutoSaveRequest`** base class supporting partial validation (only validate sent fields)
- **Standardized API response format** for all endpoints:
  ```json
  { "success": true, "data": {}, "message": "...", "saved_at": "ISO-8601" }
  ```
- **Laravel Events** for side-effects (`TaskStatusChanged`, `FollowUpDue`, `BilaScheduled`) — keep controllers thin

### Frontend Patterns (TypeScript/Alpine.js)

- All reusable logic lives in TypeScript modules bundled by Vite, exposed as Alpine.js `data()` components
- Generic systems: `AutoSaver`, `DragDropSortable`, `FilterManager` — configured via interfaces, not reimplemented per use
- Shared types in `resources/js/types/` mirror Laravel models
- Central Alpine component registration in `app.ts`
- Server returns Blade partials (HTML fragments) for filtered/searched results — no client-side templating

### Custom Blade Components

All project-specific Blade components live under `resources/views/components/tl/` namespace to avoid conflicts with TailAdmin components.

## File Ownership (Agent Teams)

When using parallel agents, respect these boundaries to avoid conflicts:

| Agent | Owns |
|-------|------|
| `backend` | `app/`, `database/`, `config/` |
| `frontend` | `resources/views/`, `resources/css/` |
| `typescript` | `resources/js/` |
| `pwa` | `public/manifest.json`, `public/sw.js` |
| **Shared** | `routes/web.php`, `routes/api.php` (coordinate via messages) |

## Key Commands

```bash
# Install
composer install && npm install

# Development
npm run dev              # Vite dev server
php artisan serve        # Laravel dev server

# Build
npm run build            # Production Vite build

# Database
php artisan migrate:fresh --seed   # Reset DB with sample data

# Verification (run all before committing)
php artisan test          # All tests must pass
npx tsc --noEmit          # TypeScript must compile clean
npm run build             # Vite build must succeed

# Scheduler (for push notification checks)
php artisan schedule:run
```

## Data Model

Core entities: `users`, `teams`, `team_members`, `tasks`, `task_groups`, `follow_ups`, `bilas`, `bila_prep_items`, `agreements`, `notes`, `note_tags`, `weekly_reflections`, `task_categories`, `push_subscriptions`.

Full schema defined in [plans/teamlead-dashboard.md](plans/teamlead-dashboard.md) under "Datamodel".

## Key Features Reference

- **Dashboard:** greeting, counters, today-section, quick-add inline form
- **Tasks:** priorities, categories, groups, privacy flag, kanban + list view, drag & drop sorting, bulk actions
- **Follow-ups:** timeline view (overdue > today > this week > later), snooze, auto-populated from "waiting" tasks
- **Teams/Members:** profile page with linked tasks, follow-ups, bila history, agreements
- **Bilas:** recurring 1-on-1s with prep items checklist, markdown notes
- **Notes:** markdown with live preview, tags, pinning, full-text search
- **Weekly Reflection:** auto-generated summary + free-form reflection
- **PWA:** service worker, offline fallback, push notifications, installable
- **Auth:** email/password + remember-me cookie, optional WebAuthn

## Plan Document

The full project specification (in Dutch) is at [plans/teamlead-dashboard.md](plans/teamlead-dashboard.md). It contains the complete data model, UI requirements, architecture patterns, and Agent Teams build strategy.
