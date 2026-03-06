# Prompt: Team Lead Dashboard — Browser Startpagina & PWA

## Context

Ik ben een technisch team lead en zoek een persoonlijke startpagina voor mijn browser die mij helpt bij het dagelijks managen van mijn teams. Outlook-taken en eenvoudige todo-lijsten schieten tekort: ik mis opvolgingsmogelijkheden, context per teamlid, overzicht over wat er speelt, en de mogelijkheid om privénotities en gevoelige taken afgeschermd te houden.

## Doel

Bouw een **Progressive Web App (PWA)** die ik als browser-startpagina kan instellen en op mijn telefoon kan installeren. De app draait op een eigen server, slaat alles op in MariaDB, en stuurt push-notificaties — ook als het browservenster niet open staat.

## Technische stack

- **Basis-framework:** TailAdmin Laravel (gratis, open-source, MIT-licentie) als startpunt. Dit levert de dashboard-layout, Blade-componenten, dark mode, sidebar-navigatie, en UI-componenten kant-en-klaar. GitHub: https://github.com/TailAdmin/tailadmin-laravel
- **Backend:** PHP 8.4+ met Laravel (laatste stabiele versie)
- **Frontend:** Blade templates + Alpine.js (geen Vue, geen React), Tailwind CSS — allemaal al onderdeel van TailAdmin Laravel
- **Build:** Vite (al geconfigureerd in TailAdmin Laravel)
- **Database:** MariaDB
- **Auth:** Eenvoudig login-systeem met "onthoud mij"-cookie (lange sessie). Optioneel: WebAuthn (vingerafdruk-login) via een package zoals `laragear/webauthn` — als dit te complex is, mag het als aparte stap/uitbreiding worden opgezet.
- **PWA:** Service worker, web app manifest, offline fallback-pagina, push-notificaties via Web Push API (Laravel package: `laravel-notification-channels/webpush`)

---

## Architectuurprincipe: Herbruikbaarheid & Uitbreidbaarheid

Dit is een essentieel onderdeel van het project. Alle code — zowel PHP als TypeScript — moet zo worden opgezet dat nieuwe entiteiten, gedragingen en UI-componenten kunnen worden toegevoegd met minimale duplicatie. Denk bij elke keuze: "als ik over 3 maanden een nieuw type content toevoeg, hoeveel plekken moet ik dan aanpassen?"

### Integratie met TailAdmin Laravel

TailAdmin Laravel is het startpunt. We gebruiken de bestaande structuur en breiden deze uit — we bouwen niet from scratch.

**Wat we overnemen van TailAdmin:**
- De volledige Blade layout-structuur (sidebar, header, main content area)
- De bestaande UI-componenten: kaarten, tabellen, formuliervelden, badges, modals, dropdowns, statistiek-widgets
- De dark mode toggle en thema-systeem
- De Vite-configuratie en Tailwind CSS setup
- De Alpine.js integratie en bestaande interactieve componenten (dropdowns, sidebars, etc.)

**Wat we toevoegen / aanpassen:**
- Sidebar-navigatie aanpassen naar onze secties (Dashboard, Taken, Opvolgingen, Teams, Notities, Weekoverzicht)
- Eigen Blade-componenten bouwen die de TailAdmin design-taal volgen maar onze specifieke functionaliteit bevatten
- TypeScript-modules toevoegen voor auto-save, drag & drop, en filtering (zie hieronder)
- Nieuwe pagina-templates die de TailAdmin layout extenden
- PWA-functionaliteit (service worker, manifest) toevoegen aan de bestaande Vite-build

**Belangrijk:** houd je aan TailAdmin's bestaande CSS-klassen, kleurvariabelen, en component-patronen. Nieuwe componenten moeten visueel naadloos aansluiten bij het bestaande design. Gebruik de TailAdmin Blade-componenten waar mogelijk in plaats van eigen HTML te schrijven.

### PHP / Laravel — Backend patterns

