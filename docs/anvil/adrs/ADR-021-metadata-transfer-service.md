## ADR-021: MetadataTransferService bypasses BelongsToUser scope for entity conversion

**Date:** 2026-03-14
**Phase:** Activity Feed & Polling
**Tags:** backend, services, polymorphic, security, conversion, global-scopes
**Status:** Accepted

### Context

Task ↔ Follow-up conversion needs to preserve polymorphic metadata (activities, calendar links, email links) when converting between entity types. These polymorphic records reference the source entity via `morphTo` columns (`*_type` and `*_id`), which must be repointed to the target entity.

**Problem:** The `BelongsToUser` trait (ADR-002) applies a global scope to all queries, filtering by the authenticated user's ID. Polymorphic metadata records (e.g. activities) also use `BelongsToUser`. When updating `activityable_type` and `activityable_id` in bulk, the global scope must be bypassed to ensure all related records are transferred — not just those owned by the current user (relevant if metadata ownership diverges from entity ownership in the future).

**Alternatives considered:**

1. **Delete and recreate metadata** — Loses timestamps and IDs; breaks any external references.
2. **Transfer with global scopes active** — Risks silently leaving behind records that don't match the scope, creating orphans.
3. **Dedicated service using `withoutGlobalScopes()`** — Explicit, auditable bypass of the security boundary for a specific, well-defined operation.

Option 3 was chosen.

### Decision

`MetadataTransferService` is a single-purpose service that transfers polymorphic relationships from a source model to a target model during entity conversion. It uses `withoutGlobalScopes()` on bulk update queries to ensure complete metadata migration.

**Usage pattern:**

```php
$service = new MetadataTransferService();
$service->transfer($sourceTask, $targetFollowUp);
```

**What it transfers:**

- Activities (polymorphic `activityable`)
- Calendar event links (polymorphic pivot)
- Email links (polymorphic pivot)

**Scope bypass is intentional and contained:**

- Only used during conversion operations (two-way Task ↔ FollowUp)
- The service is called from page controllers, not API controllers
- Both source and target are verified to belong to the authenticated user before the service is invoked

### Consequences

- `withoutGlobalScopes()` usage is isolated to one service — any future audit for scope bypasses only needs to check this file
- Adding new polymorphic relationships to entities requires updating `MetadataTransferService` to include them in transfers
- The conversion flow is: (1) create target entity, (2) transfer metadata, (3) mark source as done — if step 2 fails, the source entity still has its metadata intact (safe failure mode)

### Follow-ups / open questions

- If more entity conversions are added (e.g. Note → Task), the service should be extended to handle them
- Consider logging metadata transfers for audit purposes
