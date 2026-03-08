# Analytics Dashboard — Implementatieplan

## Samenvatting

Een apart Analytics-dashboard met configureerbare grafieken (ApexCharts), waarbij de gebruiker per widget het grafiektype kan kiezen (donut, bar, horizontal bar, stacked bar) en widgets kan drag & droppen — zowel op het Analytics-board als op het hoofddashboard. Widgets zijn herbruikbaar: dezelfde widget kan op beide pagina's verschijnen.

---

## Concepten

### Widget
Een **Widget** is een configureerbare grafiek-eenheid. Elke widget heeft:
- Een **data source** (welke statistiek: taken per status, per prioriteit, etc.)
- Een **chart type** (donut, bar-vertical, bar-horizontal, stacked-bar)
- Een **sort_order** (positie op het board)
- Een **plaatsing** (analytics, dashboard, of beide)
- Een **kolombreedte** (1/3, 1/2, 2/3, of full)

### Data Sources
Voorgedefinieerde data-aggregaties die server-side worden berekend. Elke source levert een genormaliseerd `{ labels: string[], series: number[] }` formaat.

---

## Data Sources (fase 1)

| ID | Naam | Beschrijving |
|----|------|-------------|
| `tasks_by_status` | Tasks by Status | Verdeling Open / InProgress / Waiting / Done |
| `tasks_by_priority` | Tasks by Priority | Verdeling Urgent / High / Normal / Low (excl. Done) |
| `tasks_by_category` | Tasks by Category | Openstaande taken per TaskCategory |
| `tasks_by_group` | Tasks by Group | Openstaande taken per TaskGroup (gebruikt groepskleuren) |
| `tasks_by_member` | Tasks by Team Member | Openstaande taken per teamlid |
| `tasks_by_deadline` | Deadline Overview | Taken gegroepeerd: Overdue / Today / This week / Next week / Later / No deadline |
| `follow_ups_by_status` | Follow-ups by Status | Open / Snoozed / Done |
| `follow_ups_by_urgency` | Follow-ups by Urgency | Overdue / Today / This week / Later |

### Toekomstige uitbreidingen (fase 2+)
- `tasks_completed_trend` — Tijdlijn: taken afgerond per week (line chart)
- `bilas_frequency` — Bilas per teamlid afgelopen 30 dagen
- `workload_balance` — Verdeling open taken + follow-ups per teamlid

---

## Datamodel

### Nieuwe tabel: `analytics_widgets`

```
analytics_widgets
├── id                  BIGINT UNSIGNED, PK, AUTO_INCREMENT
├── user_id             BIGINT UNSIGNED, FK → users.id, ON DELETE CASCADE
├── data_source         VARCHAR(50), NOT NULL  — bijv. 'tasks_by_status'
├── chart_type          VARCHAR(30), NOT NULL  — 'donut' | 'bar' | 'bar_horizontal' | 'stacked_bar'
├── title               VARCHAR(100), NULL     — custom titel (fallback naar data source naam)
├── column_span         TINYINT UNSIGNED, NOT NULL, DEFAULT 1  — 1=1/3, 2=2/3, 3=full
├── show_on_analytics   BOOLEAN, NOT NULL, DEFAULT TRUE
├── show_on_dashboard   BOOLEAN, NOT NULL, DEFAULT FALSE
├── sort_order_analytics SMALLINT UNSIGNED, NOT NULL, DEFAULT 0
├── sort_order_dashboard SMALLINT UNSIGNED, NOT NULL, DEFAULT 0
├── created_at          TIMESTAMP
├── updated_at          TIMESTAMP
```

### Enum: `ChartType`

```php
enum ChartType: string
{
    case Donut = 'donut';
    case Bar = 'bar';
    case BarHorizontal = 'bar_horizontal';
    case StackedBar = 'stacked_bar';
}
```

### Enum: `DataSource`

```php
enum DataSource: string
{
    case TasksByStatus = 'tasks_by_status';
    case TasksByPriority = 'tasks_by_priority';
    case TasksByCategory = 'tasks_by_category';
    case TasksByGroup = 'tasks_by_group';
    case TasksByMember = 'tasks_by_member';
    case TasksByDeadline = 'tasks_by_deadline';
    case FollowUpsByStatus = 'follow_ups_by_status';
    case FollowUpsByUrgency = 'follow_ups_by_urgency';
}
```

