# Office 365 Integration — Implementatieplan

## Samenvatting

Integratie met Microsoft Graph API om (1) je eigen Office 365 agenda en afspraken te tonen op het dashboard, en (2) de beschikbaarheidsstatus van teamleden automatisch te synchroniseren op basis van hun Outlook-agenda. De integratie gebruikt OAuth2 via Microsoft Entra ID (voorheen Azure AD) en slaat tokens veilig op voor achtergrondverwerking.

---

## Scope

### In scope

| Feature | Beschrijving |
|---------|-------------|
| **OAuth2-koppeling** | Gebruiker koppelt zijn Microsoft-account aan het dashboard via OAuth2 |
| **Eigen agenda** | Komende afspraken tonen op het dashboard (vandaag + deze week) |
| **Teamlid-beschikbaarheid** | Automatische status-sync (available/absent/partially_available) op basis van Outlook free/busy |
| **Token-management** | Automatische refresh van access tokens via background job |
| **Ontkoppelen** | Gebruiker kan Microsoft-koppeling verwijderen |

### Niet in scope (mogelijk fase 2+)

- Inloggen via Microsoft (SSO) — huidige login blijft email/password
- Outlook-taken synchroniseren met dashboard-taken
- E-mail weergave of verzending
- Teams-chat integratie
- Agenda-events aanmaken of wijzigen vanuit het dashboard
- OneDrive / SharePoint bestanden

---

## Microsoft Entra ID Setup

### App Registration

In het Azure Portal (entra.microsoft.com) moet een App Registration worden aangemaakt:

```
Application (client) ID:  → MICROSOFT_CLIENT_ID
Directory (tenant) ID:    → MICROSOFT_TENANT_ID
Client secret:            → MICROSOFT_CLIENT_SECRET
Redirect URI:             → {APP_URL}/auth/microsoft/callback
```

### API Permissions

| Permission | Type | Reden |
|-----------|------|-------|
| `User.Read` | Delegated | Basis gebruikersinfo ophalen |
| `Calendars.Read` | Delegated | Eigen agenda-events lezen |
| `Calendars.Read.Shared` | Delegated | Gedeelde agenda's lezen (optioneel) |
| `Schedule.Read.All` | Application | Free/busy van teamleden ophalen zonder hun consent |

**Belangrijk:** `Schedule.Read.All` is een **Application permission** en vereist admin consent. Dit is nodig om de `getSchedule` endpoint aan te roepen voor teamleden die geen OAuth-flow doorlopen. Zonder admin consent kan alleen de free/busy van de ingelogde gebruiker worden opgehaald.

### Alternatief zonder admin consent

Als admin consent niet haalbaar is, kan de `getSchedule` endpoint nog steeds worden aangeroepen met **delegated** `Calendars.Read.Shared` permission, mits de teamleden hun agenda delen met de ingelogde gebruiker. Dit is minder robuust maar werkt in veel organisaties.

---

## Datamodel

### Wijziging: `users` tabel

Nieuwe kolommen via migration:

```
users (bestaand)
├── ...bestaande kolommen...
├── microsoft_id              VARCHAR(100), NULL, UNIQUE  — Entra Object ID
├── microsoft_email           VARCHAR(255), NULL          — Microsoft-account email
├── microsoft_access_token    TEXT, NULL                   — Encrypted access token
├── microsoft_refresh_token   TEXT, NULL                   — Encrypted refresh token
├── microsoft_token_expires_at TIMESTAMP, NULL             — Token expiry
```

**Encryptie:** Access en refresh tokens worden versleuteld opgeslagen via Laravel's `encrypted` cast. Ze bevatten gevoelige credentials die toegang geven tot de Microsoft Graph API.

### Wijziging: `team_members` tabel

Nieuwe kolom via migration:

