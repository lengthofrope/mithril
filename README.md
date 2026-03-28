# Mithril

*Lightweight armor for team leads.*

A Progressive Web App (PWA) serving as a personal browser start page for managing teams. Built for technical team leads who need more than basic task lists; with meetings, follow-ups, per-member context, analytics, and privacy controls.

## Screenshots

### Dashboard

| Light | Dark |
|-------|------|
| ![Dashboard light mode](docs/screenshots/dashboard-light.png) | ![Dashboard dark mode](docs/screenshots/dashboard-dark.png) |

### Tasks

| Light | Dark |
|-------|------|
| ![Tasks light mode](docs/screenshots/tasks-light.png) | ![Tasks dark mode](docs/screenshots/tasks-dark.png) |

### Calendar

| Light | Dark |
|-------|------|
| ![Calendar light mode](docs/screenshots/calendar-light.png) | ![Calendar dark mode](docs/screenshots/calendar-dark.png) |

## Features

### Core

- **Dashboard** — Greeting, counters, quick-create buttons, upcoming tasks/follow-ups/meetings, calendar events, flagged emails, Jira issues
- **Tasks** — Priorities, categories, groups, privacy flag, kanban + list view, drag-and-drop sorting, bulk actions, recurring tasks, inline auto-save configuration
- **Follow-ups** — Timeline view (overdue > today > this week > later), snooze, task conversion, auto-populated from "waiting" tasks
- **Meetings** — Team meetings, 1-on-1s, and custom types; multi-attendee support; prep items with types (agenda/question/action), time estimates, and assignees; status lifecycle (scheduled > in progress > completed/cancelled); previous/next navigation
- **Notes** — Markdown with live preview, tags, pinning, full-text search
- **Teams & Members** — Profile pages with linked tasks, follow-ups, meeting history, agreements; team-filtered member selection across the app
- **Weekly Reflection** — Auto-generated weekly summary with donut and bar charts, free-form markdown reflection
- **Analytics** — Configurable widget dashboard with multiple chart types (bar, line, donut, area), drag-and-drop layout, time-series snapshots

### Audio & AI

- **Audio recording** — In-browser recording via MediaRecorder API with start/stop/pause controls; file upload fallback (mp3, wav, webm, m4a, ogg)
- **Speech service** — Self-hosted Docker-based transcription (faster-whisper) and speaker diarization (pyannote-audio); CPU and GPU (CUDA) support; per-user local or server-side processing mode
- **AI extractions** — Multi-provider AI extraction (OpenRouter, Anthropic) for meeting action items, decisions, and summaries; extraction review UI

### Integrations

- **Office 365** — Calendar sync, team member availability (auto-detect from email), email inbox sync via Microsoft Graph API
- **Jira Cloud** — Browse assigned/mentioned/watched issues, link issues to resources, auto-sync every 5 minutes
- **API** — RESTful API with Sanctum token authentication, granular `resource:action` abilities, predefined scopes (read-only, read-write, full-access)

### Platform