#### Generieke traits en interfaces
- Maak een `HasSortOrder` trait (of vergelijkbaar) die elk Eloquent model kan gebruiken dat sorteerbaar is. De trait levert scopes (`orderBySortOrder()`), een methode om bulk-reorder te doen, en automatische `sort_order`-toekenning bij aanmaken.
- Maak een `Filterable` trait met een generiek filter-systeem: elk model dat deze trait gebruikt definieert een `$filterableFields` array. Eén gedeelde scope (`scopeApplyFilters($query, array $filters)`) verwerkt query-parameters naar WHERE-clausules. Geen per-model filter-logica in controllers.
- Maak een `HasFollowUp` trait voor elk model dat opvolgingen kan hebben (taken, afspraken, losse opvolgingen). De trait definieert de relatie, scopes voor verlopen/vandaag/komend, en snooze-logica.
- Maak een `Searchable` trait (of gebruik Laravel Scout met database driver) die full-text search toevoegt. Elk model dat searchable is definieert een `$searchableFields` array.

#### Generieke controller-logica
- Gebruik een abstracte `ResourceController` (of een gedeelde `CrudService`-klasse) die standaard index/store/update/destroy-logica bevat. Concrete controllers extenden deze en definiëren alleen het model, validatieregels, en eventuele afwijkende logica.
- Eén gedeelde `ReorderController` (of een `reorder`-methode op de abstracte controller) die voor elk sorteerbaar model werkt. Accepteert `{ model: "task", items: [{ id: 1, sort_order: 0 }, ...] }`. Geen aparte reorder-endpoints per entiteit.
- Eén gedeelde `AutoSaveController` (of method) die partiële updates afhandelt: accepteert een model-type, een ID, en een key-value pair. Valideert via de Form Request van dat model. Retourneert een consistente JSON-response met timestamp.

#### Form Requests
- Eén basis `AutoSaveRequest` die partiële validatie ondersteunt: valideer alleen de velden die daadwerkelijk worden meegestuurd (voor auto-save van individuele velden), maar valideer alles bij een volledige create.

#### API-responses
- Eén gestandaardiseerd JSON-response format voor alle endpoints:
  ```
  {
    "success": true,
    "data": { ... },
    "message": "...",         // optioneel
    "saved_at": "ISO-8601"    // voor auto-save feedback
  }
  ```
- Foutresponses volgen hetzelfde format met `"success": false` en `"errors": { ... }`.

#### Events & Notifications
- Gebruik Laravel Events voor acties die side-effects hebben (bijv. `TaskStatusChanged`, `FollowUpDue`, `BilaScheduled`). Listeners handelen notificaties, opvolgingen, en andere gevolgen af. Dit houdt controllers dun en maakt het triviaal om nieuwe side-effects toe te voegen.

### TypeScript / Alpine.js — Frontend patterns

**Belangrijk:** hoewel we Alpine.js gebruiken (geen SPA-framework), schrijven we alle herbruikbare logica in TypeScript-modules die via Vite worden gebundeld en als Alpine.js `data()`-objecten of als globale utilities beschikbaar zijn.

#### Generiek auto-save systeem
Eén herbruikbaar TypeScript-object/class `AutoSaver` dat op elk formulierveld kan worden aangesloten:
```typescript
// Conceptueel voorbeeld — geen letterlijke implementatie-eis
interface AutoSaveConfig {
  endpoint: string;          // bijv. "/api/tasks/5"
  field: string;             // bijv. "description"
  debounceMs?: number;       // default 500
  onSuccess?: (response: SaveResponse) => void;
  onError?: (error: ApiError) => void;
}
```
- In Blade/Alpine: `x-data="autoSaveField({ endpoint: '/api/tasks/5', field: 'title' })"` — meer is niet nodig per veld.
- De `AutoSaver` handelt debouncing, CSRF-token, fetch-call, retry-bij-fout, en visuele status ("opslaan…" / "opgeslagen ✓" / "fout ✗") allemaal intern af.

#### Generiek drag & drop systeem
Eén herbruikbaar TypeScript-object/class `DragDropSortable` dat SortableJS wrapt en op elk type content werkt:
```typescript
interface DragDropConfig {
  containerSelector: string;    // CSS selector van de sortable container
  modelType: string;            // bijv. "task", "bila_prep_item", "task_group"
  endpoint: string;             // bijv. "/api/reorder"
  group?: string;               // SortableJS group naam (voor cross-list drag)
  onReorder?: (items: ReorderItem[]) => void;
  onMove?: (item: MoveItem) => void;  // voor cross-groep verplaatsing
}

interface ReorderItem {
  id: number;
  sort_order: number;
}

interface MoveItem {
  id: number;
  from_group: number | null;
  to_group: number | null;
  sort_order: number;
}
```
- In Blade/Alpine: `x-data="sortableList({ modelType: 'task', endpoint: '/api/reorder', group: 'tasks' })"` — meer is niet nodig.
- Het systeem handelt SortableJS-initialisatie, event-handling, het berekenen van nieuwe sort_order waarden, de AJAX-call, en visuele feedback (ghost-element, drop-zone) intern af.
- Cross-list drag (bijv. taak van groep A naar groep B, of kanban-kolom wisselen) wordt door hetzelfde object afgehandeld via de `onMove`-callback en een `PATCH`-call die zowel de `sort_order` als de groep/status bijwerkt.

