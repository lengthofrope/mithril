## ADR-002: Multi-tenant user scoping for all core entities

**Date:** 2026-03-06
**Phase:** 4
**Tags:** backend, multi-tenancy, eloquent, security
**Status:** Accepted

### Context

The original project plan designed the dashboard as a single-user application — one team lead, one dataset. However, when multiple users log in they all see the same data because no table has a `user_id` foreign key (except the Laravel `sessions` table).

This became apparent during integration testing when two users saw identical seed data. Sharing task lists, follow-ups, and team member notes between unrelated users is a data-isolation concern.

**Alternatives considered:**

1. **Keep single-user** — enforce one account, remove multi-user login. Simple but limits deployment flexibility (e.g., shared server for multiple leads).
2. **Multi-tenant via `user_id` on all core tables + global scope** — each user owns their data, queries are automatically scoped. Moderate effort, proven Laravel pattern.
3. **Multi-tenant via separate databases per user** — strongest isolation but operationally complex for a personal tool.

Option 2 was chosen: best balance of isolation, simplicity, and Laravel conventions.

### Decision

Every core entity table gets a `user_id` foreign key (constrained, cascading delete). A reusable `BelongsToUser` trait applies a global scope that automatically filters all queries to the authenticated user and sets `user_id` on model creation.

**Tables receiving `user_id`:**
`teams`, `team_members`, `tasks`, `task_groups`, `task_categories`, `follow_ups`, `bilas`, `bila_prep_items`, `agreements`, `notes`, `note_tags`, `weekly_reflections`

**Trait behavior:**
- `booted()` registers a global scope: `->where('user_id', auth()->id())`
- `creating` event sets `user_id` from `auth()->id()` if not already set
- Defines a `user(): BelongsTo` relationship

**Controller/seeder impact:**
- Controllers do not need explicit `user_id` assignment — the trait handles it
- Seeders must set `user_id` explicitly (no auth context)
- Export/import scopes to the authenticated user

### Deviation from plan

The architecture plan assumed a single-user model with no `user_id` on data tables. This decision adds a cross-cutting concern that touches every model and migration. The plan's generic `ResourceController` and `AutoSaveController` patterns remain valid — the global scope makes multi-tenancy transparent to controllers.

### Consequences

- **Migration:** One new migration adding `user_id` (nullable initially for existing data, then backfilled and made non-nullable).
- **Models:** All 12 core models gain the `BelongsToUser` trait.
- **Controllers:** Minimal changes — global scope handles read filtering, trait handles write scoping. Export/import controller must scope queries.
- **Tests:** All feature tests already create a user and call `actingAs()`. The trait's creating hook will pick up `auth()->id()` from the test context. Factory definitions need a `user_id` default.
- **Seeder:** Must pass `user_id` explicitly when creating seed data.
- **Security:** Users can no longer access each other's data through URL manipulation or API calls.

### Follow-ups / open questions

- Should the `push_subscriptions` table (from webpush package) also be scoped, or does the package handle this internally?
- Consider adding a database index on `user_id` for all scoped tables for query performance.
- The global scope must be bypassed in admin/maintenance contexts — document how (`withoutGlobalScope`).
