# Event Forge AI Helper Notes

This file gives AI assistants and code helpers the project context needed to make safe changes without rediscovering the architecture every session.

---

## Current Baseline

- App version: `0.7.2`
- Release channel: `test-candidate`
- Schema version: `9`
- PHP target: shared-hosting friendly PHP, minimum `7.4+`
- Database: MySQL / MariaDB
- Front end: vanilla JavaScript with FullCalendar
- Build tooling: none. Do not introduce Node, npm, bundlers, Composer-only dependencies, or framework build chains.

---

## Project Intent

Event Forge is a portable event CMS/display utility for static or legacy-hosted client sites.

Primary values:

- drop-in deployability
- predictable shared-hosting behavior
- no build chain
- migration-aware upgrades
- client-manageable admin panel
- safe public display
- agency-friendly reuse

---

## Important Rules for Code Helpers

1. Prefer simple PHP and MySQL/MariaDB patterns that work on common shared hosting.
2. Do not add framework dependencies.
3. Do not add WYSIWYG behavior unless explicitly requested. Imported rich HTML should be cleaned to plain text.
4. Keep external provider data normalized at import time, not in the public display layer.
5. Imported events should default to unpublished.
6. Preserve raw external payloads for diagnostics.
7. Use the same map/geocode helpers for native and imported events.
8. Public display should read from the API, not query the DB directly.
9. Keep CSRF checks on admin POST actions.
10. Keep role checks explicit for admin, staff manager, and staff workflows.

---

## Current v0.7.2-TC Features to Preserve

### External Sync

- External sync is controlled by `external_events_enabled`.
- Current provider: `chambermate`.
- Provider registry lives in `events/includes/external-events.php`.
- Sync page: `events/admin/external-events.php`.
- Sync action: `events/admin/sync-external-events.php`.
- Sync errors should be visible in the admin panel and logged.
- External events are matched by `external_source` and `external_id`.
- New imported events must remain unpublished by default.
- Imported records store `external_payload`.

### Import Normalization

Imported ChamberMate fields should normalize into Event Forge fields:

- `activityKey` -> `external_id`
- `eventName` -> `title`
- `eventDescription` / `eventFullDescription` -> plain-text `description`
- `seoDescription` -> `summary`
- `avatarStorageKey` -> image URL
- `eventDetailUrl` -> `external_url`
- `startDateTime` -> `start_datetime`
- `endDateTime` -> `end_datetime`
- `noTimes` -> `all_day`
- address fields -> Event Forge address fields
- provider cost/fee/admission fields -> `event_cost`

### Import Description Cleanup

External rich/editor HTML should be cleaned on import.

Preserve:

- paragraph breaks
- line breaks
- anchor text
- image `alt` text where useful

Strip:

- tags
- inline styles
- editor classes
- provider tracking attributes
- WYSIWYG clutter

### Geocoding

- Native and imported events use `events/includes/location.php`.
- Do not duplicate Mapbox geocoding logic.
- Imported events should geocode only when a usable address and token are available.
- Latitude/longitude are stored on the event row.

### Cost / Admission

- Database column: `events.event_cost`
- Native panel field: Cost / Admission
- Import field: first useful provider cost/fee/price/admission value
- Public API output: `extendedProps.cost`
- Public modal display: below date/time, above location
- Plain text only.

### Multi-Day Timed Events

For public calendar display:

- all-day multi-day events remain spans
- timed multi-day events are expanded for display into one daily time block per date
- the database row itself is not split for native events

Example:

```text
2026-06-15 12:15 through 2026-06-18 15:30
```

Displays as:

```text
June 15 12:15 - 15:30
June 16 12:15 - 15:30
June 17 12:15 - 15:30
June 18 12:15 - 15:30
```

---

## Version References

When updating version references, keep these aligned:

- `events/includes/version.php`
- `README.md`
- `ROADMAP.md`
- `docs/AI_HELPER.md`
- `AGENTS.md`
- migration schema version
- powered-by footer output through API metadata

Current values:

```php
const EVENTFORGE_APP_VERSION = '0.7.2';
const EVENTFORGE_RELEASE_CHANNEL = 'test-candidate';
const EVENTFORGE_SCHEMA_VERSION = 9;
```

---

## Do Not Reintroduce

- hotfix readme fragments as permanent docs
- patch-only installation instructions for standard release bundles
- raw rich HTML rendering for imported descriptions
- separate importer geocoding logic
- direct public DB reads from front-end display pages
- hidden sync failures that only appear in PHP logs
- prepared-statement bind strings in external import write queries unless thoroughly tested across the target hosting environment

---

## Suggested Test Checklist

After changes:

1. Run PHP syntax checks on changed PHP files.
2. Confirm `/events/api.php` returns JSON.
3. Confirm public calendar renders.
4. Confirm modal buttons show with icons.
5. Confirm cost appears when populated.
6. Confirm timed multi-day events display once per day at the daily time range.
7. Confirm native event save geocodes address.
8. Confirm external sync runs.
9. Confirm imported events are unpublished.
10. Confirm imported descriptions are plain text.
11. Confirm imported events with addresses get coordinates.
12. Confirm sync errors surface in the External Event Sync panel.