- **PWA** — Service worker, offline fallback, installable
- **Auth** — Email/password with remember-me, two-factor authentication (TOTP), disabled account support
- **Dark mode** — Full dark/light mode support with Rivendell-inspired UI theme (sage green, Philosopher/Outfit typography, elvish decorative elements)
- **Auto-save** — No "Save" buttons; everything auto-saves via debounced AJAX (500ms)
- **Activity feed** — Polymorphic activity log with comments, links, and file attachments on tasks, follow-ups, notes, and meetings
- **System notifications** — Admin broadcast notifications via artisan command
- **Data management** — JSON export/import, configurable auto-pruning of completed items, storage management with quota enforcement

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4+ / Laravel 12 |
| Database | MariaDB |
| Frontend | Blade + Alpine.js + Tailwind CSS v4 |
| TypeScript | Strict mode, bundled via Vite |
| Testing | Pest PHP v4 with Laravel plugin |
| Base template | [TailAdmin Laravel](https://github.com/TailAdmin/tailadmin-laravel) (MIT) |
| Libraries | SortableJS, ApexCharts, Flatpickr, Marked + DOMPurify, Floating UI |

## Requirements

- PHP 8.4 or higher
- Composer
- Node.js 20+ and npm
- MariaDB 10.6+

## Installation

1. **Clone the repository**

   ```bash
   git clone git@github.com:lengthofrope/mithril.git
   cd mithril
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
   DB_DATABASE=mithril
   DB_USERNAME=root
   DB_PASSWORD=
   ```

4. **Create the database**

   ```bash
   mysql -u root -e "CREATE DATABASE mithril CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
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

   To enable calendar sync, team member availability, and email integration from Outlook:

   a. **Create an App Registration** in the [Azure Portal](https://entra.microsoft.com):
      - Go to **Microsoft Entra ID** > **App registrations** > **New registration**
      - Name: `Mithril` (or any name)
      - Supported account types: **Single tenant** (or multi-tenant if your team spans organisations)
      - Redirect URI: **Web** ; `https://your-domain.com/auth/microsoft/callback`
      - Click **Register**

   b. **Create a client secret:**
      - Go to **Certificates & secrets** > **New client secret**
      - Copy the **Value** column immediately (it's hidden after you leave the page)
      - Do NOT copy the Secret ID; that's not the secret itself

   c. **Configure API permissions:**
      - Go to **API permissions** > **Add a permission** > **Microsoft Graph** > **Delegated permissions**
      - Add: `User.Read`, `Calendars.Read`, `Mail.Read`, `offline_access`
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

8. **Configure Jira Cloud integration (optional)**

   To enable browsing and linking Jira issues:

   a. **Create an OAuth 2.0 (3LO) app** in the [Atlassian Developer Console](https://developer.atlassian.com/console/myapps/):
      - Click **Create** > **OAuth 2.0 integration**
      - Name: `Mithril` (or any name)
      - Under **Authorization** > **OAuth 2.0 (3LO)**, click **Configure**
      - Callback URL: `https://your-domain.com/auth/jira/callback`
      - Click **Save changes**

   b. **Add API scopes:**
      - Go to **Permissions** > **Jira API** > **Configure**
      - Add scopes: `read:jira-work`, `read:jira-user`
      - The `offline_access` scope is requested automatically for refresh tokens

   c. **Copy credentials:**
      - Go to **Settings** to find your **Client ID** and **Secret**

   d. **Set environment variables** in `.env`:

      ```dotenv
      JIRA_CLIENT_ID=<Client ID from Settings page>
      JIRA_CLIENT_SECRET=<Secret from Settings page>
      JIRA_REDIRECT_URI="${APP_URL}/auth/jira/callback"
      ```

   e. **Connect your account** in the app at **Settings** > **Jira Cloud** > **Connect Jira**

9. **Configure speech service (optional)**

   For meeting transcription and speaker diarization, deploy the self-hosted speech service:

   ```bash
   cd docker/speech
   docker compose up -d
   ```

   For GPU acceleration, use the override file:

   ```bash
   docker compose -f docker-compose.yml -f docker-compose.gpu.yml up -d
   ```

   Configure in `.env`:

   ```dotenv
   SPEECH_SERVER_ENABLED=true
   SPEECH_SERVICE_URL=http://localhost:8090
   ```

   Users can also configure a personal local speech service instance via **Settings** > **Speech Service**.

10. **Set up the cron job**

    Laravel's task scheduler needs a single cron entry on your server. This runs scheduled tasks including the daily analytics snapshot, calendar sync (every 5 min), availability sync (every 5 min), email sync, and Jira sync.

    ```bash
    * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
    ```

11. **Start a queue worker**

    The calendar, availability, email sync, Jira sync, and transcription jobs run on the queue. Start a worker:

    ```bash
    php artisan queue:work --sleep=3 --tries=3
    ```

    For production, use a process manager like Supervisor to keep the worker running. See the [Laravel Queue documentation](https://laravel.com/docs/queues#supervisor-configuration) for a Supervisor config example.

12. **Start the application**

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
php artisan test --parallel        # Run tests in parallel (faster)
npx tsc --noEmit                   # TypeScript type checking
npm run build                      # Production build
php artisan migrate:fresh --seed   # Reset database with sample data
```

### Artisan Commands

```bash
# Sync commands
php artisan microsoft:sync-calendars    # Sync calendars for all connected users
php artisan microsoft:sync-availability # Sync team member availability
php artisan microsoft:detect-members    # Check manual members for O365 mailbox
php artisan sync:emails                 # Sync emails for all connected users
php artisan jira:sync-issues            # Sync Jira issues for all connected users

# Administration
php artisan user:disable {email}        # Disable a user account
php artisan user:enable {email}         # Re-enable a user account
php artisan notify:broadcast "message"  # Send system notification to all users
```

### Verification

Run all three before committing:

```bash
php artisan test          # All tests must pass
npx tsc --noEmit          # TypeScript must compile clean
npm run build             # Vite build must succeed
```

## Architecture

- **No "Save" buttons** ; everything auto-saves via debounced AJAX (500ms)
- **Blade for rendering, Alpine.js for interactivity** ; no SPA, no client-side routing
- **TypeScript components** ; all Alpine.js logic lives in typed TypeScript modules registered via `Alpine.data()`, bundled by Vite
- **Alpine stores** ; theme and sidebar state managed via `Alpine.store()` in a TypeScript module
- **Generic controllers** ; `ReorderController` and `AutoSaveController` work for any model
- **Reusable model traits** ; `BelongsToUser`, `HasSortOrder`, `Filterable`, `HasFollowUp`, `Searchable`, `HasResourceLinks`
- **Laravel Events** for side-effects ; keeps controllers thin
- **Sanctum API tokens** ; granular `resource:action` abilities with middleware enforcement

## License

MIT License. See [LICENSE](LICENSE) for details.