#### Generiek filter/zoek systeem
Eén herbruikbaar `FilterManager` object:
```typescript
interface FilterConfig {
  endpoint: string;             // bijv. "/api/tasks"
  availableFilters: FilterDef[];
  onResults: (html: string) => void;  // Blade partial als HTML-response
}

interface FilterDef {
  field: string;        // bijv. "team_id", "status", "priority"
  type: "select" | "multi-select" | "date-range" | "boolean" | "search";
  label: string;
  options?: { value: string; label: string }[];  // voor select-types
}
```
- Filters worden via query-parameters naar de API gestuurd. De server retourneert een Blade partial (HTML-fragment) dat Alpine in de DOM injecteert. Hierdoor blijft de rendering server-side (Blade) en hoeft de frontend geen templates te bevatten.

#### Gedeelde TypeScript types
Definieer een set gedeelde interfaces die door alle modules worden gebruikt:
```typescript
// types/api.ts
interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
  saved_at?: string;
}

interface ApiError {
  success: false;
  errors: Record<string, string[]>;
  message: string;
}

// types/models.ts — spiegelt de Laravel models
interface Task { id: number; title: string; priority: Priority; status: TaskStatus; ... }
interface TeamMember { id: number; name: string; team_id: number; ... }
interface FollowUp { id: number; follow_up_date: string; status: FollowUpStatus; ... }
// etc. voor alle entiteiten

type Priority = "urgent" | "high" | "normal" | "low";
type TaskStatus = "open" | "in_progress" | "waiting" | "done";
type FollowUpStatus = "open" | "snoozed" | "done";
```

#### Alpine.js component-registratie
Alle herbruikbare Alpine-componenten worden in TypeScript geschreven en centraal geregistreerd:
```typescript
// app.ts — centrale registratie
Alpine.data("autoSaveField", autoSaveField);
Alpine.data("autoSaveForm", autoSaveForm);
Alpine.data("sortableList", sortableList);
Alpine.data("sortableKanban", sortableKanban);
Alpine.data("filterManager", filterManager);
Alpine.data("markdownEditor", markdownEditor);
Alpine.data("confirmDialog", confirmDialog);
Alpine.data("privacyToggle", privacyToggle);
// etc.
```
Elk component is een losse TypeScript-module met een duidelijke interface. Nieuwe componenten toevoegen = nieuw bestand + één regel registratie.

### Mappenstructuur (gebaseerd op TailAdmin Laravel)

TailAdmin Laravel heeft al een nette mappenstructuur. Hieronder staat wat we **toevoegen** aan de bestaande structuur. Bestanden gemarkeerd met `[TA]` zijn van TailAdmin en worden hergebruikt of uitgebreid; bestanden gemarkeerd met `[NIEUW]` voegen wij toe.

