## ADR-020: Polymorphic activity feed with observer-based system event logging

**Date:** 2026-03-14
**Phase:** Activity Feed & Polling
**Tags:** backend, frontend, polymorphic, observer, activity-feed, traits
**Status:** Accepted

### Context

The plan calls for an activity feed on entities (tasks, follow-ups, notes, bila's) where users can add comments, links, and attachments, and the system automatically logs field changes (e.g. status transitions, priority changes, snooze updates).

**Key design questions:**

1. **How to attach activities to multiple entity types?** — Laravel polymorphic relations (`morphMany`) are the established pattern in this codebase (already used for calendar event linking per ADR-011).
2. **How to capture field changes automatically?** — Options: (a) explicit logging calls in controllers, (b) model observer, (c) event/listener. An observer keeps controllers thin and ensures changes are captured regardless of where the update originates.
3. **How to handle different activity types?** — A single `Activity` model with a string-backed `ActivityType` enum (`comment`, `attachment`, `link`, `system`) keeps queries simple and avoids STI complexity.

### Decision

A new **`Activity`** model with polymorphic `activityable` relationship stores all activity feed entries. A new **`Attachment`** model belongs to an activity for file storage.

**Models and traits:**

- `Activity` — uses `BelongsToUser`, has polymorphic `activityable()` morph relation, typed via `ActivityType` enum
- `Attachment` — uses `BelongsToUser`, belongs to `Activity`, deletes physical files via `deleting` model event
- `HasActivityFeed` trait — added to `Task`, `FollowUp`, `Note`, `Bila`; provides `activities()` morph relation plus helpers: `addComment()`, `addLink()`, `logSystemEvent()`, `getActivityFeed()`

**Automatic system event logging:**

- `ActivityObserver` watches model `updated` events on models using `HasActivityFeed`
- Each model defines tracked fields (e.g. Task: `status`, `priority`; FollowUp: `status`, `snoozed_until`; Bila: `is_done`)
- On change, the observer creates a `system` activity with old → new values in the `content` field

**API pattern:**

- `ActivityController` uses a model-map pattern (like `AutoSaveController`) to resolve the polymorphic parent from route parameters, keeping one controller for all entity types

**Frontend:**

- Activity feed rendered via Blade partial, refreshed using the existing `data-changed` event dispatch (ADR-007)

### Consequences

- Four models now use `HasActivityFeed` — adding activity feeds to new entities requires only using the trait and defining tracked fields
- `ActivityObserver` must be registered for each model that wants automatic system logging
- Activity feed queries use polymorphic `where` clauses — indexed on `(activityable_type, activityable_id)`
- File storage lifecycle is tied to the `Attachment` model's `deleting` event — bulk deletes via raw SQL will not clean up files (mitigated by `CleanOrphanedAttachments` command, see ADR-019)

### Follow-ups / open questions

- Consider adding activity feed to additional entities (e.g. team members) as the feature matures
- Pagination or lazy-loading for entities with large activity histories
