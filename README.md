# Event Forge

![License](https://img.shields.io/badge/license-Proprietary-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php)
![Database](https://img.shields.io/badge/database-MySQL%20%7C%20MariaDB-orange)
![Version](https://img.shields.io/badge/version-0.7.2-blue)
![Status](https://img.shields.io/badge/status-test%20candidate-orange)
![Architecture](https://img.shields.io/badge/architecture-portable-informational)

**Event Forge** is a portable event management and display utility built for static and legacy hosting environments.

It provides a self-contained event system with an administrative interface, event API, recurring event support, cancellation handling, category-based styling, location mapping, modal event display, external event ingestion, and deployable calendar views without requiring Node.js, a framework build chain, or a modern CMS stack.

---

## Current Version

**Current release:** `0.7.2`

**Release channel:** `test-candidate`

**Schema version:** `9`

This build is marked as a test candidate. It is intended for focused validation before being promoted to a stable client deployment baseline.

### v0.7.2-TC Highlights

- External event sync from provider feeds, currently ChamberMate.
- Imported events are stored unpublished by default until reviewed.
- Imported event descriptions are cleaned to plain text on import.
- Imported event images are captured from provider storage keys when available.
- Imported event addresses are geocoded with the same Mapbox workflow used by native events.
- Imported cost/admission data is captured into the event cost field.
- Native event form supports Cost / Admission.
- Public modal displays cost near the top when available.
- Timed multi-day events display as daily time blocks in calendar views.
- External sync errors are surfaced in the External Event Sync panel.
- External import write path uses escaped SQL, matching the native save path, to avoid host-specific prepared-statement bind issues.

---

## Purpose

Event Forge was built to solve a practical deployment problem:

- static or lightly dynamic sites still need a real event system
- older hosting environments often cannot support modern application stacks
- clients still expect calendars, recurring events, attachments, categories, maps, external event feeds, and admin controls
- agencies need something repeatable, fast to deploy, and easy to maintain
- installs need to be updatable without rebuilding the whole deployment by hand

Event Forge is designed to fill that gap.

---

## Features

### Event Management

- Create, edit, and delete events
- Publish and unpublish events
- Cancel and uncancel events
- Optional image uploads
- Optional PDF attachments
- Event summaries and descriptions
- Cost / admission field
- Event category assignment
- Event slug generation
- Admin event detail view

### External Event Sync

- Provider registry for external event services
- ChamberMate feed support
- Admin toggle for external sync
- Role-aware Sync Other Events button
- Imported events remain unpublished until reviewed
- Insert/update matching by external source and external ID
- Raw payload retention for troubleshooting
- Plain-text cleanup for imported HTML/editor descriptions
- Imported image URL capture
- Imported cost/admission capture
- Imported address geocoding through the native Mapbox helper path
- Visible sync error reporting in the admin panel

### Recurring Events

- Daily recurrence
- Weekly recurrence
- Monthly nth-weekday recurrence
- Annual recurrence
- Generated child event instances
- Independent child support for one-off overrides
- Parent/child grouping in the admin interface
- Child inheritance of parent content and category
- Series-aware cancellation handling

### Event Display

- Month view
- Week view
- Day view
- List view
- Event detail modal
- Cost shown near top of event popup when available
- Timed multi-day events shown as daily time blocks
- Category color styling
- Category key / legend
- Deep-link event modal support from shared URLs
- Flyer, more-info, add-to-calendar, location, and share action buttons
- SVG action icons
- Powered-by Event Forge footer with version display
- Canceled event display styling

### Maps and Location

- Address storage
- Latitude / longitude storage
- Mapbox geocoding token setting
- Mapbox public token setting
- Native and imported event geocoding
- Public View Location modal when saved coordinates are available

### Category System

- Create, edit, and delete categories
- Background color selection
- Font color selection
- Active / inactive categories
- Category-based display styling in the calendar
- Category legend output below calendar

### Administrative Controls

- Login-protected admin area
- User roles:
  - `staff`
  - `staff_manager`
  - `admin`
- Staff manager account controls for client-side delegated management
- Admin-only system configuration
- User suspension support
- Role-aware settings display
- Public calendar URL setting for shareable event links
- CSRF protection for admin state-changing actions

### Deployment / Upgrade Model

- Install-aware bootstrap flow
- Database configuration setup
- Schema version tracking through `eventforge_system`
- App version tracking through `eventforge_system`
- Release channel tracking through `eventforge_system`
- Migration runner for dropped-in file updates
- Legacy install adoption path for older deployments

### API

Event data is exposed through:

```text
/events/api.php
```

This powers:

- calendars
- list views
- scrollers
- event widgets
- future modules

The API also returns install metadata needed by the display layer, including app version, release channel, calendar theme values, and Mapbox public token.

---

## System Requirements

Minimum requirements:

- PHP **7.4+**
- MySQL or MariaDB
- Standard shared hosting
- JavaScript enabled in the browser

No Node.js, npm, or build pipeline is required.

---

## Upgrade Notes for v0.7.2-TC

v0.7.2 uses schema version `9`.

The key database addition is:

```sql
ALTER TABLE events
  ADD COLUMN event_cost VARCHAR(255) DEFAULT NULL AFTER external_url;
```

Normal installs should use the migration runner. For manual rescue, see:

```text
docs/upgrades/v0.7.2.sql
```

---

## Project Structure

```text
event-forge/
├── README.md
├── ROADMAP.md
├── AGENTS.md
├── LICENSE
├── .gitignore
├── docs/
│   ├── AI_HELPER.md
│   └── upgrades/
│       └── v0.7.2.sql
├── events/
│   ├── api.php
│   ├── categories.php
│   ├── event.php
│   ├── admin/
│   ├── assets/
│   ├── includes/
│   ├── uploads/
│   └── config/
├── install/
│   ├── install.sql
│   ├── seed.sample.sql
│   └── notes.md
├── deploy/
│   └── deployment-checklist.md
└── examples/
    ├── consumer-page-example.php
    └── embed-snippets.md
```