```
app/
  Http/
    Controllers/
      Api/                               [NIEUW] ← alle API-endpoints voor auto-save, reorder, etc.
        ReorderController.php            [NIEUW] ← generiek, werkt voor alle sorteerbare models
        AutoSaveController.php           [NIEUW] ← generiek, partiële updates voor elk model
        TaskController.php               [NIEUW] ← extend abstracte ResourceController
        FollowUpController.php           [NIEUW]
        TeamController.php               [NIEUW]
        TeamMemberController.php         [NIEUW]
        BilaController.php               [NIEUW]
        NoteController.php               [NIEUW]
        SearchController.php             [NIEUW]
        ExportImportController.php       [NIEUW]
      Web/
        DashboardController.php          [NIEUW]
        TaskPageController.php           [NIEUW]
        FollowUpPageController.php       [NIEUW]
        TeamPageController.php           [NIEUW]
        NotePageController.php           [NIEUW]
        WeeklyReflectionController.php   [NIEUW]
        SettingsController.php           [NIEUW]
    Requests/
      AutoSaveRequest.php                [NIEUW] ← basis voor partiële validatie
      TaskRequest.php                    [NIEUW]
      ...
  Models/
    Traits/                              [NIEUW]
      HasSortOrder.php
      Filterable.php
      HasFollowUp.php
      Searchable.php
    Task.php                             [NIEUW]
    Team.php                             [NIEUW]
    TeamMember.php                       [NIEUW]
    TaskGroup.php                        [NIEUW]
    FollowUp.php                         [NIEUW]
    Bila.php                             [NIEUW]
    BilaPrepItem.php                     [NIEUW]
    Agreement.php                        [NIEUW]
    Note.php                             [NIEUW]
    WeeklyReflection.php                 [NIEUW]
    ...
  Events/                                [NIEUW]
    TaskStatusChanged.php
    FollowUpDue.php
    BilaScheduled.php
    ...
  Listeners/                             [NIEUW]
    ...
  Services/                              [NIEUW]
    CrudService.php                      ← gedeelde CRUD-logica
    ReorderService.php                   ← sort_order herberekening
    NotificationScheduler.php

resources/
  js/                                    [TA] ← TailAdmin's bestaande JS-map, wij voegen TypeScript toe
    types/                               [NIEUW]
      api.ts                             ← ApiResponse, ApiError
      models.ts                          ← Task, TeamMember, etc.
    components/                          [NIEUW] ← onze Alpine.js componenten in TypeScript
      auto-save-field.ts
      auto-save-form.ts
      sortable-list.ts
      sortable-kanban.ts
      filter-manager.ts
      markdown-editor.ts
      privacy-toggle.ts
      confirm-dialog.ts
    utils/                               [NIEUW]
      api-client.ts                      ← fetch-wrapper met CSRF, error handling
      debounce.ts
      date-helpers.ts
    app.js                               [TA] ← uitbreiden met onze Alpine-registraties
  views/
    layouts/
      app.blade.php                      [TA] ← TailAdmin's hoofdlayout, sidebar aanpassen
    components/                          [TA] ← TailAdmin's bestaande componenten + onze toevoegingen
      tl/                                [NIEUW] ← namespace voor onze eigen Blade-componenten
        auto-save-field.blade.php
        sortable-container.blade.php
        filter-bar.blade.php
        priority-badge.blade.php
        status-badge.blade.php
        privacy-shield.blade.php
        task-card.blade.php
        follow-up-card.blade.php
        team-member-avatar.blade.php
        ...
    pages/                               [NIEUW] ← onze pagina-templates
      dashboard.blade.php
      tasks/
        index.blade.php
        kanban.blade.php
      follow-ups/
        index.blade.php
      teams/
        index.blade.php
        show.blade.php
        member.blade.php
      notes/
        index.blade.php
      bilas/
        show.blade.php
      weekly/
        index.blade.php
      settings/
        index.blade.php
  css/
    app.css                              [TA] ← Tailwind + eventuele custom styles

public/
  manifest.json                          [NIEUW] ← PWA manifest
  sw.js                                  [NIEUW] ← Service worker
```

### Samenvatting ontwerpprincipes

1. **DRY tot op het bot:** geen copy-paste van filter-logica, sort-logica, of auto-save-logica. Eén implementatie, overal hergebruikt via traits (PHP) en generieke componenten (TypeScript/Alpine).
2. **Configuratie boven code:** nieuwe entiteiten toevoegen betekent primair configuratie (arrays, interfaces) invullen — niet nieuwe logica schrijven.
3. **TypeScript strict mode:** alle frontend-code in TypeScript met `strict: true`. Geen `any` types tenzij absoluut onvermijdelijk.
4. **Eén API-contract:** alle endpoints volgen hetzelfde response-format. De frontend `api-client.ts` module verwacht dit format en handelt errors uniform af.
5. **Blade voor rendering, Alpine voor interactie:** de server levert HTML (Blade partials), Alpine voegt interactiviteit toe. Geen client-side templating.

---

## Kernprincipe: Auto-save

**Alle invoervelden slaan automatisch op.** Er is nergens een "Opslaan"-knop. Gebruik debounced AJAX-calls (bijv. 500ms na laatste toetsaanslag) via Alpine.js + `fetch()` naar de Laravel API. Geef visuele feedback: een subtiele "opgeslagen ✓"-indicator bij elk veld of formulierblok. Bij netwerkfouten: toon een waarschuwing en retry automatisch.

