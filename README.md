# Mithril

*Lightweight armor for team leads.*

A Progressive Web App (PWA) serving as a personal browser start page for managing teams. Built for technical team leads who need more than basic task lists — with follow-ups, per-member context, and privacy controls.

## Features

- **Dashboard** — Greeting, counters, today-section, quick-add inline form
- **Tasks** — Priorities, categories, groups, privacy flag, kanban + list view, drag & drop sorting, bulk actions
- **Follow-ups** — Timeline view (overdue > today > this week > later), snooze, auto-populated from "waiting" tasks
- **Teams & Members** — Profile pages with linked tasks, follow-ups, bila history, agreements
- **Bilas** — Recurring 1-on-1s with prep items checklist, markdown notes
- **Notes** — Markdown with live preview, tags, pinning, full-text search
- **Weekly Reflection** — Auto-generated summary + free-form reflection
- **Analytics** — Configurable dashboard with charts and widgets
- **PWA** — Service worker, offline fallback, push notifications, installable
- **Auth** — Email/password + remember-me cookie, optional WebAuthn

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4+ / Laravel 12 |
| Database | MariaDB |
| Frontend | Blade + Alpine.js + Tailwind CSS |
| TypeScript | Strict mode, bundled via Vite |
| Base template | [TailAdmin Laravel](https://github.com/TailAdmin/tailadmin-laravel) (MIT) |
| Libraries | SortableJS, ApexCharts, Flatpickr, Marked |

## Requirements

- PHP 8.4 or higher
- Composer
- Node.js 20+ and npm
- MariaDB 10.6+

## Installation

1. **Clone the repository**

   ```bash
   git clone <repository-url> teamlead-dashboard
   cd teamlead-dashboard
   ```

2. **Install dependencies**

   ```bash
   composer install
   npm install
   ```

3. **Configure environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Edit `.env` and set your database credentials:

   ```dotenv
   DB_CONNECTION=mariadb
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=teamlead_dashboard
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Create the database**

   ```bash
   mysql -u root -e "CREATE DATABASE teamlead_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

5. **Run migrations and seed**

   ```bash
   php artisan migrate --seed
   ```

6. **Build frontend assets**

   ```bash
   npm run build
   ```

7. **Set up the cron job**

   Laravel's task scheduler needs a single cron entry on your server. This runs scheduled tasks like the daily analytics snapshot (`analytics:snapshot` at 00:15).

   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

8. **Start the application**

   For development, use the combined dev command that starts the Laravel server, queue worker, log viewer, and Vite dev server simultaneously:

   ```bash
   composer dev
   ```

   Or start services individually:

   ```bash
   php artisan serve        # Laravel dev server
   npm run dev              # Vite dev server
   ```

   The application will be available at `http://localhost:8000`.

## Development

### Key Commands

```bash
composer dev                       # Start all dev services concurrently
php artisan test                   # Run test suite (Pest)
npx tsc --noEmit                   # TypeScript type checking
npm run build                      # Production build
php artisan migrate:fresh --seed   # Reset database with sample data
php artisan schedule:run           # Run scheduler (push notification checks)
```

### Verification

Run all three before committing:

```bash
php artisan test
npx tsc --noEmit
npm run build
```

## Architecture

- **No "Save" buttons** — everything auto-saves via debounced AJAX (500 ms)
- **Blade for rendering, Alpine.js for interactivity** — no SPA, no client-side routing
- **Generic controllers** — `ReorderController` and `AutoSaveController` work for any model
- **Reusable model traits** — `HasSortOrder`, `Filterable`, `HasFollowUp`, `Searchable`
- **Laravel Events** for side-effects — keeps controllers thin
- **TypeScript modules** exposed as Alpine.js `data()` components

## License

Proprietary. All rights reserved.