```
team_members (bestaand)
├── ...bestaande kolommen...
├── microsoft_email    VARCHAR(255), NULL  — Email voor Graph API lookups
├── status_source      VARCHAR(20), DEFAULT 'manual'  — 'manual' | 'microsoft'
├── status_synced_at   TIMESTAMP, NULL     — Laatste sync-moment
```

**`status_source`:** Geeft aan of de status handmatig is gezet of automatisch via Microsoft. Bij `microsoft` wordt de status niet overschreven door handmatige wijzigingen (tenzij de gebruiker de source terugzet naar `manual`).

**`microsoft_email`:** Het emailadres waarmee het teamlid in de organisatie bekend is. Dit wordt gebruikt voor de `getSchedule` API-call. Kan afwijken van het bestaande `email` veld (dat optioneel is en voor contactdoeleinden dient).

### Nieuwe tabel: `calendar_events` (cache)

```
calendar_events
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── user_id             BIGINT UNSIGNED, FK → users.id, ON DELETE CASCADE
├── microsoft_event_id  VARCHAR(255), NOT NULL  — Graph API event ID
├── subject             VARCHAR(500), NOT NULL
├── start_at            TIMESTAMP, NOT NULL
├── end_at              TIMESTAMP, NOT NULL
├── is_all_day          BOOLEAN, DEFAULT FALSE
├── location            VARCHAR(500), NULL
├── status              VARCHAR(30), NOT NULL    — 'free' | 'tentative' | 'busy' | 'oof' | 'workingElsewhere'
├── is_online_meeting   BOOLEAN, DEFAULT FALSE
├── online_meeting_url  VARCHAR(1000), NULL
├── organizer_name      VARCHAR(255), NULL
├── organizer_email     VARCHAR(255), NULL
├── synced_at           TIMESTAMP, NOT NULL
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
```

**Waarom een cache-tabel?** Directe API-calls bij elke page load zijn te traag (200-500ms per call) en kunnen rate limits raken. Een sync-job haalt data periodiek op, de UI leest uit de lokale database.

### Nieuwe Enum: `StatusSource`

```php
enum StatusSource: string
{
    case Manual = 'manual';
    case Microsoft = 'microsoft';
}
```

### Nieuwe Enum: `CalendarEventStatus`

```php
enum CalendarEventStatus: string
{
    case Free = 'free';
    case Tentative = 'tentative';
    case Busy = 'busy';
    case OutOfOffice = 'oof';
    case WorkingElsewhere = 'workingElsewhere';
}
```

---

## Backend Architectuur

### Service: `MicrosoftGraphService`

Centrale service voor alle Microsoft Graph API-interactie:

```php
class MicrosoftGraphService
{
    public function getAuthorizationUrl(string $state): string
    public function exchangeCodeForTokens(string $code): TokenResponse
    public function refreshAccessToken(User $user): void
    public function getMyProfile(User $user): GraphUser
    public function getMyCalendarEvents(User $user, CarbonInterface $from, CarbonInterface $to): Collection
    public function getScheduleAvailability(User $user, array $emails, CarbonInterface $from, CarbonInterface $to): Collection
    public function revokeAccess(User $user): void
}
```

**Token refresh:** Elke methode die de API aanroept controleert eerst of het token verlopen is en refresht automatisch. Als refresh faalt (bijv. gebruiker heeft consent ingetrokken), wordt de koppeling gedeactiveerd en de gebruiker genotificeerd.

**HTTP client:** Gebruikt Laravel's `Http` facade (Guzzle wrapper). Geen aparte Microsoft Graph SDK nodig — de REST API is voldoende eenvoudig.

### DTO: `TokenResponse`

```php
final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonInterface $expiresAt,
        public string $microsoftId,
        public string $email,
    ) {}
}
```

### Controller: `MicrosoftAuthController`

```php
class MicrosoftAuthController extends Controller
{
    public function redirect(): RedirectResponse        // Start OAuth flow
    public function callback(Request $request): RedirectResponse  // Handle callback
    public function disconnect(): RedirectResponse       // Remove Microsoft link
}
```

