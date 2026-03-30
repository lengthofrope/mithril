# Fix Speech Service Sticky Mode Bug

**Created:** 2026-03-25
**Status:** Complete
**Author:** Bas de Kort
**PRDs:** none

## Problem Statement

When `SPEECH_CUSTOM_URL_ENABLED` is toggled from `true` to `false`, users who had their `speech_service_mode` set to `local` get stuck. The `updateSpeechService()` endpoint returns 403 when the feature flag is off, so they cannot switch back to `server` mode via the UI. Although `isLocalSpeechMode()` correctly returns `false` (preventing local processing), the stale `local` value in the database causes confusion: the frontend still receives `speechServiceMode: 'local'` and `speechServiceUrl` values, and the settings UI section is hidden entirely so the user has no way to fix it.

## Acceptance Criteria

1. When `custom_url_enabled` is `false`, `MeetingPageController` must pass `speechServiceMode: null` (not the raw DB value) so the frontend treats the user as server-mode.
2. When `custom_url_enabled` is `false`, `MeetingPageController` must not leak `speechServiceUrl` or `speechServiceToken` to the view.
3. The `updateSpeechService()` endpoint must allow resetting mode to `server` even when `custom_url_enabled` is `false`, so users can clean up their own state.
4. All existing tests continue to pass; new tests cover the edge cases above.

## Technical Design

### Approach

Two targeted changes:

1. **MeetingPageController** (`show()` method): gate the speech service view variables behind `custom_url_enabled`. When the flag is off, pass `null` values regardless of DB state.
2. **SettingsController** (`updateSpeechService()`): remove the blanket 403 guard. Instead, when `custom_url_enabled` is false, only allow setting mode to `server` (reject `local`). This lets users reset themselves without re-enabling the full feature.

### Affected Components

| Component | Action | Description |
|-----------|--------|-------------|
| `app/Http/Controllers/Web/MeetingPageController.php` | Modify | Gate `speechServiceMode`, `speechServiceUrl`, `speechServiceToken` behind `custom_url_enabled` |
| `app/Http/Controllers/Web/SettingsController.php` | Modify | Allow `server` mode even when `custom_url_enabled` is false |
| `tests/Feature/Http/Controllers/Web/SettingsSpeechServiceTest.php` | Modify | Add test: can reset to server when flag is off |
| `tests/Feature/Http/Controllers/Api/MeetingRecordingControllerTest.php` | No change | Existing test already covers "dispatches job when custom_url_enabled is false regardless of user mode" |

### Edge Cases & Error Handling

- User has `speech_service_mode=local` in DB, `custom_url_enabled=false`: meeting page must behave as server mode; settings must allow switching to server.
- User tries to set `speech_service_mode=local` when `custom_url_enabled=false`: must be rejected with validation error.
- User has `speech_service_mode=null` (never configured): no change in behavior.

## Implementation Phases

### Phase 1: Backend fixes + tests
- **Goal:** Fix both controller behaviors and add test coverage.
- **Specs:**
  - [ ] `MeetingPageController::show()` passes `speechServiceMode: null`, `speechServiceUrl: null`, `speechServiceToken: null` when `custom_url_enabled` is `false`
  - [ ] `MeetingPageController::show()` passes actual user values when `custom_url_enabled` is `true` (existing behavior preserved)
  - [ ] `SettingsController::updateSpeechService()` allows setting mode to `server` when `custom_url_enabled` is `false`
  - [ ] `SettingsController::updateSpeechService()` rejects setting mode to `local` when `custom_url_enabled` is `false`
  - [ ] `SettingsController::updateSpeechService()` allows both modes when `custom_url_enabled` is `true` (existing behavior preserved)
  - [ ] All existing speech service tests pass unchanged
- **Files:** `app/Http/Controllers/Web/MeetingPageController.php`, `app/Http/Controllers/Web/SettingsController.php`, `tests/Feature/Http/Controllers/Web/SettingsSpeechServiceTest.php`

## Parallelization

**Strategy:** Sequential

Single phase, small scope; no benefit from parallelization.

## Out of Scope

- The `.env` typo (`http:///localhost:8090`); user will fix manually.
- Automatic migration to bulk-reset all `local` users when the flag is toggled off. The self-service approach via the settings endpoint is sufficient.
- UI changes; the settings section is already hidden when the flag is off, which is correct. Users who need to reset can do so via a one-time API call or by re-enabling the flag temporarily.

## Open Questions

None.

## Amendment Log

| Date | Phase | Change | Reason | ADR |
|------|-------|--------|--------|-----|