---

## Kernfunctionaliteiten

### 1. Dashboard (startscherm)

- Begroeting op basis van tijdstip ("Goedemorgen", etc.) met de huidige datum
- **Snel overzicht** met tellingen:
  - Openstaande taken (totaal + urgent/vandaag)
  - Opvolgingen die aandacht nodig hebben (verlopen + vandaag)
  - Aankomende bila's / 1-on-1's deze week
- **Vandaag-sectie:** alle items die vandaag relevant zijn (taken met deadline vandaag, opvolgingen die verlopen, geplande gesprekken)
- Snelle invoer: vanuit het dashboard direct een taak of notitie aanmaken via een inline formulier (geen modal/navigatie nodig)

### 2. Teams & Teamleden

Ik manage meerdere teams. Teams zijn de bovenliggende structuur; teamleden horen bij een team.

- **Team:**
  - Naam
  - Beschrijving (optioneel)
  - Kleurcode (voor visuele herkenning in de UI)
- **Teamlid:**
  - Naam
  - Rol / functie
  - Team (verplicht, selectie)
  - E-mailadres (optioneel)
  - Notities (vrij tekstveld, markdown, auto-save)
  - Status-indicator: beschikbaar / afwezig / deels beschikbaar
  - Profielfoto/avatar (optioneel, of initialen als fallback)
- **Per teamlid zichtbaar (profielpagina):**
  - Alle gekoppelde taken (open + recent afgerond)
  - Alle opvolgingen gerelateerd aan dit teamlid
  - Bila-historie (datum + gespreksnotities)
  - Afspraken en aantekeningen (zie sectie 5)

### 3. Taken

Taken zijn mijn persoonlijke actiepunten als team lead.

- **Velden per taak:**
  - Titel (verplicht)
  - Beschrijving (optioneel, markdown-ondersteuning, auto-save)
  - Prioriteit: urgent / hoog / normaal / laag (met kleurcodering)
  - Categorie: Opvolging, Bila, Blocker, Beslissing, HR, Technisch, Overig (categorieën zijn aanpasbaar in instellingen)
  - Deadline (optioneel)
  - Gekoppeld team (optioneel, selectie)
  - Gekoppeld teamlid (optioneel, selectie — gefilterd op geselecteerd team)
  - Status: open / in uitvoering / wacht op ander / afgerond
  - **Taakgroep** (optioneel): een zelfgedefinieerde groep om taken te bundelen (bijv. "Sprint 12", "Project Apollo", "Q3 doelen"). Groepen zijn vrij aan te maken en herbruikbaar.
  - **Privé-vlag** (boolean): als `true`, is de taaktitel en beschrijving standaard verborgen in lijstweergaven. Wordt getoond als "Privétaak" met een slot-icoon. Klikken onthult de inhoud (of via een "toon privé"-toggle per sessie).
  - **Sorteervolgorde** (integer): bepaalt de handmatige volgorde binnen een groep of lijst. Wordt bijgewerkt via drag & drop.
  - Aanmaakdatum (automatisch)
- **Opvolgingsmechanisme:**
  - Bij status "wacht op ander" → verplicht invulveld: op wie wacht ik + wanneer opvolgen
  - Push-notificatie wanneer opvolgdatum bereikt is (ook bij gesloten browser)
  - Mogelijkheid om opvolging te "snoozen" (+1 dag, +3 dagen, volgende week, aangepaste datum)
- **Weergave & filteren:**
  - Standaard: lijst gesorteerd op prioriteit + deadline
  - Filterbaar op: team, teamlid, categorie, status, taakgroep, privé ja/nee
  - Groepeerbaar op: team, teamlid, of taakgroep (toggle)
  - Kanban-view (kolommen per status) als alternatieve weergave
  - Voltooide taken verbergen (toggle) maar altijd doorzoekbaar
- **Taakgroepen:**
  - Vrij aan te maken groepen om taken te bundelen (bijv. "Sprint 12", "Project Apollo", "Q3 doelen")
  - Groepen zijn inklapbaar in de lijstweergave
  - Taken via drag & drop verplaatsen tussen groepen
  - Groepen zelf zijn ook sorteerbaar via drag & drop
  - Een taak kan ook "ongegroepeerd" zijn
