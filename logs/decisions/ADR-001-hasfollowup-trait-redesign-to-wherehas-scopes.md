## ADR-001: HasFollowUp trait redesign to whereHas-based scopes

**Date:** 2026-03-06
**Phase:** 2 (Architecture)
**Tags:** backend, traits, eloquent
**Status:** Accepted

### Context

During test writing for Phase 2, it was discovered that the `HasFollowUp` trait had a design bug. The trait defined timeline scopes (`scopeOverdue`, `scopeDueToday`, `scopeDueThisWeek`, `scopeUpcoming`) that directly queried `follow_up_date` and `status` columns. These columns only exist on the `follow_ups` table — not on `tasks` or `team_members`, which are the intended consumers of the trait. Calling these scopes on `Task` or `TeamMember` would produce a SQL error at runtime.

Additionally, the trait was not actually used by any model. `Task` and `TeamMember` each defined their own `followUps()` relationship manually, and `FollowUp` defined its own equivalent scopes directly.

Two alternatives were considered:
1. **Delete the trait entirely** — let each model define `followUps()` manually and rely on `FollowUp`'s own scopes. Simpler, but loses the "opt-in via trait" pattern the architecture plan specifies.
2. **Redesign scopes to use `whereHas`** — the scopes filter the parent model by querying through the `followUps()` relationship. This matches the intended usage: "give me all Tasks that have overdue follow-ups."

### Decision

Option 2 was chosen. The `HasFollowUp` trait now:

- Provides the `followUps()` HasMany relationship (unchanged).
- Provides four `whereHas`-based scopes with renamed methods that clearly describe what they filter:
  - `scopeWithOverdueFollowUps` — parent models with at least one overdue, non-done follow-up
  - `scopeWithFollowUpsDueToday` — parent models with at least one follow-up due today
  - `scopeWithFollowUpsDueThisWeek` — parent models with follow-ups due this week (excluding today)
  - `scopeWithUpcomingFollowUps` — parent models with follow-ups due after the current week
- Uses the `FollowUpStatus` enum instead of hardcoded string values.
- Is now actually used by `Task` and `TeamMember`, replacing their manual `followUps()` definitions.

The `FollowUp` model retains its own direct scopes (`scopeOverdue`, `scopeDueToday`, etc.) for querying follow-ups directly.

### Consequences

- **Changed files:** `app/Models/Traits/HasFollowUp.php`, `app/Models/Task.php`, `app/Models/TeamMember.php`
- **Scope names changed:** Any future code using the old scope names (`overdue`, `dueToday`, etc.) on `Task`/`TeamMember` will need to use the new names (`withOverdueFollowUps`, `withFollowUpsDueToday`, etc.). Since no code consumed these scopes yet, there is no migration impact.
- **Tests rewritten:** `tests/Unit/Models/Traits/HasFollowUpTest.php` now tests the `whereHas`-based scopes on `Task` as the parent model.
- **No database or migration changes.**

### Follow-ups / open questions

- None. The trait is now correct and tested.
