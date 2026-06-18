# Event Forge Roadmap

This document tracks the evolution of Event Forge from a deployable event calendar utility into a broader modular utility platform.

---

## Guiding Direction

Event Forge is being built as a portable utility platform for static and legacy-hosted websites.

The current focus is the **events system**, but the longer-term direction includes multiple related modules that share configuration, display logic, deployment patterns, and install-level controls.

---

## Current State

### v0.7.2-TC - External Sync and Display Polish Baseline

Implemented:

- Event CRUD
- Publish / unpublish
- Cancel / uncancel
- Image upload support
- PDF upload support
- Cost / admission field
- FullCalendar integration
- Month / week / day / list views
- Event modal display
- Cost display near the top of the modal when available
- Modal deep-link support from shared event URLs
- Timed multi-day event display as daily time blocks
- Daily recurrence
- Weekly recurrence
- Monthly nth-weekday recurrence
- Annual recurrence
- Generated child event instances
- Independent child workflow
- Parent/child admin grouping
- User roles:
  - `staff`
  - `staff_manager`
  - `admin`
- User suspension support
- Role-aware settings display
- Category system
- Category colors and font colors
- Category legend output
- Event slug generation
- Admin event detail view
- Public calendar URL setting
- Installer-driven deployment flow
- `eventforge_system` version tracking
- Migration runner for dropped-in file updates
- Legacy install bridge-upgrade path
- Powered-by Event Forge version display in the consumer layer
- Map location storage and public map display support
- Native and imported event geocoding
- Calendar theme controls
- Admin CSRF hardening for state-changing actions
- Public rendering sanitization and upload validation hardening
- Release channel tracking through `eventforge_system.release_channel`
- External event sync framework
- ChamberMate provider support
- Imported event plain-text description cleanup
- Imported event cost/admission capture
- Imported event image capture
- Visible external sync error reporting

This version is marked as a test candidate for validation before stable client deployment.

---

## Versioning Model

### App Version

Tracked in:

```text
eventforge_system.app_version
```

Used to identify the deployed code release.

Current app version:

```text
0.7.2
```

### Release Channel

Tracked in:

```text
eventforge_system.release_channel
```

Used to distinguish stable builds from test candidates and other pre-release deployment states.

Current release channel:

```text
test-candidate
```

### Schema Version

Tracked in:

```text
eventforge_system.schema_version
```

Used to determine whether install-level database migrations must be executed.

Current schema version:

```text
9
```

This allows:

- safer dropped-in file updates
- install-aware upgrade paths
- legacy deployment adoption
- schema evolution without one-off guessing

---

## Near-Term Goals

### v0.7.x - Test Candidate Hardening

Planned:

- stabilize external provider registry
- add provider-specific field mapping notes
- improve external sync audit output
- add optional imported event review filters
- formalize external provider extension interface
- improve recurring event edit messaging
- add upgrade verification / health check utility
- finalize v0.7.x stable promotion checklist

### v0.8 - Event Module Production Baseline

Planned:

- production-ready external sync settings
- optional import logs
- safer bulk publish/review tools
- stronger install health dashboard
- improved public embed configuration
- better client handoff documentation

---

## Events Module Expansion

### Recurrence Improvements

Implemented:

- Daily recurrence
- Weekly recurrence
- Monthly nth-weekday recurrence
- Annual recurrence

Planned:

- Monthly date-based recurrence
- Better recurrence helper UX
- Safer child editing controls
- Series-level management improvements
- Return independent child to series control
- Skip one occurrence workflow

### Event Detail Layer

Implemented / in progress:

- Event slug generation
- Shareable event URLs
- Modal deep-link landing flow
- Admin event detail page
- PDF flyer action
- External more-info action
- Add-to-calendar action
- View-location action
- Share event action
- Cost/admission display

Planned:

- QR generation for event URLs
- printable event detail / QR handoff
- stronger social-campaign link workflows
- optional public event detail page variants

---

## External Event Sync

Implemented:

- Provider registry
- ChamberMate provider
- Feed URL settings
- Active/inactive toggle
- Staff-manager/admin sync access
- Insert/update external event matching
- Raw payload retention
- Plain-text import cleanup
- Imported cost capture
- Imported image capture
- Imported event geocoding
- Visible sync error reporting

Planned:

- provider mapping documentation
- provider test harness
- import preview mode
- imported event review queue/filter
- optional dry-run sync
- optional import audit table

---

## Planned Platform Modules

Event Forge is expected to expand beyond the calendar itself.

### Upcoming Events Scroller

Planned use cases:

- homepage promo strips
- rotating event highlights
- sidebar event widgets

### Announcement Bar

Planned use cases:

- temporary notices
- seasonal messaging
- attention banners
- campaign support

### Mapped Locations

Planned use cases:

- event maps
- venue pins
- tourism overlays
- location-based discovery

---

## Administrative Platform Goals

### Settings Area Expansion

Implemented in part:

- role-aware settings surface
- admin-only public calendar page URL configuration
- external event sync settings
- Mapbox token settings
- calendar theme controls

Planned:

- feature enable / disable controls
- display defaults
- hide past events modes
- calendar default view
- branding values
- attachment controls
- module enable / disable flags
- environment-aware public display configuration

### User Management Expansion

Implemented in part:

- role refinement
- suspension support
- admin-only install control surface

Planned:

- password reset controls
- edit-user workflow
- audit-friendly account actions
- safer user lifecycle management
- staff profile editing

---

## Deployment Goals

### Legacy Adoption

Implemented in practice:

- bridge-upgrade path for early installs that predate schema versioning
- backfill path for missing structures and indexes
- install normalization into migration-aware state

Planned:

- more formalized legacy adoption checklist
- install audit tooling
- upgrade verification routines

### Portable Consumer Embeds

Implemented in part:

- modal injection
- category legend injection
- powered-by footer injection

Planned:

- configurable embed snippets
- multiple display styles
- lightweight widget variants