- **Drag & drop (overal waar logisch):**
  - Gebruik SortableJS (of vergelijkbare lichtgewicht library, compatible met Alpine.js)
  - **Takenlijst:** taken herordenen binnen een lijst of groep via drag & drop. Nieuwe volgorde wordt direct opgeslagen (auto-save).
  - **Kanban-view:** taken slepen tussen statuskolommen (bijv. van "open" naar "in uitvoering"). Status wordt automatisch bijgewerkt.
  - **Taakgroepen:** taken slepen naar een andere groep. Groep-toewijzing wordt direct opgeslagen.
  - **Bila-voorbereidingspunten:** volgorde van gesprekspunten aanpassen via drag & drop.
  - **Team-/teamlidvolgorde:** optioneel de volgorde van teams en teamleden in de sidebar/overzichten aanpassen.
  - Visuele feedback tijdens het slepen: ghost-element, drop-zone highlight, soepele animatie.
- **Bulk-acties:** meerdere taken selecteren → status wijzigen, categorie toekennen, naar groep verplaatsen, of verwijderen

### 4. Opvolgingen (apart scherm)

Een gefocuste weergave specifiek voor alles waar ik op wacht of wat ik moet opvolgen.

- Automatisch gevuld vanuit taken met status "wacht op ander"
- **Aanvullend handmatig aanmaken** van opvolgingen die niet aan een taak gekoppeld zijn
- Weergave per tijdslijn: verlopen → vandaag → deze week → later
- Kleurcodering op urgentie (rood = verlopen, oranje = vandaag, groen = komend)
- Snelle actie: "Afgehandeld" / "Snooze" / "Omzetten naar taak"
- Filterbaar op team en teamlid

### 5. Notities, Bila's & Afspraken per Teamlid

Dit is een gecombineerd systeem voor alle aantekeningen die ik per teamlid bijhoud.

#### 5a. Bila's (1-on-1 gesprekken)
- Per teamlid een **terugkerend interval** instellen (bijv. elke 2 weken)
- **Gespreksnotities** vastleggen per sessie (markdown, auto-save)
- **Voorbereidingspunten:** checklist-achtig, items verzamelen in de weken vóór het gesprek. Tijdens het gesprek af te vinken.
- Push-notificatie wanneer volgende bila gepland staat
- Historie van alle bila's per teamlid, doorzoekbaar

#### 5b. Afspraken per teamlid
- Vrije aantekeningen over afspraken die ik met een teamlid heb gemaakt (bijv. "afgesproken dat Jan in Q3 de lead-rol oppakt voor project X")
- Datum + beschrijving (markdown, auto-save)
- Optioneel: opvolgdatum (wordt dan zichtbaar in opvolgingen-scherm)

#### 5c. Losse notities
- Voor meeting-aantekeningen, referentiemateriaal, ideeën
- **Velden:**
  - Titel
  - Inhoud (markdown met live preview, auto-save)
  - Tags (vrij in te vullen, autocomplete op bestaande tags)
  - Gekoppeld team en/of teamlid (optioneel)
  - Vastgepind (boolean, gepinde notities bovenaan)
- Zoekfunctie (full-text search over titel + inhoud)
- Sorteerbaar op datum of alfabetisch

### 6. Weekoverzicht / Reflectie

- Wekelijks overzicht (automatisch gegenereerd): wat is er deze week afgerond, wat blijft open, wat is doorgeschoven
- Vrij tekstveld voor persoonlijke weekreflectie (auto-save)
- Historie van eerdere weken terugkijkbaar
- Per team een samenvatting

---

## Authenticatie & Sessie

- **Login:** e-mail + wachtwoord formulier
- **Onthoud mij:** een "Onthoud mij"-checkbox die een lange-levensduur cookie zet (bijv. 30 dagen). Zolang de cookie geldig is, hoeft de gebruiker niet opnieuw in te loggen. Gebruik Laravel's ingebouwde `remember`-functionaliteit.
- **WebAuthn / Vingerafdruk (optioneel/uitbreiding):** na eerste login met wachtwoord kan de gebruiker een vingerafdruk of beveiligingssleutel registreren. Bij volgende bezoeken kan inloggen via vingerafdruk in plaats van wachtwoord. Gebruik `laragear/webauthn` of vergelijkbare package. Als dit te complex is: implementeer het als een los te activeren module met eigen migraties en routes, zodat het later kan worden toegevoegd zonder de rest te breken.
- **Sessiebeveiliging:** CSRF-tokens op alle formulieren, rate-limiting op login-pogingen

