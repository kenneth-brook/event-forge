# ROADMAP.md

# Event Forge Roadmap

This document tracks the planned evolution of Event Forge from a deployable event calendar utility into a broader modular utility platform.

---

## Guiding Direction

Event Forge is being built as a portable utility platform for static and legacy-hosted websites.

The current focus is the **events system**, but the long-term direction includes multiple related modules that share configuration, display logic, and deployment patterns.

---

## Current State

### v0.1.x - Working Internal Deployment Baseline

Implemented:

- Event CRUD
- Publish / unpublish
- Cancel / uncancel
- Image upload support
- PDF upload support
- FullCalendar integration
- Month / week / day / list views
- Event modal display
- Recurring weekly events
- Recurring monthly nth-weekday events
- Generated child event instances
- Independent child workflow
- Parent/child admin grouping
- User roles (`admin`, `staff`)
- Admin settings entry point

This version is designed to be deployable for current client use.

---

## Near-Term Goals

### v0.2 - Deployment Quality Improvements

Planned:

- Install SQL cleanup and standardization
- Seed SQL examples
- Cleaner deployment checklist
- Config sample files
- Better admin polish
- More descriptive helper text and tooltips
- Event slug generation groundwork
- Improved canceled event presentation
- Stronger validation around recurring event controls

### v0.3 - Rapid Deployment Foundations

Planned:

- Repeatable deployment structure
- Installer planning and bootstrap groundwork
- Environment-aware configuration model
- More portable packaging
- Shared deployment conventions for multiple clients
- Admin-side feature toggles

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

Planned:

- Direct-access event pages
- Shareable event URLs
- Social campaign landing pages
- Event detail templates
- QR-friendly event routing

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

Planned:

- Feature enable/disable controls
- Display defaults
- Hide past events toggle
- Calendar default view
- Branding values
- Attachment controls
- Module enable/disable flags

### User Management Expansion

Planned:

- Password reset controls
- Role refinement
- Audit-friendly account actions
- Safer user lifecycle management

---

## Longer-Term Direction

### v1.0 - Modular Utility Platform Foundation

Target goals:

- Stable deployment pattern
- Module-aware platform structure
- Shared settings architecture
- Cleaner internal extension model

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
- modular growth without unnecessary complexity

---

## Notes

This roadmap is intentionally practical.

Features should be added based on:

1. repeat client need
2. deployment efficiency
3. maintainability across multiple installs
4. alignment with the broader Event Forge platform direction