De `DataSource` enum bevat ook een `label(): string` methode die de menselijke naam retourneert, en een `allowedChartTypes(): array` methode die aangeeft welke chart types zinvol zijn per source (bijv. deadline overview leent zich niet goed voor donut).

---

## Backend Architectuur

### Model: `AnalyticsWidget`

```
App\Models\AnalyticsWidget
├── Traits: BelongsToUser, HasSortOrder
├── $casts: data_source → DataSource, chart_type → ChartType
├── $fillable: data_source, chart_type, title, column_span,
│              show_on_analytics, show_on_dashboard,
│              sort_order_analytics, sort_order_dashboard
```

### Service: `AnalyticsDataService`

Centrale service die data aggregeert. Eén publieke methode per data source, plus een dispatcher:

```php
class AnalyticsDataService
{
    public function resolve(DataSource $source): ChartData  // dispatcher
    public function tasksByStatus(): ChartData
    public function tasksByPriority(): ChartData
    public function tasksByCategory(): ChartData
    public function tasksByGroup(): ChartData
    public function tasksByMember(): ChartData
    public function tasksByDeadline(): ChartData
    public function followUpsByStatus(): ChartData
    public function followUpsByUrgency(): ChartData
}
```

`ChartData` is een simpel DTO:

```php
final readonly class ChartData
{
    public function __construct(
        public array $labels,
        public array $series,
        public array $colors = [],  // optioneel, voor group colors
    ) {}
}
```

**Waarom een service i.p.v. logica in controller?**
- Herbruikbaar: zowel de Analytics pagina als het Dashboard roepen dezelfde service aan
- Testbaar: unit tests op de aggregatie-logica zonder HTTP
- Single Responsibility: controller doet alleen routing + response

### Controller: `AnalyticsPageController`

```php
class AnalyticsPageController extends Controller
{
    public function index(): View           // Analytics board pagina
    public function widgetData(): JsonResponse   // GET /analytics/widget-data?sources[]=...
    public function store(): JsonResponse        // POST /analytics/widgets — nieuwe widget
    public function update(): JsonResponse       // PATCH /analytics/widgets/{widget}
    public function destroy(): JsonResponse      // DELETE /analytics/widgets/{widget}
}
```

### DashboardController uitbreiding

De bestaande `DashboardController::index()` wordt uitgebreid met dashboard-widgets:

```php
// In buildStats() of aparte methode:
$dashboardWidgets = AnalyticsWidget::where('show_on_dashboard', true)
    ->orderBy('sort_order_dashboard')
    ->get();
```

### Routes (web.php)

```php
Route::get('/analytics', [AnalyticsPageController::class, 'index'])->name('analytics.index');
Route::get('/analytics/widget-data', [AnalyticsPageController::class, 'widgetData'])->name('analytics.widget-data');
Route::post('/analytics/widgets', [AnalyticsPageController::class, 'store'])->name('analytics.widgets.store');
Route::patch('/analytics/widgets/{analyticsWidget}', [AnalyticsPageController::class, 'update'])->name('analytics.widgets.update');
Route::delete('/analytics/widgets/{analyticsWidget}', [AnalyticsPageController::class, 'destroy'])->name('analytics.widgets.destroy');
```

### Reorder integratie

De bestaande `ReorderController` wordt uitgebreid met `analytics_widget` als nieuw model type. Twee aparte reorder-calls zijn nodig: een voor `sort_order_analytics` en een voor `sort_order_dashboard`. Dit wordt opgelost door een `sort_field` parameter toe te voegen aan het reorder-endpoint:

```
POST /reorder
{
    "model_type": "analytics_widget",
    "sort_field": "sort_order_analytics",  // of "sort_order_dashboard"
    "items": [{ "id": 1, "sort_order": 0 }, ...]
}
```

---

## Frontend Architectuur

### NPM Package

```bash
npm install apexcharts
```

ApexCharts wordt direct gebruikt (geen wrapper library). De TypeScript types zijn inbegrepen.

### TypeScript Types

Toevoegen aan `resources/js/types/models.ts`:

```typescript
type ChartType = 'donut' | 'bar' | 'bar_horizontal' | 'stacked_bar';

type DataSource =
    | 'tasks_by_status'
    | 'tasks_by_priority'
    | 'tasks_by_category'
    | 'tasks_by_group'
    | 'tasks_by_member'
    | 'tasks_by_deadline'
    | 'follow_ups_by_status'
    | 'follow_ups_by_urgency';

interface AnalyticsWidget {
    id: number;
    data_source: DataSource;
    chart_type: ChartType;
    title: string | null;
    column_span: number;
    show_on_analytics: boolean;
    show_on_dashboard: boolean;
    sort_order_analytics: number;
    sort_order_dashboard: number;
}

interface ChartData {
    labels: string[];
    series: number[];
    colors: string[];
}
```