---

## PWA & Push-notificaties

### PWA-vereisten
- `manifest.json` met app-naam, iconen (minimaal 192x192 en 512x512), themakleur, startpagina-URL
- Service worker met:
  - Caching van statische assets (CSS, JS, afbeeldingen) via cache-first strategie
  - Offline fallback-pagina ("Je bent offline — data wordt gesynchroniseerd zodra je weer verbinding hebt")
  - Background sync voor auto-save acties die offline plaatsvonden (als haalbaar)
- "Installeren als app"-prompt ondersteunen

### Push-notificaties
- Gebruik de Web Push API + `laravel-notification-channels/webpush`
- De gebruiker kan in instellingen push-notificaties aan/uit zetten
- Notificaties worden verstuurd bij:
  - Opvolgdatum bereikt
  - Bila-herinnering (bijv. 1 uur voor gepland moment, of ochtend van de dag)
  - Deadline van een taak bereikt
- Notificaties werken ook als de browser gesloten is (mits PWA geïnstalleerd of browser push toestaat)
- Laravel Scheduler (cron) controleert periodiek welke notificaties verstuurd moeten worden

---

## UX & Design-vereisten

- **Donker thema** als standaard (licht thema als toggle is een plus, voorkeur opgeslagen per gebruiker)
- **Snel en responsief** — het is een startpagina, dus instant laden. Blade + Alpine.js zonder build-step of met minimale Vite-build.
- **Toetsenbord-shortcuts** voor veelgebruikte acties:
  - `N` → nieuwe taak (inline op dashboard)
  - `Ctrl+K` of `/` → globale zoekfunctie
  - `1-6` → navigeren tussen secties
- Minimalistisch maar functioneel design — geen visuele ruis
- Duidelijke typografie, goede witruimte, scanbaar
- **Privétaken** zijn visueel herkenbaar (slot-icoon, gedimde tekst) en standaard gemaskeerd
- Desktop-first; mobiel moet bruikbaar zijn (responsive) vanwege PWA op telefoon

---

## Technische vereisten