### Controller uitbreiding: `DashboardController`

De bestaande `DashboardController::index()` wordt uitgebreid met agenda-data:

```php
// Nieuwe data voor de view:
$calendarEvents = CalendarEvent::query()
    ->where('start_at', '>=', now()->startOfDay())
    ->where('start_at', '<=', now()->endOfWeek())
    ->orderBy('start_at')
    ->get();

$isMicrosoftConnected = (bool) $request->user()->microsoft_id;
```

### Jobs

#### `SyncCalendarEventsJob`

Haalt agenda-events op voor de komende 7 dagen en slaat ze op in `calendar_events`. Wordt per gebruiker gedraaid.

```php
class SyncCalendarEventsJob implements ShouldQueue
{
    public function __construct(private readonly User $user) {}

    public function handle(MicrosoftGraphService $graph): void
    {
        // 1. Haal events op voor komende 7 dagen
        // 2. Upsert in calendar_events (op microsoft_event_id)
        // 3. Verwijder events die niet meer in de response zitten
        // 4. Update synced_at
    }
}
```

#### `SyncMemberAvailabilityJob`

Haalt free/busy status op voor alle teamleden met een `microsoft_email` en `status_source = microsoft`.

```php
class SyncMemberAvailabilityJob implements ShouldQueue
{
    public function __construct(private readonly User $user) {}

    public function handle(MicrosoftGraphService $graph): void
    {
        // 1. Verzamel alle microsoft_emails van teamleden met status_source = microsoft
        // 2. Roep getSchedule aan (max 20 per batch)
        // 3. Map availability naar MemberStatus:
        //    - oof → Absent
        //    - busy/tentative → PartiallyAvailable (of Available, configureerbaar)
        //    - free → Available
        // 4. Update team_members.status en status_synced_at
    }
}
```

### Scheduler

```php
// app/Console/Kernel.php (of routes/console.php)
Schedule::command('microsoft:sync-calendars')->everyFifteenMinutes();
Schedule::command('microsoft:sync-availability')->everyFiveMinutes();
```

De commands itereren over alle gebruikers met een actieve Microsoft-koppeling en dispatchen de bijbehorende jobs.

### Availability → MemberStatus Mapping

| Graph Schedule Status | MemberStatus | Logica |
|----------------------|-------------|--------|
| `free` | Available | Geen lopend event of event met status free |
| `tentative` | Available | Voorlopig geaccepteerd, beschikbaar tot bevestiging |
| `busy` | PartiallyAvailable | In een meeting maar nog op kantoor/online |
| `oof` (Out of Office) | Absent | Afwezig, niet beschikbaar |
| `workingElsewhere` | Available | Op andere locatie maar beschikbaar |

**Nuance:** Deze mapping is een startpunt. In de settings kan de gebruiker later eventueel de mapping aanpassen (bijv. `busy` → `Absent` i.p.v. `PartiallyAvailable`).

---

## Routes

### Nieuwe web routes

```php
// Microsoft OAuth
Route::get('/auth/microsoft/redirect', [MicrosoftAuthController::class, 'redirect'])
    ->name('microsoft.redirect');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])
    ->name('microsoft.callback');
Route::delete('/auth/microsoft', [MicrosoftAuthController::class, 'disconnect'])
    ->name('microsoft.disconnect');
```

---

## Frontend

### Dashboard: Agenda Sectie

Nieuw Blade component `resources/views/components/tl/calendar-events.blade.php`:

```
<x-tl.calendar-events :events="$calendarEvents" />

├── Vandaag-sectie (gegroepeerd)
│   ├── Tijdlijn-layout: start-eind, onderwerp, locatie
│   ├── Status-indicator (kleurcode: busy=blauw, tentative=gestreept, oof=rood)
│   ├── Online meeting link (klikbaar, Teams/Zoom icoon)
│   └── "Nu bezig" highlight voor lopende events
├── Morgen-sectie
├── Rest van de week (per dag gegroepeerd)
└── Lege state: "Connect your Office 365 account to see your calendar"
```

