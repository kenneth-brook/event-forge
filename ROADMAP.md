
---

## Updated `ROADMAP.md`

```markdown
# Event Forge Roadmap

This document tracks the evolution of Event Forge from a deployable event calendar utility into a broader modular utility platform.

---

## Guiding Direction

Event Forge is being built as a portable utility platform for static and legacy-hosted websites.

The current focus is the **events system**, but the longer-term direction includes multiple related modules that share configuration, display logic, deployment patterns, and install-level controls.

---

## Current State

### v0.4.x - Deployable Upgrade-Aware Event Platform Baseline

Implemented:

- Event CRUD
- Publish / unpublish
- Cancel / uncancel
- Image upload support
- PDF upload support
- FullCalendar integration
- Month / week / day / list views
- Event modal display
- Modal deep-link support from shared event URLs
- Weekly recurrence
- Monthly nth-weekday recurrence
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

This version is suitable for active client deployment.

---

## Versioning Model

### App Version
Tracked in:

`eventforge_system.app_version`

Used to identify the deployed code release.

### Schema Version
Tracked in:

`eventforge_system.schema_version`

Used to determine whether install-level database migrations must be executed.

This allows:

- safer dropped-in file updates
- install-aware upgrade paths
- legacy deployment adoption
- schema evolution without one-off guessing

---

## Near-Term Goals

### v0.5 - Event Linking and Display Refinement

Planned:

- cleaner event share-link handling
- stronger public-link generation around calendar consumer URLs
- improved modal behavior across more client layouts
- copy-link UX refinement
- event detail presentation polish
- better direct-link behavior on public pages with content above the fold

### v0.6 - Administrative Quality Expansion

Planned:

- profile-level controls for staff
- password reset workflow
- safer user lifecycle handling
- edit-user workflow
- cleaner role-action UX
- additional admin-only install configuration values

### v0.7 - Event Module Maturity Push

Planned:

- recurrence helper improvements
- recurrence validation hardening
- return-independent-child-to-series workflow
- skip-one-occurrence workflow
- improved canceled-series behavior
- better parent/child visibility and inspection tools

---

## Events Module Expansion

### Recurrence Improvements

Planned:

- Monthly date-based recurrence
- Yearly recurrence
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

Planned:

- QR generation for event URLs
- printable event detail / QR handoff
- stronger social-campaign link workflows
- optional public event detail page variants

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

- more automatic consumer-page bootstrapping
- fewer assumptions about page-specific markup
- stronger cross-install portability guarantees

---

## Longer-Term Direction

### v1.0 - Modular Utility Platform Foundation

Target goals:

- stable deployment pattern
- module-aware platform structure
- shared settings architecture
- cleaner internal extension model
- repeatable client rollout across multiple modules

### v2.0 - Native Event Forge Calendar Engine

Long-term goal:

- replace FullCalendar dependency
- use a native Event Forge renderer
- keep the existing event data and admin model intact
- support custom rendering behavior tailored to client needs

---

## Development Principles

Event Forge should continue to prioritize:

- portability
- deployment speed
- maintainability
- low infrastructure requirements
- client-friendly controls
- upgrade safety
- modular growth without unnecessary complexity

---

## Notes

Features should continue to be added based on:

1. repeat client need
2. deployment efficiency
3. maintainability across multiple installs
4. alignment with the broader Event Forge platform direction
5. whether the feature improves portability, upgrade safety, or real client usefulness