### Alpine Component: `analyticsChart`

Nieuw component in `resources/js/components/analytics-chart.ts`:

```typescript
interface AnalyticsChartConfig {
    widgetId: number;
    chartType: ChartType;
    dataSource: DataSource;
    dataEndpoint: string;
    title: string;
}
```

Verantwoordelijkheden:
- Haalt data op via `apiClient.get(dataEndpoint)`
- Initialiseert ApexCharts instance met het juiste type
- Luistert naar `chart-type-changed` event om het type dynamisch te switchen (destroy + re-create)
- Responsive: herberekent op window resize
- Dark mode support: luistert naar theme changes en update chart kleuren

### Alpine Component: `analyticsBoard`

Nieuw component in `resources/js/components/analytics-board.ts`:

```typescript
interface AnalyticsBoardConfig {
    context: 'analytics' | 'dashboard';
    reorderEndpoint: string;
    widgetEndpoint: string;
}
```

Verantwoordelijkheden:
- Initialiseert SortableJS op de widget grid container
- Stuurt reorder-calls bij drop (met juiste `sort_field`)
- Biedt methodes voor widget CRUD (add, remove, update chart type)
- Dispatcht `widget-updated` events zodat individuele charts refreshen

### Alpine Component: `widgetConfigurator`

Nieuw component in `resources/js/components/widget-configurator.ts`:

Modal/dropdown waarmee de gebruiker:
- Een data source kiest
- Een chart type kiest (alleen opties die zinvol zijn voor die source)
- De kolombreedte kiest
- Bepaalt of de widget op analytics, dashboard, of beide verschijnt
- Opslaat via POST of PATCH

---

## Views

### Nieuwe pagina: `resources/views/pages/analytics.blade.php`

```
@extends('layouts.app')

├── Breadcrumb: Analytics
├── Header met "Add widget" knop
├── Widget grid (3-koloms grid, responsive)
│   ├── Per widget: <x-tl.analytics-widget />
│   └── Sortable via analyticsBoard component
├── Lege state: "No widgets yet. Click 'Add widget' to get started."
└── Widget configurator modal
```

### Nieuw component: `resources/views/components/tl/analytics-widget.blade.php`

Een kaart die:
- De widget titel toont
- Het ApexChart element bevat (via `analyticsChart` Alpine component)
- Een kebab-menu (⋮) heeft met:
  - Chart type wijzigen (sub-menu met opties)
  - Kolombreedte wijzigen
  - Tonen op dashboard toggle
  - Widget verwijderen
- De juiste `column_span` class toepast (`col-span-1`, `col-span-2`, `col-span-3`)
- Een drag handle heeft (grip icon links van de titel)

### Dashboard uitbreiding: `resources/views/pages/dashboard.blade.php`

Onder de bestaande "Today section" wordt een nieuwe sectie toegevoegd:

```blade
{{-- Analytics widgets --}}
@if($dashboardWidgets->isNotEmpty())
    <div class="mt-8" x-data="analyticsBoard({ context: 'dashboard', ... })">
        <h2 class="mb-4 text-lg font-semibold ...">Analytics</h2>
        <div id="dashboard-widget-grid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($dashboardWidgets as $widget)
                <x-tl.analytics-widget :widget="$widget" context="dashboard" />
            @endforeach
        </div>
    </div>
@endif
```

### Navigatie

Toevoegen aan de sidebar navigatie (na "Weekly Reflection", voor "Settings"):

```
📊 Analytics  →  /analytics
```

---

## Drag & Drop Strategie

### Hergebruik bestaand patroon

De bestaande `sortableList` component en `ReorderController` worden hergebruikt. De widgets grid wordt een `<x-tl.sortable-container>` met:
- `model-type="analytics_widget"`
- Aparte `sort_field` parameter per context

### Twee onafhankelijke sorteerbare grids
1. **Analytics board**: sorteert op `sort_order_analytics`
2. **Dashboard widgets sectie**: sorteert op `sort_order_dashboard`

Beide gebruiken dezelfde `ReorderController`, maar met een extra `sort_field` parameter om aan te geven welke kolom ge-update moet worden.