### Settings: Microsoft Koppeling

Op de bestaande settings pagina (`/settings`) een nieuwe sectie toevoegen:

```
Microsoft Office 365
├── Status: Connected als [email] / Not connected
├── Knop: "Connect Office 365" / "Disconnect"
├── Laatste sync: [timestamp]
└── Info: "Calendar syncs every 15 min, team availability every 5 min."
```

### Member Profile: Status Source Toggle

Op de member profile pagina, naast de huidige status-indicator, een optie toevoegen om de `status_source` te kiezen:

```
Status: [dropdown: Available / Absent / Partially Available]
Sync: [toggle: Manual / Auto (Office 365)]
       └── Vereist: microsoft_email ingevuld
```

Wanneer `Auto` is geselecteerd:
- De status-dropdown wordt disabled (read-only, toont automatische status)
- Een "Last synced: X min ago" label verschijnt
- Het `microsoft_email` veld wordt verplicht

### Member Profile: Microsoft Email Veld

Nieuw auto-save veld op de member profile pagina:

```blade
<x-tl.auto-save-field
    :endpoint="route('members.update', $member->id)"
    field="microsoft_email"
    :value="$member->microsoft_email ?? ''"
    type="email"
    label="Microsoft email (for availability sync)"
/>
```

---

## Configuratie

### Environment Variables