- **TailAdmin Laravel als basis:** clone het repo (https://github.com/TailAdmin/tailadmin-laravel), pas de sidebar-navigatie aan, en bouw alle functionaliteit bovenop de bestaande layout en componenten. Verwijder TailAdmin's demo-pagina's die we niet nodig hebben, maar behoud de componenten-library.
- **Laravel Blade templates** met Alpine.js voor interactiviteit — geen SPA, geen client-side router. Navigatie via gewone pagina-loads (Blade), interacties binnen een pagina via Alpine.js.
- **RESTful API-endpoints** naast de Blade-routes, zodat Alpine.js auto-save calls kan doen via `fetch()`
- **MariaDB database** — migraties via Laravel, seeder met voorbeelddata
- **Auto-save:** alle tekstvelden gebruiken debounced AJAX (Alpine.js `x-on:input.debounce.500ms`). Visuele "opgeslagen"-indicator per veld.
- **Drag & drop:** SortableJS (via npm) geïntegreerd met Alpine.js. Elke drag-actie triggert een auto-save AJAX-call die de nieuwe `sort_order` waarden opslaat. Gebruik een dedicated API-endpoint (`PATCH /api/reorder`) dat een array van id+sort_order paren accepteert voor batch-updates.
- **Zoekfunctionaliteit:** server-side, full-text search via MariaDB FULLTEXT indexes op relevante kolommen
- **Data-export:** mogelijkheid om alle data als JSON te exporteren (backup)
- **Data-import:** JSON-bestand importeren om te herstellen
- **Installatiegemak:**
  ```bash
  git clone https://github.com/TailAdmin/tailadmin-laravel.git teamlead-dashboard
  cd teamlead-dashboard
  composer install
  npm install && npm run build
  cp .env.example .env
  # Configureer MariaDB credentials in .env
  php artisan key:generate
  php artisan migrate --seed
  php artisan serve
  ```
- **Scheduler:** `php artisan schedule:run` via cron voor push-notificatie checks

---

## Datamodel (richtlijn)

```
users
  - id, name, email, password, remember_token,
    theme_preference, push_enabled, created_at, updated_at

teams
  - id, name, description, color, sort_order,
    created_at, updated_at

team_members
  - id, team_id, name, role, email, notes, status,
    avatar_path, bila_interval_days, next_bila_date,
    created_at, updated_at

task_groups (zelfgedefinieerde groepen)
  - id, name, description, color, sort_order,
    created_at, updated_at

tasks
  - id, title, description, priority, category, status,
    deadline, team_id (nullable), team_member_id (nullable),
    task_group_id (nullable), is_private, sort_order,
    created_at, updated_at

follow_ups
  - id, task_id (nullable), team_member_id (nullable),
    description, waiting_on, follow_up_date, snoozed_until,
    status, created_at, updated_at

bilas (1-on-1 gesprekken)
  - id, team_member_id, scheduled_date, notes,
    created_at, updated_at

bila_prep_items
  - id, team_member_id, bila_id (nullable),
    content, is_discussed, sort_order, created_at, updated_at

agreements (afspraken per teamlid)
  - id, team_member_id, description, agreed_date,
    follow_up_date (nullable), created_at, updated_at

notes
  - id, title, content, team_id (nullable),
    team_member_id (nullable), is_pinned,
    created_at, updated_at

note_tags
  - id, note_id, tag

weekly_reflections
  - id, week_start, week_end, summary, reflection,
    created_at, updated_at

task_categories (aanpasbaar)
  - id, name, sort_order, created_at, updated_at

webauthn_credentials (voor vingerafdruk-login)
  - id, user_id, credential_id, public_key, counter,
    name, created_at, updated_at

push_subscriptions (voor web push)
  - id, user_id, endpoint, public_key, auth_token,
    created_at, updated_at
```

---

## Wat ik NIET wil

- Geen Vue, geen React — alleen Alpine.js voor frontend-interactiviteit
- Geen externe API-koppelingen (geen Google Calendar, geen Slack, geen Jira)
- Geen complexe multi-user permissies of rollen (het is mijn persoonlijke tool, maar er is wel een login)
- Geen Electron wrapper — het is een PWA
- Geen "Opslaan"-knoppen — alles auto-save
- Geen overkill: houd het pragmatisch en onderhoudbaar

---

## Gewenste output

Lever een volledig werkend project op, gebouwd bovenop TailAdmin Laravel:

1. **TailAdmin setup:** clone het repo, verwijder overbodige demo-pagina's, pas de sidebar-navigatie aan naar onze secties, configureer MariaDB-verbinding.
2. **Architectuur:** generieke traits (`HasSortOrder`, `Filterable`, `HasFollowUp`, `Searchable`), abstracte controller/service, en gestandaardiseerd API-response format — voordat je aan specifieke features begint.
3. **TypeScript foundation:** gedeelde types (`types/api.ts`, `types/models.ts`), `api-client.ts` wrapper, en generieke Alpine-componenten (`AutoSaver`, `DragDropSortable`, `FilterManager`) — geregistreerd in TailAdmin's bestaande `app.js`.
4. Complete Laravel backend: migraties, models (met traits en relaties), controllers (die de generieke logica hergebruiken), form requests, routes (web + API)
5. Blade templates die TailAdmin's layout en componenten extenden, aangevuld met onze eigen componenten in de `tl/`-namespace
6. PWA setup: manifest.json, service worker, offline fallback
7. Push-notificatie systeem met Laravel Events + Scheduler
8. Auto-save implementatie via het generieke `AutoSaver`-systeem
9. Drag & drop via het generieke `DragDropSortable`-systeem
10. Login-systeem met "onthoud mij"-cookie + optionele WebAuthn vingerafdruk
11. Privétaken-functionaliteit met maskering in de UI via `privacyToggle`-component
12. README met volledige installatie-instructies (incl. MariaDB setup, cron, VAPID keys)
13. Database seeder met realistische voorbeelddata (2 teams, 6-8 teamleden, diverse taken/notities)
14. Werkende zoekfunctie, filters, groepering, en sortering — allemaal via de generieke systemen

Bouw het stap voor stap op:
1. TailAdmin Laravel opzetten en sidebar/navigatie aanpassen
2. Generieke architectuur (traits, services, TypeScript types en utilities)
3. Authenticatie
4. Kernfunctionaliteiten (teams, taken, opvolgingen) die de generieke architectuur gebruiken
5. Notities/bila's
6. PWA/notificaties

Laat bij elke stap zien hoe TailAdmin's bestaande componenten worden hergebruikt, hoe de generieke systemen worden ingezet, en waarom je bepaalde keuzes maakt.