### ReorderController aanpassing

De bestaande `ReorderController` wordt uitgebreid om een optionele `sort_field` te accepteren. Default blijft `sort_order` voor backward compatibility. Alleen gewhitelist-e velden worden geaccepteerd (security).

---

## Chart Type Configuratie per Data Source

| Data Source | Donut | Bar | Bar Horizontal | Stacked Bar |
|-------------|:-----:|:---:|:--------------:|:-----------:|
| tasks_by_status | v | v | v | - |
| tasks_by_priority | v | v | v | - |
| tasks_by_category | v | v | v | - |
| tasks_by_group | v | v | v | - |
| tasks_by_member | - | v | v | - |
| tasks_by_deadline | - | v | v | v |
| follow_ups_by_status | v | v | v | - |
| follow_ups_by_urgency | - | v | v | v |

---

## Kleuren

### Vaste kleuren per data source

De service levert consistente kleuren aan die passen bij TailAdmin's design tokens:

- **Status**: Open=blue-500, InProgress=amber-500, Waiting=purple-500, Done=green-500
- **Priority**: Urgent=red-500, High=orange-500, Normal=blue-500, Low=gray-400
- **Categories**: Rotatie uit TailAdmin palette (blue, teal, indigo, pink, amber, emerald, violet, cyan)
- **Groups**: Kleuren uit de `task_groups.color` database kolom
- **Deadline**: Overdue=red-500, Today=orange-500, This week=amber-500, Next week=blue-500, Later=gray-400, No deadline=gray-300
- **Follow-up status**: Open=blue-500, Snoozed=amber-500, Done=green-500
- **Follow-up urgency**: Overdue=red-500, Today=orange-500, This week=amber-500, Later=gray-400

Dark mode: ApexCharts krijgt een `theme.mode` instelling die meeloopt met de Tailwind dark mode toggle.

---

## Implementatiestappen

### Fase 1: Fundament (backend)
1. Migration: `analytics_widgets` tabel
2. Enums: `ChartType`, `DataSource`
3. DTO: `ChartData`
4. Model: `AnalyticsWidget`
5. Service: `AnalyticsDataService` met alle 8 data sources
6. Tests: Unit tests voor alle data sources

### Fase 2: API endpoints
7. Controller: `AnalyticsPageController` (CRUD + data endpoint)
8. ReorderController uitbreiden met `sort_field`
9. Routes toevoegen
10. Tests: Feature tests voor endpoints

### Fase 3: Frontend fundament
11. `npm install apexcharts`
12. TypeScript types toevoegen
13. `analyticsChart` Alpine component
14. `analyticsBoard` Alpine component
15. `widgetConfigurator` Alpine component
16. Registratie in `app.ts`

### Fase 4: Views
17. `analytics-widget.blade.php` component
18. `analytics.blade.php` pagina
19. Dashboard uitbreiden met widget sectie
20. Sidebar navigatie-item toevoegen

### Fase 5: Polish
21. Lege states
22. Loading states (skeleton loaders tijdens data fetch)
23. Error states
24. Responsive gedrag (1 kolom op mobile, 2 op tablet, 3 op desktop)
25. Dark mode verificatie

---

## Aandachtspunten

### Performance
- Widget data wordt **on-demand** opgehaald via AJAX, niet bij page load — voorkomt trage initiële render
- Elke widget doet een eigen API call met zijn `data_source` — eenvoudig en cacheable
- Overweeg server-side caching (5 min TTL) als het aantal widgets groeit

### Beveiliging
- `sort_field` whitelist in ReorderController (alleen `sort_order`, `sort_order_analytics`, `sort_order_dashboard`)
- Alle queries via `BelongsToUser` trait — geen data leakage
- Widget CRUD validatie op bekende `data_source` en `chart_type` enum values

### UX
- Widget toevoegen opent een modal met een duidelijke flow: kies source → kies type → kies breedte → kies plaatsing → opslaan
- Chart type wijzigen is direct via het kebab-menu, zonder modal
- Drag handle is duidelijk zichtbaar (grip dots icon)
- Na elke wijziging: auto-save (consistent met rest van de app)

---

## Niet in scope

- Line/area charts (fase 2 — vereist tijdreeks-data)
- Widget dupliceren
- Widget delen tussen gebruikers
- PDF/image export van grafieken
- Real-time updates (WebSocket) — data refresht bij page load
- Custom queries / vrije SQL