```env
# Microsoft Entra ID (Azure AD)
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_TENANT_ID=
MICROSOFT_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

### Config File: `config/microsoft.php`

```php
return [
    'client_id'     => env('MICROSOFT_CLIENT_ID'),
    'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
    'tenant_id'     => env('MICROSOFT_TENANT_ID'),
    'redirect_uri'  => env('MICROSOFT_REDIRECT_URI'),

    'scopes' => [
        'User.Read',
        'Calendars.Read',
        'offline_access',  // Nodig voor refresh tokens
    ],

    'authority' => 'https://login.microsoftonline.com/',
    'graph_url' => 'https://graph.microsoft.com/v1.0/',

    'calendar_sync_interval_minutes'      => 15,
    'availability_sync_interval_minutes'  => 5,
    'calendar_days_ahead'   => 7,
    'schedule_batch_size'   => 20,  // Max emails per getSchedule call
];
```

---

## Beveiliging

### Token Opslag

- Access en refresh tokens worden **encrypted** opgeslagen via Laravel's `encrypted` cast
- Tokens worden nooit gelogd, nooit in responses opgenomen, nooit in de frontend getoond
- Bij disconnect worden tokens uit de database verwijderd

### OAuth State Parameter

- De OAuth `state` parameter wordt opgeslagen in de sessie en gevalideerd bij de callback
- Beschermt tegen CSRF-aanvallen op de OAuth flow

### Rate Limiting

- Microsoft Graph API heeft throttling (per app: ~2000 requests/10 sec)
- De sync-jobs batchen requests waar mogelijk (`getSchedule` accepteert max 20 emails per call)
- Bij 429 (Too Many Requests) responses: respecteer de `Retry-After` header en herplan de job

### Consent & Privacy

- De gebruiker beslist zelf om de koppeling te maken (opt-in)
- Teamleden worden niet gevraagd om consent — hun free/busy info is organisatiebreed beschikbaar via de `getSchedule` API (mits admin consent)
- Er worden geen emailinhouden, bijlagen, of contacten opgeslagen — alleen agendatijden en free/busy status

---

## Foutafhandeling

| Scenario | Actie |
|---------|-------|
| Token refresh mislukt (consent ingetrokken) | Microsoft-kolommen clearen, gebruiker notificeren via flash message bij volgende page load |
| Graph API timeout | Job retry (max 3x met exponential backoff) |
| 429 Too Many Requests | Respecteer `Retry-After`, herplan job |
| Teamlid email niet gevonden in organisatie | Sla over, log warning, toon "Email not found" in UI |
| Microsoft-account gewisseld | Oude tokens overschrijven, calendar_events resyncen |

---

## Implementatiefasen

### Fase 1: Fundament — OAuth & Token Management

1. Migration: Microsoft-kolommen op `users` tabel
2. Config: `config/microsoft.php` + `.env.example` uitbreiden
3. Enum: `StatusSource`
4. Service: `MicrosoftGraphService` (auth methods: getAuthorizationUrl, exchangeCodeForTokens, refreshAccessToken, revokeAccess)
5. Controller: `MicrosoftAuthController` (redirect, callback, disconnect)
6. Routes: OAuth routes toevoegen
7. Settings UI: Connect/disconnect knop op settings pagina
8. Tests: OAuth flow, token storage, token refresh, disconnect

### Fase 2: Eigen Agenda

9. Migration: `calendar_events` tabel
10. Model: `CalendarEvent` met BelongsToUser trait
11. Enum: `CalendarEventStatus`
12. Service uitbreiden: `getMyCalendarEvents()` methode
13. Job: `SyncCalendarEventsJob`
14. Artisan command: `microsoft:sync-calendars`
15. Scheduler: elke 15 minuten
16. Dashboard UI: Calendar events component
17. Tests: Sync job, calendar display, edge cases (hele dag events, meerdaagse events)

### Fase 3: Teamlid Beschikbaarheid

18. Migration: `microsoft_email`, `status_source`, `status_synced_at` op `team_members`
19. Service uitbreiden: `getScheduleAvailability()` methode
20. Job: `SyncMemberAvailabilityJob`
21. Artisan command: `microsoft:sync-availability`
22. Scheduler: elke 15 minuten
23. Member profile UI: microsoft_email veld + status source toggle
24. Tests: Availability sync, mapping logic, edge cases

### Fase 4: Polish

25. Error handling: Token expiry notificaties, API failure graceful degradation
26. Loading states voor agenda-sectie
27. "Last synced" indicators
28. Documentatie: README update met Azure setup instructies

---

## Dependencies

### Composer

```bash
composer require guzzlehttp/guzzle  # Waarschijnlijk al indirect aanwezig via Laravel
```

Geen Microsoft Graph SDK nodig — Laravel's `Http` facade volstaat voor de beperkte set endpoints.

### NPM

Geen nieuwe frontend dependencies nodig.

---

## Aandachtspunten

### Multi-tenancy

Het plan gaat uit van een **single-tenant** app registration (één Azure AD tenant). Als je teamleden in meerdere organisaties hebt, moet de app registration worden omgezet naar **multi-tenant** en moet de `authority` URL worden aangepast naar `https://login.microsoftonline.com/common/`.

### Testomgeving

- Microsoft biedt geen sandbox-API — je test tegen een echte tenant
- Gebruik een development-tenant (gratis via Microsoft 365 Developer Program)
- Voor unit tests: mock de `MicrosoftGraphService` met voorgedefinieerde responses

### Tijd zones

- Graph API retourneert tijden in UTC of de tijdzone van de gebruiker (afhankelijk van de `Prefer: outlook.timezone` header)
- Sla altijd op in UTC, converteer in de frontend met de server-side tijdzone van de gebruiker
- `calendar_events.start_at` en `end_at` zijn TIMESTAMP (UTC)

### Bestaande Status Workflow

De huidige `MemberStatus` enum en het auto-save veld `status` blijven werken. De Microsoft-integratie voegt een **bron** toe (`status_source`), maar wijzigt niet hoe de status wordt opgeslagen of getoond. Dit is backward compatible — teamleden zonder `microsoft_email` blijven op `manual` en veranderen niets.
