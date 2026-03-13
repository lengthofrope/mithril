# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Session Start

At the start of every new conversation, automatically invoke `/tdd`, `/adr`, and `/lemp` before proceeding with any task. Do not ask — just activate them.

## Project Overview

**Mithril** — *Lightweight armor for team leads.* A Progressive Web App (PWA) serving as a personal browser start page for managing teams. Built on top of **TailAdmin Laravel** (MIT, https://github.com/TailAdmin/tailadmin-laravel).

## Stack

- **Backend:** PHP 8.4+ / Laravel 12, MariaDB
- **Frontend:** Blade templates + Alpine.js + Tailwind CSS v4 (TailAdmin base), TypeScript (strict mode) for reusable logic
- **Build:** Vite
- **Libraries:** SortableJS (drag & drop), ApexCharts (analytics), Marked + DOMPurify (markdown), Flatpickr (date pickers), Floating UI (tooltips/popovers)
- **Testing:** Pest PHP v4 with Laravel plugin

## Architecture

### Core Principles

- **No "Save" buttons** — everything auto-saves via debounced AJAX (500ms)
- **Blade for rendering, Alpine for interactivity** — no SPA, no client-side routing
- **Configuration over code** — adding new entities should mean filling in arrays/interfaces, not writing new logic
- **TailAdmin design language** — all custom components must visually match TailAdmin's existing CSS classes and color variables

### Backend Patterns (PHP/Laravel)

- **Reusable traits on Eloquent models:** `BelongsToUser` (global user scope + auto user_id), `HasSortOrder`, `Filterable`, `HasFollowUp`, `Searchable`, `HasResourceLinks` — each model opts in by using the trait and defining a config array (e.g. `$filterableFields`, `$searchableFields`)
- **`BelongsToUser`** is on virtually every model — provides a global scope so queries are always scoped to the authenticated user
- **`AbstractResourceController`** (`app/Http/Controllers/Api/`) for standard CRUD — concrete controllers only define `$modelClass` and `$requestClass` (often just 2-5 lines)
- **Generic endpoints:** one `ReorderController` and one `AutoSaveController` that work for any model type, not per-entity endpoints
- **`AutoSaveRequest`** base class supporting partial validation (only validate sent fields)
- **`ApiResponse` trait** on all API controllers — standardized format: `{ "success": true, "data": {}, "message": "...", "saved_at": "ISO-8601" }`
- **Laravel Events** for side-effects — keep controllers thin
- **Enums** (11 in `app/Enums/`) are all string-backed PHP 8.4 enums, stored as strings in DB, validated with `Rule::enum()`
- **Form Requests** use `prepareForValidation()` to normalize input (e.g. empty strings → null for foreign keys)

### Frontend Patterns (TypeScript/Alpine.js)

- All reusable logic lives in TypeScript modules bundled by Vite, exposed as Alpine.js `data()` components
- Generic systems: `AutoSaver`, `DragDropSortable`, `FilterManager` — configured via interfaces, not reimplemented per use
- Shared types in `resources/js/types/` mirror Laravel models
- Central Alpine component registration in `resources/js/app.ts` (~20 components)
- Server returns Blade partials (HTML fragments) for filtered/searched results — no client-side templating

### Custom Blade Components

All project-specific Blade components live under `resources/views/components/tl/` namespace to avoid conflicts with TailAdmin components.

## Database & Migrations

**Production database is MariaDB.** All migrations must be MariaDB-compatible:

- **No MySQL-only features** — avoid `mysql`-specific syntax, JSON path operators (`->>`), or MySQL-specific functions
- **FULLTEXT indexes** must be guarded: check `DB::getDriverName() === 'sqlite'` and skip (SQLite doesn't support them). See `2024_01_01_200000_add_fulltext_indexes.php` for the pattern.
- **Enums stored as strings** — use `$table->string()` not `$table->enum()` (better MariaDB/migration compatibility)
- **JSON columns** — avoid; prefer normalized columns. MariaDB JSON support is less mature than MySQL's.
- **Foreign keys** — use `constrained()->nullOnDelete()` or `constrained()->cascadeOnDelete()` pattern
- **Testing** uses SQLite (`:memory:`), so migrations must work on both MariaDB and SQLite

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

# Development (all services concurrently)
composer dev                 # server + queue + logs + vite

# Or individually:
php artisan serve            # Laravel dev server
npm run dev                  # Vite dev server

# Build
npm run build                # Production Vite build

# Database
php artisan migrate:fresh --seed   # Reset DB with sample data

# Testing
php artisan test                                    # All tests
php artisan test tests/Unit/Models/TaskTest.php     # Single file
php artisan test --filter="test name"               # Single test

# Verification (run all before committing)
php artisan test          # All tests must pass
npx tsc --noEmit          # TypeScript must compile clean
npm run build             # Vite build must succeed
```

## Routing

- **Web routes** (`routes/web.php`): RESTful, grouped under `auth` middleware. 17 page controllers in `app/Http/Controllers/Web/`.
- **API routes** (`routes/api.php`): Prefixed `/api/v1/`, middleware `auth:web` + `throttle:api`. Uses `apiResource()` for standard CRUD, plus generic `auto-save` and `reorder` endpoints.

## Architecture Decision Records

ADRs are stored in `logs/decisions/` (18 records). New architectural decisions must be documented there using the `/adr` skill.

## Plan Document

The full project specification (in Dutch) is at [plans/teamlead-dashboard.md](plans/teamlead-dashboard.md). It contains the complete data model, UI requirements, architecture patterns, and Agent Teams build strategy.
