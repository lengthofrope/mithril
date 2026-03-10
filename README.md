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
- **Office 365** — Calendar sync and automatic team member availability via Microsoft Graph API
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

7. **Configure Microsoft Office 365 integration (optional)**

   To enable calendar sync and team member availability from Outlook:

   a. **Create an App Registration** in the [Azure Portal](https://entra.microsoft.com):
      - Go to **Microsoft Entra ID** > **App registrations** > **New registration**
      - Name: `Mithril` (or any name)
      - Supported account types: **Single tenant** (or multi-tenant if your team spans organisations)
      - Redirect URI: **Web** — `https://your-domain.com/auth/microsoft/callback`
      - Click **Register**

   b. **Create a client secret:**
      - Go to **Certificates & secrets** > **New client secret**
      - Copy the **Value** column immediately (it's hidden after you leave the page)
      - Do NOT copy the Secret ID — that's not the secret itself

   c. **Configure API permissions:**
      - Go to **API permissions** > **Add a permission** > **Microsoft Graph** > **Delegated permissions**
      - Add: `User.Read`, `Calendars.Read`, `offline_access`
      - Optionally add `Calendars.Read.Shared` for shared calendar access
      - For team availability without per-member consent: add **Application permission** `Schedule.Read.All` and grant admin consent

   d. **Set environment variables** in `.env`:

      ```dotenv
      MICROSOFT_CLIENT_ID=<Application (client) ID from Overview page>
      MICROSOFT_CLIENT_SECRET=<Secret Value from Certificates & secrets>
      MICROSOFT_TENANT_ID=<Directory (tenant) ID from Overview page>
      MICROSOFT_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
      ```

   e. **Connect your account** in the app at **Settings** > **Microsoft Office 365** > **Connect Office 365**

   f. **Set up team member availability** (optional):
      - On a member's profile page, fill in their **Microsoft email** address
      - Change **Status source** to **Auto (Office 365)**
      - Their availability status will sync every 5 minutes based on their Outlook calendar

8. **Set up the cron job**

   Laravel's task scheduler needs a single cron entry on your server. This runs scheduled tasks including the daily analytics snapshot, calendar sync (every 15 min), and team availability sync (every 5 min).

   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

9. **Start a queue worker**

   The calendar and availability sync jobs run on the queue. Start a worker:

   ```bash
   php artisan queue:work --sleep=3 --tries=3
   ```

   For production, use a process manager like Supervisor to keep the worker running. See the [Laravel Queue documentation](https://laravel.com/docs/queues#supervisor-configuration) for a Supervisor config example.

10. **Start the application**

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
php artisan schedule:run           # Run scheduler (analytics, calendar sync, availability sync)
php artisan microsoft:sync-calendars    # Manually sync calendars for all connected users
php artisan microsoft:sync-availability # Manually sync team member availability
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
